<?php

require dirname(__DIR__).'/vendor/autoload.php';

const BAIT_DIR = __DIR__ . '/bait/';

if (!file_exists(BAIT_DIR)){
    mkdir(BAIT_DIR);
}