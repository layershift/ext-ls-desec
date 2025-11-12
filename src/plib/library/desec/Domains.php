<?php

namespace PleskExt\Desec;

##### Custom Classes Imports #####
use PleskExt\Desec\Utils\DesecApiClient;
use PleskExt\Utils\MyLogger;
use PleskExt\Utils\Settings;

##### Plesk Classes Imports #####
use pm_Domain;
use Psr\Log\LoggerInterface;

class Domains
{
    private MyLogger $myLogger;
    private DesecApiClient $client;

    public function __construct() {
        $this->client = new DesecApiClient();
        $this->myLogger = new MyLogger();
    }

    public function getDomain($domain) {
        $res = $this->client->request('GET', 'domains/' . $domain . '/', ['accept404' => true]);

        if($res['code'] === 200) {
            $this->myLogger->log("debug", "Domain {$domain} successfully fetched!. Response: " . PHP_EOL . $res['body']);
            return true;
        } else {
            $this->myLogger->log("debug", "Domain {$domain} doesn't exist!. Response: " . PHP_EOL . $res['body']);
            return false;
        }

    }

    public function getDesecDomains(): array {
        $res = $this->client->request('GET', 'domains/');
        $this->myLogger->log("debug", "deSEC domains retrieved: " . PHP_EOL . $res['body']);

        return ['code' => $res['code'], 'response' => $res['json']];
    }

    public function addDomain($domain) {
        $res = $this->client->request('POST', 'domains/', [
            'json' => [ 'name' => $domain ]
        ]);

        pm_Domain::getByName($domain)->setSetting(Settings::DESEC_STATUS->value, "Registered");
        pm_Domain::getByName($domain)->setSetting(Settings::AUTO_SYNC_STATUS->value, "true");

        $this->myLogger->log("debug", "Domain {$domain} registered. Response: " . PHP_EOL . $res['body']);
        return ['code' => $res['code'], 'response' => $res['body']];

    }

    public function deleteDomain(string $domain) : array {
        $res = $this->client->request('DELETE', 'domains/' . rawurlencode($domain) . "/");

        $this->myLogger->log("debug", "Domain {$domain} deleted!");
        return ['code' => $res['code'], 'response' => $res['body']];
    }

}


