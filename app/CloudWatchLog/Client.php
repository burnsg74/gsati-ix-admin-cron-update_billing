<?php

namespace App\CloudWatchLog;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Sdk;

class Client
{
    private CloudWatchLogsClient $client;
    
    public function __construct()
    {
        $args = [
            'region'  => 'us-west-2',
            'version' => 'latest'
        ];
        
        $sdk      = new Sdk($args);
        $this->client = $sdk->createCloudWatchLogs();
    }
}