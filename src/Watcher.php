<?php
declare(strict_types=1);

namespace RTC\Watcher;


use JetBrains\PhpStorm\Pure;
use RTC\Watcher\Watching\EventInfo;
use RTC\Watcher\Watching\EventTrait;
use RTC\Watcher\Watching\WatchedItem;
use Swoole\Event as SWEvent;

class Watcher
{
    use EventTrait;

    protected mixed $inotifyFD;
    protected array $paths = [];
    protected array $extensions = [];
    protected array $watchedItems = [];

    protected array $fileShouldNotEndWith = [
        '~'
    ];


    #[Pure] public static function create(): Watcher
    {
        return new Watcher();
    }

    /**
     * Get inotify file descriptor
     *
     * @return mixed
     */
    public function getInotifyFD(): mixed
    {
        if (!isset($this->inotifyFD)) {
            $this->inotifyFD = inotify_init();
        }

        return $this->inotifyFD;
    }

    /**
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
        static $index = 1;

        // Register paths
        foreach ($this->paths as $path) {
            $this->watchedItems[$index] = $path;
            inotify_add_watch($this->getInotifyFD(), $path, $this->event->value);
            $index += 1;
        }

        // Set up a new event listener for inotify read events
        SWEvent::add($this->getInotifyFD(), function () {
            $events = inotify_read($this->getInotifyFD());

            // IF WE ARE LISTENING TO 'ON_ALL_EVENTS'
            if ($this->willWatchAny) {
                foreach ($events as $event) {
                    if (!empty($event['name'])) {   // Filter out invalid events
                        $this->fireEvent($event);
                    }
                }

                return;
            }

            // INDIVIDUAL LISTENERS
            foreach ($events as $event) {
                // Make sure that we support this event
                if (array_key_exists($event['mask'], self::$constants)) {
                    $this->fireEvent($event);
                }
            }

        });

        // Set to monitor and listen for read events for the given $fd
        SWEvent::set(
            $this->getInotifyFD(),
            null,
            null,
            SWOOLE_EVENT_READ
        );
    }

    private function fireEvent(array $inotifyEvent): void
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

                $eventMask = $this->willWatchAny
                    ? Event::ON_ALL_EVENTS->value
                    : $eventInfo->getMask();

                $this->eventEmitter->emit($eventMask, [$eventInfo]);
            }
        }
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
}