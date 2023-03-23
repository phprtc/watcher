<?php

namespace RTC\Tests\Watcher;

use PHPUnit\Framework\TestCase;
use RTC\Watcher\Watching\EventInfo;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

class WatcherTest extends TestCase
{
    public function __construct(
        protected readonly string $baitDir = __DIR__ . '/bait',
    )
    {
        parent::__construct();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!file_exists($this->baitDir)) {
            mkdir($this->baitDir);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->baitDir)) {
            rmdir($this->baitDir);
        }

        parent::tearDown();
    }

    public function testFileAndDirectoryCreation(): void
    {
        run(function (): void {
            $fileToCreate = $this->baitDir('test.txt');
            $dirToCreate = $this->baitDir('test');

            $watcher = TWatcher::create();

            $watcher
                ->addPath($this->baitDir)
                ->onCreate(function (EventInfo $eventInfo) use ($fileToCreate, $dirToCreate, &$watcher) {
                    static $calls = 0;
                    $calls += 1;

                    if ($eventInfo->getWatchedItem()->isFile()) {
                        self::assertSame($fileToCreate, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    if ($eventInfo->getWatchedItem()->isDir()) {
                        self::assertSame($dirToCreate, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    if ($calls == 2) {
                        $watcher->stop();
                    }
                })
                ->start();

            Coroutine::sleep(0.1);
            touch($fileToCreate);
            mkdir($dirToCreate);

            unlink($fileToCreate);
            rmdir($dirToCreate);
        });
    }

    public function testFileAndDirectoryDeletion(): void
    {
        run(function (): void {
            $fileToDelete = $this->baitDir('testy.txt');
            $dirToDelete = $this->baitDir('testy');

            touch($fileToDelete);
            mkdir($dirToDelete);

            $watcher = TWatcher::create();

            $watcher
                ->addPath($this->baitDir)
                ->onDelete(function (EventInfo $eventInfo) use ($fileToDelete, $dirToDelete, &$watcher) {
                    static $calls = 0;
                    $calls += 1;

                    if ($calls == 1) {
                        self::assertSame($fileToDelete, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    if ($calls == 2) {
                        self::assertSame($dirToDelete, $eventInfo->getWatchedItem()->getFullPath());
                        $watcher->stop();
                    }
                })
                ->start();

            unlink($fileToDelete);
            rmdir($dirToDelete);
        });
    }

    public function testFileChange(): void
    {
        run(function (): void {
            $fileToUpdate = $this->baitDir('test.txt');
            touch($fileToUpdate);

            $watcher = TWatcher::create();

            $watcher
                ->addPath($this->baitDir)
                ->onChange(function (EventInfo $eventInfo) use ($fileToUpdate, &$watcher) {
                    self::assertSame($fileToUpdate, $eventInfo->getWatchedItem()->getFullPath());
                    $watcher->stop();

                    unlink($fileToUpdate);
                })
                ->start();

            file_put_contents($fileToUpdate, uniqid());
        });
    }

    public function testDirWithSpecialChars(): void
    {
        run(function (): void {
            $watcher = TWatcher::create();
            $baitDir = $this->baitDir('@hello');
            $baitFile = $baitDir . '/world.txt';

            mkdir($baitDir);
            self::assertDirectoryExists($baitDir);

            $watcher
                ->addPath($baitDir)
                ->onCreate(function (EventInfo $eventInfo) use ($baitFile, $watcher): void {
                    if ($eventInfo->getWatchedItem()->isFile()) {
                        self::assertSame($baitFile, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    $watcher->stop();
                })
                ->start();

            touch($baitFile);
            Coroutine::sleep(0.1);
            unlink($baitFile);

            rmdir($baitDir);
            self::assertDirectoryDoesNotExist($baitDir);
        });
    }

    public function testCreateChangeDeleteOnTheFly(): void
    {
        run(function () {
            $watcher = TWatcher::create();
            $baitDir = $this->baitDir('dir-on-the-fly');
            $baitFile = $baitDir . '/bait.txt';

            $watcher
                ->addPath($this->baitDir)
                ->onCreate(function (EventInfo $eventInfo) use ($baitDir, $baitFile): void {
                    if ($eventInfo->getWatchedItem()->isDir()) {
                        self::assertSame($baitDir, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    if ($eventInfo->getWatchedItem()->isFile()) {
                        self::assertSame($baitFile, $eventInfo->getWatchedItem()->getFullPath());
                    }
                })
                ->onChange(function (EventInfo $eventInfo) use ($baitFile): void {
                    self::assertSame($baitFile, $eventInfo->getWatchedItem()->getFullPath());
                })
                ->onDelete(function (EventInfo $eventInfo) use (&$watcher, $baitDir, $baitFile) {
                    static $calls = 0;

                    $calls += 1;

                    if ($calls == 1) {
                        self::assertSame($baitFile, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    if ($calls == 2) {
                        self::assertSame($baitDir, $eventInfo->getWatchedItem()->getFullPath());
                        $watcher->stop();
                    }
                })
                ->start();

            Coroutine::sleep(0.1);
            mkdir($baitDir);
            Coroutine::sleep(0.1);
            touch($baitFile);

            Coroutine::sleep(0.1);
            file_put_contents($baitFile, uniqid());

            Coroutine::sleep(0.1);
            unlink($baitFile);
            rmdir($baitDir);
        });
    }


    private function baitDir(string $path): string
    {
        return "$this->baitDir/$path";
    }
}
