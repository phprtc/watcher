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
    protected bool $isDir;
    protected string $dirName;


    public function __construct(
        protected string  $path,
        private EventInfo $eventInfo
    )
    {
        $this->isFile = is_file($this->path);
        $this->isDir = is_dir($this->path);

        $this->dirName = pathinfo($this->path)['dirname'];
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
        if ($this->isDir()) {
            return "{$this->getPath()}/{$this->eventInfo->getEvent()['name']}";
        }

        return $this->getPath();
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
        return $this->isDir;
    }

    /**
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->isFile;
    }
}