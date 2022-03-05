# File Watcher

PHP-based file system changes watcher implemented using [**Swoole**](https://swoole.co.uk) & **Inotify**.

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
    ->onChange(function (EventInfo $eventInfo) {
        var_dump($eventInfo->getMask());
    });
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
            var_dump($eventInfo->getMask());
        });
    ```

- Only listen to event with file name that matches given extension(s).
    ```php
    use RTC\Watcher\Watcher;
    
    require 'vendor/autoload.php';
    
    Watcher::create()
        ->addPath(__DIR__ . '/app')
        ->addExtension('php')
        ->onChange(function (EventInfo $eventInfo) {
            var_dump($eventInfo->getMask());
        });
    ```


#### Any-event
Listens to any event on given path

Be careful using this method.

```php
use RTC\Watcher\Watcher;

require 'vendor/autoload.php';

Watcher::create()
    ->addPath(__DIR__ . '/app')
    ->onAny(function (EventInfo $eventInfo) {
        var_dump($eventInfo->getMask());
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