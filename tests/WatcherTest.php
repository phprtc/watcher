<?php

namespace RTC\Tests\Watcher;

use PHPUnit\Framework\TestCase;
use RTC\Watcher\Watching\EventInfo;
use Swoole\Timer;
use function Co\run;

class WatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!file_exists(__DIR__ . '/bait')) {
            mkdir(__DIR__ . '/bait');
        }
    }

    public function testFileAndDirectoryCreation(): void
    {
        run(function () {
            $fileToCreate = __DIR__ . '/bait/test.txt';
            $dirToCreate = __DIR__ . '/bait/test';

            if (file_exists($fileToCreate)) {
                unlink($fileToCreate);
            }

            if (file_exists($dirToCreate)) {
                rmdir($dirToCreate);
            }

            $watcher = TWatcher::create();

            $watcher->addPath(__DIR__ . '/bait')
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

            touch($fileToCreate);
            mkdir($dirToCreate);
        });
    }

    public function testFileAndDirectoryDeletion(): void
    {
        run(function () {
            $fileToDelete = __DIR__ . '/bait/test.txt';
            $dirToDelete = __DIR__ . '/bait/testy';

            if (!file_exists($fileToDelete)) {
                touch($fileToDelete);
            }

            if (!file_exists($dirToDelete)) {
                mkdir($dirToDelete);
            }

            $watcher = TWatcher::create();

            $watcher->addPath(__DIR__ . '/bait')
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
        run(function () {
            $fileToUpdate = __DIR__ . '/bait/test.txt';

            if (!file_exists($fileToUpdate)) {
                touch($fileToUpdate);
            }

            $watcher = TWatcher::create();

            $watcher->addPath(__DIR__ . '/bait')
                ->onChange(function (EventInfo $eventInfo) use ($fileToUpdate, &$watcher) {
                    self::assertSame($fileToUpdate, $eventInfo->getWatchedItem()->getFullPath());
                    $watcher->stop();

                    unlink($fileToUpdate);
                })
                ->start();

            file_put_contents($fileToUpdate, uniqid());
        });
    }

    public function testCreateChangeDeleteOnTheFly(): void
    {
        run(function () {
            $watcher = TWatcher::create();
            $baitDir = __DIR__ . '/bait/dir-on-the-fly';
            $baitFile = __DIR__ . '/bait/dir-on-the-fly/bait.txt';

            $watcher->addPath(__DIR__ . '/bait')
                ->onCreate(function (EventInfo $eventInfo) use ($baitDir, $baitFile): void {
                    static $calls = 0;
                    $calls += 1;

                    if ($calls == 1) {
                        self::assertSame($baitDir, $eventInfo->getWatchedItem()->getFullPath());
                    }

                    if ($calls == 2) {
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

            mkdir($baitDir);
            touch($baitFile);
            file_put_contents($baitFile, uniqid());
            unlink($baitFile);
        });
    }
}
