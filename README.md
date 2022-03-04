# RTC\Watcher

[Swoole](https://swoole.co.uk) file system changes watcher.

## Installation

```
composer require phprtc/watcher
```

## Usage

#### Basic Usage

```php
use RTC\Watcher\Watcher;

require 'vendor/autoload.php';

Watcher::create()
    ->addPath(__DIR__ . '/app')
    ->addPath(__DIR__ . '/views')
    //->fileShouldNotEndWith(['.php'])
    ->onAny(function (EventInfo $eventInfo) {
        dump($eventInfo->getMask());
    });
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
        ->onChange(fn() => $server->reload());
});

$server->start();
```