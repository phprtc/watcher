<?php

namespace RTC\Watcher\Watching;

use JetBrains\PhpStorm\Pure;

/**
 * This class represents watched directory/file
 *
 * @internal This should be used internally only!
 */
class WatchedItem
{
    protected bool $isFile;
    protected string $dirName;
    protected string $fullPath;


    #[Pure] public function __construct(
        protected string  $path,
        private EventInfo $eventInfo
    )
    {
        // Set full path
        if (is_dir($this->path)) {
            $this->fullPath = "$this->path/{$this->eventInfo->getEvent()['name']}";
        } else {    // This is when a file is being watched
            $this->fullPath = $this->path;
        }

        $this->isFile = is_file($this->fullPath);
        $this->dirName = pathinfo($this->fullPath)['dirname'];

    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    #[Pure] public function getFullPath(): string
    {
        return $this->fullPath;
    }

    /**
     * @return mixed|string
     */
    public function getDirName(): mixed
    {
        return $this->dirName;
    }

    /**
     * @return bool
     */
    public function isDir(): bool
    {
        return !$this->isFile;
    }

    /**
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->isFile;
    }
}