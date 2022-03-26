<?php


namespace RTC\Watcher\Watching;


use Evenement\EventEmitter;
use JetBrains\PhpStorm\Pure;
use RTC\Watcher\Event;

trait EventTrait
{
    protected EventEmitter $eventEmitter;
    protected bool $willWatchAny = false;
    protected array $watchedMasks = [];
    public static array $constants = [
        0 => ['UNKNOWN', 'Unknown code.'],
        1 => ['ON_ACCESS', 'File was accessed (read)'],
        2 => ['ON_MODIFY', 'File was modified'],
        4 => ['ON_ATTRIB', 'Metadata changed (e.g. permissions, mtime, etc.)'],
        8 => ['ON_CLOSE_WRITE', 'File opened for writing was closed'],
        16 => ['ON_CLOSE_NOWRITE', 'File not opened for writing was closed'],
        32 => ['ON_OPEN', 'File was opened'],
        128 => ['ON_MOVED_TO', 'File moved into watched directory'],
        64 => ['ON_MOVED_FROM', 'File moved out of watched directory'],
        256 => ['ON_CREATE', 'File or directory created in watched directory'],
        512 => ['ON_DELETE', 'File or directory deleted in watched directory'],
        1024 => ['ON_DELETE_SELF', 'Watched file or directory was deleted'],
        2048 => ['ON_MOVE_SELF', 'Watch file or directory was moved'],
        24 => ['ON_CLOSE', 'Equals to ON_CLOSE_WRITE | ON_CLOSE_NOWRITE'],
        192 => ['ON_MOVE', 'Equals to ON_MOVED_FROM | ON_MOVED_TO'],
        4095 => ['ON_ALL_EVENTS', 'Bitmask of all the above constants'],
        8192 => ['ON_UNMOUNT', 'File system containing watched object was unmounted'],
        16384 => ['ON_Q_OVERFLOW', 'Event queue overflowed (wd is -1 for this event)'],
        32768 => ['ON_IGNORED', 'Watch was removed (explicitly by inotify_rm_watch() or because file was removed or filesystem unmounted'],
        1073741824 => ['ON_ISDIR', 'Subject of this event is a directory'],
        1073741840 => ['ON_CLOSE_NOWRITE', 'High-bit: File not opened for writing was closed'],
        1073741856 => ['ON_OPEN_HIGH', 'High-bit: File was opened'],
        1073742080 => ['ON_CREATE_HIGH', 'High-bit: File or directory created in watched directory'],
        1073742336 => ['ON_DELETE_HIGH', 'High-bit: File or directory deleted in watched directory'],
        16777216 => ['ON_ONLYDIR', 'Only watch pathname if it is a directory (Since Linux 2.6.15)'],
        33554432 => ['ON_DONT_FOLLOW', 'Do not dereference pathname if it is a symlink (Since Linux 2.6.15)'],
        536870912 => ['ON_MASK_ADD', 'Add events to watch mask for this pathname if it already exists (instead of replacing mask).'],
        2147483648 => ['ON_ONESHOT', 'Monitor pathname for one event, then remove from watch list.'],
    ];


    #[Pure] public function __construct()
    {
        $this->eventEmitter = new EventEmitter();
    }

    public function on(Event $event, callable $handler): static
    {
        if ($event === Event::ON_ALL_EVENTS) {
            $this->willWatchAny = true;
        }

        $this->watchedMasks[] = $event->value;
        $this->eventEmitter->on($event->value, $handler);
        return $this;
    }

    public function once(Event $event, callable $handler): static
    {
        $this->watchedMasks[] = $event->value;
        $this->eventEmitter->once($event->value, $handler);
        return $this;
    }

    protected function emit(Event $event, array $data): void
    {
        $this->eventEmitter->emit($event->value, $data);
    }

    /**
     * @param callable $listener
     * @param bool $fireOnce Indicates that this event should only be listened once
     * @return EventTrait
     */
    public function onAny(callable $listener, bool $fireOnce = false): static
    {
        $this->willWatchAny = true;

        $fireOnce
            ? $this->once(Event::ON_ALL_EVENTS, $listener)
            : $this->on(Event::ON_ALL_EVENTS, $listener);

        return $this;
    }

    /**
     * Listen to change/update on provided paths
     *
     * @param callable $listener
     * @param bool $fireOnce Indicates that this event should only be listened once
     * @return EventTrait
     */
    public function onChange(callable $listener, bool $fireOnce = false): static
    {
        $this->watchedMasks[] = Event::ON_CLOSE_WRITE->value;

        $fireOnce
            ? $this->once(Event::ON_CLOSE_WRITE, $listener)
            : $this->on(Event::ON_CLOSE_WRITE, $listener);

        return $this;
    }
}