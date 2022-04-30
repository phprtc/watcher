<?php

namespace RTC\Watcher\CLI\Commands;

use RTC\Watcher\CLI\Console;
use RTC\Watcher\Watcher;
use RTC\Watcher\Watching\EventInfo;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command
{
    protected static $defaultName = 'w';


    protected function configure(): void
    {
        Console::setPrefix('<comment>[Watcher]</comment> ');
        $this->setDescription('Outputs "Hello World"')
            ->addArgument('password', InputArgument::OPTIONAL, 'Server script');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->startWatcher();
    }


    public function startWatcher(): int
    {
        $confFile = ROOT_DIR . 'watcher.json';

        if (!file_exists($confFile)) {
            throw new RuntimeException('Configuration file missing, please create "watcher.json" file in your project directory');
        }

        if (!is_readable($confFile)) {
            throw new RuntimeException('Configuration file cannot be read, please verify the permissions of "watcher.json" against current user');
        }

        $settings = json_decode(file_get_contents($confFile), true);

        $watcher = $this->registerWatcher($settings);
        $watcher->onChange(function (EventInfo $info) {
            Console::writeln("Changed: {$info->getWatchedItem()->getFullPath()}");
        });

        Console::info('Watching given paths...');
        $watcher->start();

        return 1;
    }

    public function registerWatcher(array $settings): Watcher
    {
        Console::comment('Registering watcher');

        if (!isset($settings['paths'])) {
            throw new RuntimeException('Please specify directories to watch');
        }

        $watcher = Watcher::create();

        foreach ($settings['paths'] as $path) {
            $watcher->addPath($path);
        }

        if (isset($settings['ignore'])) {
            $watcher->ignore($settings['ignore']);
        }

        return $watcher;
    }
}