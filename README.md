# File Watcher

PHP-based file system changes watcher implemented using [**Swoole**](https://swoole.co.uk) & **Inotify**.

## Installation

```
composer require phprtc/watcher ^0.0 --dev
```

## Usage

#### Basic Usage

```php
use RTC\Watcher\Watcher;

require 'vendor/autoload.php';

Watcher::create()
    ->addPath(__DIR__ . '/app')
    ->addPath(__DIR__ . '/views')
    ->onChange(function (EventInfo $eventInfo) {
        echo $eventInfo->getWatchedItem()->getFullPath() . PHP_EOL;
    })
    ->watch();
```

#### Any Event

Listens to any event on given path

Be careful using this method.

```php
use RTC\Watcher\Watcher;

require 'vendor/autoload.php';

Watcher::create()
    ->addPath(__DIR__ . '/app')
    ->onAny(function (EventInfo $eventInfo) {
        echo date('H:i:s') . " - {$eventInfo->getName()} {$eventInfo->getWatchedItem()->getFullPath()}\n";
    })
    ->watch();
```

#### Ignoring Path

Ignore files using regular expression

```php
use RTC\Watcher\Watcher;

require 'vendor/autoload.php';

Watcher::create()
    ->addPath(__DIR__ . '/app')
    ->ignore(__DIR__ . '/test1/t/*')   // Ignore files in "/test1/t/"
    ->ignore([
        __DIR__ . '/test1/t/.*(\.php$)',   // Ignore files that end with "php" in "/test1/t/"
        __DIR__ . '/test1/t/.*(\.js)',   // Ignore files that end with "js" in "/test1/t/"
    ])   
    ->onChange(function (EventInfo $eventInfo) {
        echo date('H:i:s') . " - {$eventInfo->getName()} {$eventInfo->getWatchedItem()->getFullPath()}\n";
    })
    ->watch();
```

#### Filter

- Make sure that the file whose event is being fired should not end with provided characters.
    ```php
    use RTC\Watcher\Watcher;
    
    require 'vendor/autoload.php';
    
    Watcher::create()
        ->addPath(__DIR__ . '/app')
        ->fileShouldNotEndWith(['.php'])
        ->onChange(function (EventInfo $eventInfo) {
            echo $eventInfo->getWatchedItem()->getFullPath() . PHP_EOL;
        })
        ->watch();
    ```

- Only listen to event with file name that matches given extension(s).
    ```php
    use RTC\Watcher\Watcher;
    
    require 'vendor/autoload.php';
    
    Watcher::create()
        ->addPath(__DIR__ . '/app')
        ->addExtension('php')
        ->onChange(function (EventInfo $eventInfo) {
            echo $eventInfo->getWatchedItem()->getFullPath() . PHP_EOL;
        })
        ->watch();
    ```

#### Swoole Server Integration

```php
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use RTC\Watcher\Watcher;

require 'vendor/autoload.php';

$server = new Server('0.0.0.0', 9000);
$server->on('request', function (Request $request, Response $response) {
    $response->end('Hello world');
});

$server->on('start', function (Server $server) {
    echo "Server started at http://0.0.0.0:9000\n";
    
    Watcher::create()
        ->addPath(__DIR__ . '/app')
        ->addPath(__DIR__ . '/views')
        ->onChange(fn() => $server->reload())
        ->watch();
});

$server->start();
```