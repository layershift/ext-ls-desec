<?php

namespace PleskExt\Desec;

##### Custom Classes Imports #####
use PleskExt\Desec\Utils\DesecApiClient;
use PleskExt\Utils\MyLogger;
use PleskExt\Utils\Settings;

##### Plesk Classes Imports #####
use Exception;
use pm_Bootstrap;
use pm_Config;
use pm_Settings;
use Psr\Log\LoggerInterface;

class Dns
{
    private MyLogger $myLogger;
    private DesecApiClient $client;

    public function __construct() {
        $this->client = new DesecApiClient();
        $this->myLogger = new MyLogger();
    }

    public function pushRRsetDesec(string $domainName, array $payload, string $method = 'POST'): array {
        $res = $this->client->request($method, "domains/{$domainName}/rrsets/", ['json' => $payload]);
        $this->myLogger->log("debug", "RRsets pushed. Response: " . PHP_EOL . json_encode($res));

        return ["code" => $res['code'], "response" => json_decode($res['body'])];

    }

    public function getRRSets(string $domainName): array {

        $desecArray = [];

        $res = $this->client->request( "GET", "domains/{$domainName}/rrsets");
        $decoded = $res['json'] ?? [];

        foreach ($decoded as $row) {
            if(isset($row['type'], $row['subname'], $row['ttl'], $row['records']) && $row['type'] !== 'NS') {
                $desecArray[] = [
                    'subname' => $row['subname'],
                    'type' => $row['type'],
                    'ttl' => $row['ttl'],
                    'records' => $row['records']
                ];
            }
        }

        $this->myLogger->log("debug", "RRsets retrieved: " . PHP_EOL . json_encode($desecArray, JSON_PRETTY_PRINT));
        return ['code' => $res['code'], 'response' => $desecArray];
    }

    public function getSpecificRRset(string $domainName, string $subname, string $type): array {
        $sub = ($subname === '') ? '@' : $subname;

        $res = $this->client->request(
            'GET',
            "domains/{$domainName}/rrsets/{$sub}/{$type}/",
            [ 'accept404' => true ]
        );

        $this->myLogger->log("debug", "Specific RRset retrieved: " . PHP_EOL . $res['body']);
        return ['code' => $res['code'], 'response' => $res['body']];
     }

}