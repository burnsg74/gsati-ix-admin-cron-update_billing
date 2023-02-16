<?php

namespace App;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Sdk;
use DateTime;
use GSATi\DynamoDb\Client as DB;
use App\Logger\Logger;

class Script
{
    private Logger $log;
    private DB $db;
    
    public function __construct()
    {
        $this->logGroupNames = [
            "/aws/lambda/integrations-c7-app-activated-dev-worker",
            "/aws/lambda/integrations-c7-app-deactivated-dev-worker",
            "/aws/lambda/integrations-c7-clubmembership-updated-dev-worker",
            "/aws/lambda/integrations-c7-customer-created-dev-worker",
            "/aws/lambda/integrations-c7-customer-deleted-dev-worker",
            "/aws/lambda/integrations-c7-customer-updated-dev-worker",
            "/aws/lambda/integrations-c7-gui-bulk-contact-load-dev-worker",
            "/aws/lambda/integrations-c7-gui-dd-get-data-fields-dev-worker",
            "/aws/lambda/integrations-c7-gui-get-mappings-dev-worker",
            "/aws/lambda/integrations-c7-gui-get-settings-dev-worker",
            "/aws/lambda/integrations-c7-gui-put-mapping-dev-worker",
            "/aws/lambda/integrations-c7-gui-put-settings-dev-worker",
            "/aws/lambda/integrations-c7-order-created-dev-worker",
            "/aws/lambda/integrations-c7-order-updated-dev-worker",
            "/aws/lambda/integrations-c7-product-created-dev-worker",
            "/aws/lambda/integrations-c7-product-deleted-dev-worker",
            "/aws/lambda/integrations-c7-product-updated-dev-worker",
            "/aws/lambda/integrations-c7-user-auth-dev-worker",
        ];
    }
    
    public function run(): void
    {
        $this->log->write(">>> Integ Admin Update Billing  : Started <<<");
    
        $cloudWatchLogsSdk = $this->getLogsClient();
        $lastUpdatedAt = $this->db->getSetting('update_billing_last_updated_at');
        
        $startTimeObject = new DateTime($lastUpdatedAt);
        $stopTimeObject = new DateTime();
        $this->log->write("Start Time", $startTimeObject->format('Y-m-d H:i:s'));
        $this->log->write("Stop Time", $stopTimeObject->format('Y-m-d H:i:s'));
    
        $queryId = $this->startCloudWatchLogQuery($cloudWatchLogsSdk, $startTimeObject, $stopTimeObject);
        $logItems = $this->getLogItems($cloudWatchLogsSdk, $queryId);
    
        foreach ($logItems as $logItem) {
            if ($c7request = $this->db->getC7Request($logItem['id'])) {
                $c7request = $this->appendLogItemToC7Request($logItem, $c7request);
                $this->db->putC7Request($c7request);
            } else {
                $this->log->write("c7 request not found: ", $logItem['id']);
            }
        }
    
        $this->db->updateSetting('update_billing_last_updated_at', $stopTimeObject->format('Y-m-d H:i:s'));
        $this->log->write(">>> Integ Admin Update Billing  : Finished <<<");
    }
    
    public function setDb(Db $db): void
    {
        $this->db = $db;
    }
    
    public function setLogger(Logger $log): void
    {
        $this->log = $log;
    }
    
    private function getQueryResults(CloudWatchLogsClient $client, mixed $queryId): array
    {
        do {
            sleep(1);
            $awsQueryResults = $client->GetQueryResults(['queryId' => $queryId]);
            $this->log->write("Query status", $awsQueryResults->get('status'));
        } while ($awsQueryResults->get('status') == 'Scheduled' || $awsQueryResults->get('status') == 'Running');
    
        $this->log->write("Got query results", $awsQueryResults->get('statistics'));
        $results = [];
        foreach ($awsQueryResults->get('results') as $resultPair) {
            $row = [];
            foreach ($resultPair as $item) {
                $row[$item['field']] = $item['value'];
            }
            $results[] = $row;
        }
        
        return $results;
    }
    
    private function getLogsClient(): CloudWatchLogsClient
    {
        $sdk               = new Sdk(['region' => 'us-west-2', 'version' => 'latest',]);
        return $sdk->createCloudWatchLogs();
    }
    
    private function startCloudWatchLogQuery(CloudWatchLogsClient $cloudWatchLogsSdk, DateTime $startTimeObject, DateTime $stopTimeObject): string
    {
        $this->log->write("Start CloudWatch Logs Query");
        $result  = $cloudWatchLogsSdk->startQuery([
            'logGroupNames' => $this->logGroupNames,
            'startTime'     => (int)$startTimeObject->format('U'),
            'endTime'       => (int)$stopTimeObject->format('U'),
            'queryString'   => "filter @type = 'REPORT' | fields  @requestId, @initDuration, @billedDuration, @duration, @memorySize, @maxMemoryUsed",
        ]);
        $this->log->write("Query Id", $result->get('queryId'));
        return $result->get('queryId');
    }
    
    /**
     * @param  CloudWatchLogsClient  $cloudWatchLogsSdk
     * @param  string  $queryId
     * @return array
     */
    private function getLogItems(CloudWatchLogsClient $cloudWatchLogsSdk, string $queryId): array
    {
        $logItems = [];
        foreach ($this->getQueryResults($cloudWatchLogsSdk, $queryId) as $logItem) {
            $logItems[] = [
                'id'             => $logItem['@requestId'],
                'initDuration'   => floatval($logItem['@initDuration']),
                'billedDuration' => floatval($logItem['@billedDuration']),
                'duration'       => floatval($logItem['@duration']),
                'memorySize'     => floatval($logItem['@memorySize']) / 1000000,
                'maxMemoryUsed'  => floatval($logItem['@maxMemoryUsed']) / 1000000
            
            ];
        }
        return $logItems;
    }
    
    /**
     * @param  mixed  $logItem
     * @param  bool|array  $c7request
     * @return array|bool
     */
    private function appendLogItemToC7Request(mixed $logItem, bool|array $c7request): array|bool
    {
        $c7request['initDuration']   = $logItem['initDuration'];
        $c7request['billedDuration'] = $logItem['billedDuration'];
        $c7request['duration']       = $logItem['duration'];
        $c7request['memorySize']     = $logItem['memorySize'];
        $c7request['maxMemoryUsed']  = $logItem['maxMemoryUsed'];
        return $c7request;
    }
}