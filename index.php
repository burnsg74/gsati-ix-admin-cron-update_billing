<?php

use App\Logger\StdOutLogger;
use App\Script;
use GSATi\DynamoDb\Client as DB;

require 'vendor/autoload.php';

try {
    $script = new Script();
    $script->setDb(new DB());
    $script->setLogger(new StdOutLogger());
    $script->run();
} catch (Exception $e) {
    $logger = new StdOutLogger();
    $logger->write('!!! App Exception !!!');
    $logger->write('Exception Message', $e->getmessage());
    $logger->write('Exception Trace', $e->gettrace());
}