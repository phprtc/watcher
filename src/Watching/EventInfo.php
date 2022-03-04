<?php

namespace RTC\Watcher\Watching;

use RTC\Watcher\Watcher;

class EventInfo
{
    protected int $eventMask;
    protected array $eventInfo;
    protected WatchedItem $watchedItem;


    public function __construct(
        protected array $event,
        string $path
    )
    {
        $this->watchedItem = new WatchedItem($path, $this);
        $this->eventMask = $this->event['mask'];
        $this->eventInfo = Watcher::$constants[$event['mask']];
    }

    public function getMask(): int
    {
        return $this->eventMask;
    }

    public function getName(): string
    {
        return $this->eventInfo[0];
    }

    public function getDesc(): string
    {
        return $this->eventInfo[1];
    }

    /**
     * @return array
     */
    public function getEvent(): array
    {
        return $this->event;
    }

    /**
     * @return WatchedItem
     */
    public function getWatchedItem(): WatchedItem
    {
        return $this->watchedItem;
    }
}