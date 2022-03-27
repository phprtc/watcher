<?php
declare(strict_types=1);

namespace RTC\Watcher;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RTC\Watcher\Watching\EventInfo;
use RTC\Watcher\Watching\EventTrait;
use RTC\Watcher\Watching\WatchedItem;
use Swoole\Event as SwooleEvent;

class Watcher
{
    use EventTrait {
        EventTrait::__construct as private __eventTraitConstructor;
    }

    protected mixed $inotifyFD;
    protected array $paths = [];
    protected array $extensions = [];
    protected array $ignorePaths = [];
    protected array $watchedItems = [];

    protected readonly int $maskItemCreated;
    protected readonly int $maskItemDeleted;

    protected array $fileShouldNotEndWith = [
        '~'
    ];


    public static function create(): Watcher
    {
        return new Watcher();
    }

    public function __construct()
    {
        $this->__eventTraitConstructor();

        $this->maskItemCreated = Event::ON_CREATE_HIGH->value;
        $this->maskItemDeleted = Event::ON_DELETE_HIGH->value;
    }

    /**
     * Get inotify file descriptor
     *
     * @return mixed
     */
    protected function getInotifyFD(): mixed
    {
        if (!isset($this->inotifyFD)) {
            $this->inotifyFD = inotify_init();
        }

        return $this->inotifyFD;
    }

    /**
     * Handles directory creation/deletion on the fly
     *
     * @param array $inotifyEvent
     * @return void
     */
    protected function inotifyPerformAdditionalOperations(array $inotifyEvent): void
    {
        // Handle directory creation
        if ($inotifyEvent['mask'] == $this->maskItemCreated) {
            $eventInfo = new EventInfo($inotifyEvent, $this->watchedItems[$inotifyEvent['wd']]);
            // Register this path also if it's directory
            if ($eventInfo->getWatchedItem()->isDir()) {
                $this->inotifyWatchPathRecursively($eventInfo->getWatchedItem()->getFullPath());
            }

            return;
        }

        // Handle directory deletion
        if ($inotifyEvent['mask'] == $this->maskItemDeleted) {
            $eventInfo = new EventInfo($inotifyEvent, $this->watchedItems[$inotifyEvent['wd']]);
            // Remove this path also if it's directory
            if ($eventInfo->getWatchedItem()->isDir()) {
                $this->inotifyRemovePathWatch($eventInfo);
            }
        }
    }

    /**
     * Register directory/file to inotify watcher
     * Loops through directory recursively and register all it's subdirectories as well
     *
     * @param string $path
     * @return void
     */
    protected function inotifyWatchPathRecursively(string $path): void
    {
        if (is_dir($path)) {
            $iterator = new RecursiveDirectoryIterator($path);

            // Loop through files
            foreach (new RecursiveIteratorIterator($iterator) as $file) {
                if ($file->isDir()/**&& !in_array($file->getRealPath(), $this->watchedItems)**/) {
                    $this->inotifyWatchPath($file->getRealPath());
                }
            }

            return;
        }

        // Register file watch
        $this->inotifyWatchPath($path);
    }

    /**
     * Register directory/file to inotify watcher
     *
     * @param string $path
     * @return void
     */
    protected function inotifyWatchPath(string $path): void
    {
        $descriptor = inotify_add_watch(
            $this->getInotifyFD(),
            $path,
            Event::ON_ALL_EVENTS->value
        );

        $this->watchedItems[$descriptor] = $path;
    }

    /**
     * Stop watching file/directory
     *
     * @param EventInfo $eventInfo
     * @return void
     */
    protected function inotifyRemovePathWatch(EventInfo $eventInfo)
    {
        // Stop watching event
        inotify_rm_watch($this->getInotifyFD(), $eventInfo->getWatchDescriptor());

        // Stop tracking descriptor
        unset($this->watchedItems[$eventInfo->getWatchDescriptor()]);
    }

    /**
     * Trigger an event
     *
     * @param array $inotifyEvent
     * @return void
     */
    protected function fireEvent(array $inotifyEvent): void
    {
        $shouldFireEvent = array_key_exists($inotifyEvent['mask'], self::$constants);

        if ($shouldFireEvent) {
            // Make sure that the inotify fired event file name does not contain unneeded chars
            foreach ($this->fileShouldNotEndWith as $char) {
                if (str_ends_with($inotifyEvent['name'], $char)) {
                    $shouldFireEvent = false;
                    break;
                }
            }

            // Make sure that the event has registered items
            if ($this->willWatchAny && $shouldFireEvent) {
                $shouldFireEvent = array_key_exists($inotifyEvent['wd'], $this->watchedItems);
            }

            // Handle extension condition
            if ($shouldFireEvent && !empty($this->extensions)) {
                $expExt = explode('.', $inotifyEvent['name']);
                $shouldFireEvent = in_array(end($expExt), $this->extensions);
            }

            // Fire event if conditions are met
            if ($shouldFireEvent) {
                $eventInfo = new EventInfo($inotifyEvent, $this->watchedItems[$inotifyEvent['wd']]);

                foreach ($this->ignorePaths as $ignorePath) {
                    if (1 === preg_match("@$ignorePath@", $eventInfo->getWatchedItem()->getFullPath())) {
                        return;
                    }
                }

                $eventMask = $this->willWatchAny
                    ? Event::ON_ALL_EVENTS->value
                    : $eventInfo->getMask()->value;

                $this->eventEmitter->emit($eventMask, [$eventInfo]);
            }
        }
    }

    /**
     * Returns list of files currently being watched
     *
     * @return WatchedItem[]
     */
    public function getWatchedItems(): array
    {
        return $this->watchedItems;
    }

    /**
     * Start watching file system changes
     *
     * @return void
     */
    public function watch(): void
    {
        // Register paths
        foreach ($this->paths as $path) {
            $this->inotifyWatchPathRecursively($path);
        }

        // Set up a new event listener for inotify read events
        SwooleEvent::add($this->getInotifyFD(), function () {
            $inotifyEvents = inotify_read($this->getInotifyFD());

            // IF WE ARE LISTENING TO 'ON_ALL_EVENTS'
            if ($this->willWatchAny) {
                foreach ($inotifyEvents as $inotifyEvent) {
                    $this->fireEvent($inotifyEvent);

                    $this->inotifyPerformAdditionalOperations($inotifyEvent);
                }

                return;
            }

            // INDIVIDUAL LISTENERS
            foreach ($inotifyEvents as $inotifyEvent) {
                // Make sure that we support this event
                if (in_array($inotifyEvent['mask'], $this->watchedMasks)) {
                    $this->fireEvent($inotifyEvent);

                    $this->inotifyPerformAdditionalOperations($inotifyEvent);
                }
            }

        });

        // Set to monitor and listen for read events for the given $fd
        SwooleEvent::set(
            $this->getInotifyFD(),
            null,
            null,
            SWOOLE_EVENT_READ
        );
    }

    /**
     * Add file extension filter
     *
     * @param string $extension
     * @return $this
     */
    public function addExtension(string $extension): Watcher
    {
        $this->extensions[] = $extension;
        return $this;
    }

    /**
     * Add path to watch
     *
     * @param string $path
     * @return $this
     */
    public function addPath(string $path): Watcher
    {
        $this->paths[] = $path;
        return $this;
    }

    /**
     * Add additional filter
     *
     * @param string[] $characters
     * @param bool $clearPreviousEntry This will overwrite previous entry, including built-in entries
     * @return $this
     */
    public function fileShouldNotEndWith(array $characters, bool $clearPreviousEntry = false): Watcher
    {
        $clearPreviousEntry
            ? $this->fileShouldNotEndWith = $characters
            : $this->fileShouldNotEndWith = array_merge($this->fileShouldNotEndWith, $characters);

        return $this;
    }

    /**
     * Ignore event from being fired if path matches given ones
     *
     * @param array|string $path
     * @return $this
     */
    public function ignore(array|string $path): Watcher
    {
        if (is_string($path)) {
            $this->ignorePaths[] = $path;
            return $this;
        }

        $this->ignorePaths = array_merge($this->ignorePaths, $path);
        return $this;
    }

    /**
     * Proxy of Watcher::watch()
     *
     * @return void
     * @see Watcher::watch()
     */
    public function start(): void
    {
        $this->watch();
    }

    /**
     * Stop watching
     *
     * @return void
     */
    public function stop(): void
    {
        if (isset($this->inotifyFD)) {
            // Ask Swoole to remote watcher on this FD
            SwooleEvent::del($this->inotifyFD);
            // Close inotify FD resource
            fclose($this->inotifyFD);
            // Delete the var content
            unset($this->inotifyFD);
        }
    }
}