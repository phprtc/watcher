<?php

namespace RTC\Watcher\CLI;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Console
{
    protected static OutputInterface $output;
    protected static string $prefix;


    /**
     * @param mixed $data
     */
    public static function dump(mixed $data): void
    {
        ob_start();
        dump($data);
        $output = ob_get_clean();
        static::write($output);
    }

    public static function setPrefix(string $prefix): void
    {
        static::$prefix = $prefix;
    }

    public static function getPrefix(): string
    {
        return isset(self::$prefix) ? static::$prefix : '';
    }

    protected static function getOutput(): OutputInterface
    {
        if (!isset(static::$output)) {
            static::$output = new ConsoleOutput();
        }

        return static::$output;
    }

    public static function comment(string $message): void
    {
        static::writeWithTimestamp("<comment>$message</comment>");
    }

    public static function info(string $message): void
    {
        static::writeWithTimestamp("<info>$message</info>");
    }

    public static function question(string $message): void
    {
        static::writeWithTimestamp("<question>$message</question>");
    }

    public static function error(string $message): void
    {
        static::writeWithTimestamp("<error>$message</error>");
    }

    public static function echo(string $message): void
    {
        static::writeWithTimestamp($message);
    }

    public static function write(string $message): void
    {
        static::writeWithTimestamp($message, false);
    }

    public static function writeln(string $message): void
    {
        static::writeWithTimestamp($message);
    }

    private static function writeWithTimestamp(string $message, bool $newLine = true): void
    {
        $message = static::prependTime( self::getPrefix() . $message);
        self::writeWithoutTimestamp($message, $newLine, false);
    }

    private static function writeWithoutTimestamp(string $message, bool $newLine = true, $prefix = true): void
    {
        $message = $prefix ? self::getPrefix() . $message : $message;
        static::getOutput()->write($message . ($newLine ? PHP_EOL : null));
    }

    protected static function prependTime(string $message): string
    {
        return date('[Y-m-d H:i:s]') . " $message";
    }
}