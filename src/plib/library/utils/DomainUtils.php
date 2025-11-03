<?php

namespace PleskExt\Utils;


##### Custom Classes Imports #####
use PleskExt\Desec\Dns;
use PleskExt\Desec\Domains;
use PleskExt\Utils\MyLogger;

##### Plesk Classes Imports #####
use DateTime;
use Exception;
use pm_Bootstrap;
use pm_Domain;
use pm_Session;
use pm_Settings;
use Psr\Log\LoggerInterface;

class DomainUtils
{
    private MyLogger $myLogger;
    private Domains $desecDomains;

    public function __construct() {
        $this->myLogger = new MyLogger();
        $this->desecDomains = new Domains();
    }

    /*
     * This function returns all the domains that are registered within Plesk + the details that will be shown in the main list
     * @var Settings $enum
     */

    /**
     * @throws \pm_Exception
     * @throws Exception
     */
    public function getPleskDomains(): array
    {
        $domainsData = array();

        $domains = $this->desecDomains->getDesecDomains();
        $domainNameSet = array_fill_keys(
            array_column($domains['response'], 'name'),
            true
        );

        foreach (pm_Domain::getAllDomains() as $pm_Domain) {

            if($pm_Domain->getSetting(Settings::DESEC_STATUS->value) !== Status::STATUS_ERROR->value) {
                if (isset($domainNameSet[$pm_Domain->getName()])) {
                    $pm_Domain->setSetting(Settings::DESEC_STATUS->value, Status::STATUS_REGISTERED->value);
                } else {
                    $pm_Domain->setSetting(Settings::DESEC_STATUS->value, Status::STATUS_NOT_REGISTERED->value);
                }
            }

            if(pm_Session::getClient()->isAdmin()) {
                $domainLink = "/admin/subscription/login/id/".$pm_Domain->getId()."?pageUrl=/smb/web/overview/id/".$pm_Domain->getId()."/type/domain";
            } else {
                $domainLink = "/smb/web/overview/id/".$pm_Domain->getId()."/type/domain";
            }

            $domainsData[] = [
                'domain-id' => $pm_Domain->getId(),
                'domain-name' => idn_to_ascii($pm_Domain->getName()),
                'last-sync-attempt' => $pm_Domain->getSetting(Settings::LAST_SYNC_ATTEMPT->value, "No date"),
                'last-sync-status' => $pm_Domain->getSetting(Settings::LAST_SYNC_STATUS->value, "No data"),
                'dns-status' => $pm_Domain->getDnsZone()->isEnabled(),
                'desec-status' => $pm_Domain->getSetting(Settings::DESEC_STATUS->value, "Not Registered"),
                'auto-sync-status' => $pm_Domain->getSetting(Settings::AUTO_SYNC_STATUS->value, "false"),
                'domain-link' => $domainLink,
            ];
        }

        $this->myLogger->log("debug", "Data that was retrieved about the domains: " . PHP_EOL . print_r($domainsData, true));
        return $domainsData;
    }

    /*
    * This function returns all the DNS records of a domain
    * @var int $domainId
    */
    /**
     * @throws \pm_Exception
     */
    public function getDNSRecords($domainId): array
    {
        $rrsets = array();
        $domain = pm_Domain::getByDomainId($domainId);
        $dnsZone = $domain->getDnsZone();

        foreach($dnsZone->getRecords() as $record) {

            $type = $record->getType();
            $value = $record->getValue();
            $ttl = $record->getTtl();
            $option = $record->getOption();
            $host = rtrim($record->getHost(), '.');
            $domainName = rtrim($domain->getName(), '.');

            if ($host === $domainName) {
                $subname = '';
            } elseif (str_ends_with($host, '.' . $domainName)) {
                $subname = substr($host, 0, -strlen('.' . $domainName));
            } else {
                $subname = $host;
            }

            $key = $this->buildKey($type, $subname);
            $this->myLogger->log("debug","Key  " . $key . " for " . $type);

            if ($type === 'NS' || $type === "SOA") { # excluding the NS, SOA - deSEC doesn't need them
                $this->myLogger->log("debug","Record excluded(" . $key . "): " . $subname . " " . $type . " " . $ttl . " " . $value);
                continue;
            }

            if ($option) {
                $value = $option . " " . $value;
            }

            if ($type === "TXT") {
                $value = '"' . addslashes($value) . '"'; # the TXT record needs to be surrounded by quotes and terminated with slashes - that's why I am using the addslashes() method
            }

            if (!$ttl || $ttl < 3600) { # deSEC doesn't accept ttl values lower than 3600
                $ttl = 3600;
            }

            if (!isset($rrsets[$key])) { # grouping the records by a custom key

                $this->myLogger->log("debug","Record included(" . $key . "): " . $subname . " " . $type . " " . $ttl . " " . $value);
                $rrsets[$key] = [
                    'subname' => $subname,
                    'type' => $type,
                    'ttl' => $ttl,
                    'records' => []
                ];
            }

            $rrsets[$key]['records'][] = $value; # all the records value under the 'records' key

        }

        return array_values($rrsets); # replacing the keys of the rrsets array with 0, 1, 2 .....
    }

    private function buildKey($type, $subname) {
        return "{$type}|{$subname}";
    }

    private function normalizeRecord(array $records): array {
        return array_map(function ($record) {
            $record = stripslashes($record);
            $record = str_replace(['"', ' '], "", $record);
            return trim($record);
        }, $records);
    }

    private function recordsEqual(array $record1, array $record2, int $ttl1, int $ttl2): bool {
        $norm1 = $this->normalizeRecord($record1);
        $norm2 = $this->normalizeRecord($record2);
        sort($norm1);
        sort($norm2);

        return ($norm1 === $norm2 && $ttl1 === $ttl2);
    }

    /**
     * @throws \pm_Exception
     * @throws Exception
     */
    public function syncDomain($domainId)
    {

        $summary = [
            'missing' => [],
            'modified' => [],
            'deleted' => [],
            'timestamp' => null
        ];

        $desec = new Dns();
        $pleskRrsets = $this->getDNSRecords($domainId);
        $summary['timestamp'] = new DateTime()->format('Y-m-d H:i:s T');
        $domainName = pm_Domain::getByDomainId($domainId)->getName();

        try {
            $allDesecRRsets = $desec->getRRSets($domainName);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }


        foreach ($pleskRrsets as $rrset) {

            $this->myLogger->log("debug", "Something something 1" . $domainName . " " . $rrset['subname'] . " " . $rrset['type']);

            $response = $desec->getSpecificRRset($domainName, $rrset['subname'], $rrset['type']);
            $desecRRset = json_decode($response['response'], true);

            $this->myLogger->log("debug", "Something something 2");

            $pleskRecords = $rrset['records'];
            $desecRecords = ($response['code'] === 404) ? [] : $desecRRset['records'] ?? [];

            $this->myLogger->log("debug", "Plesk Records: " . json_encode($pleskRecords) . PHP_EOL);
            $this->myLogger->log("debug", "deSEC Records: " . json_encode($desecRecords) . PHP_EOL);


            if(empty($desecRecords)) {
                $summary['missing'][] = $rrset;
            } else {
                $pleskTtl = $rrset['ttl'];
                $desecTtl = $desecRRset['ttl'];

                if(!$this->recordsEqual($pleskRecords, $desecRecords, $pleskTtl, $desecTtl)) {
                    $summary['modified'][] = $rrset;
                }
            }

            $key = $this->buildKey($rrset['type'], $rrset['subname']);
            $pleskRecords[$key] = true;

        }

        $this->myLogger->log("debug","Missing DNS records: " . json_encode($summary['missing'], true) . PHP_EOL);
        $this->myLogger->log("debug","Modified DNS records: " . json_encode($summary['modified'], true) . PHP_EOL);

        if(!empty($allDesecRRsets['response']) ) {

            $pleskRrsetKeys = [];
            foreach ($pleskRrsets as $rr) {
                $key = $this->buildKey($rr['type'], $rr['subname']);
                $pleskRrsetKeys[$key] = true;
            }

            foreach ($allDesecRRsets['response'] as $rrset) {
                if ($rrset['type'] === 'NS') {
                    continue; // Skip NS records
                }

                $key = $this->buildKey($rrset['type'], $rrset['subname']);
                if (!isset($pleskRrsetKeys[$key])) {
                    $summary['deleted'][] = [
                        'subname' => $rrset['subname'],
                        'type' => $rrset['type'],
                        'ttl' => $rrset['ttl'],
                        'records' => []
                    ];
                }
            }

            $this->myLogger->log("debug","Removable DNS records: " . json_encode($summary['deleted'], true) . PHP_EOL);

        }

        if (count($summary['missing']) > 0) {
            try {
                $response = $desec->pushRRsetDesec($domainName, $summary['missing']);
                $this->myLogger->log("debug","Created the missing RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);

            } catch (Exception $e) {
                $this->myLogger->log("debug","Failed to create the missing RRsets in deSEC! API response: " . json_encode($e->getMessage(), true) . PHP_EOL);
                throw $e;
            }
        }

        if (count($summary['modified']) > 0) {
            try {
                $response = $desec->pushRRsetDesec($domainName, $summary['modified'], 'PUT');
                $this->myLogger->log("debug","Successfully modified RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);

            } catch (Exception $e) {

                $this->myLogger->log("debug","Failed to modify the RRsets in deSEC! API response: " . json_encode($e->getMessage(), true) . PHP_EOL);
                throw $e;
            }
        }

        if (count($summary['deleted']) > 0) {
            try {
                $response = $desec->pushRRsetDesec($domainName, $summary['deleted'], 'PATCH');
                $this->myLogger->log("debug","Successfully deleted the RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);

            } catch (Exception $e) {

                $this->myLogger->log("debug","Failed to delete the RRsets from deSEC! API response: " . json_encode($e->getMessage(), true) . PHP_EOL);
                throw $e;
            }
        }



        return $summary;

    }
}