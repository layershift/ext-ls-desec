<?php

namespace library;

use DateTime;
use Exception;
use library\desec\Dns;
use desec\Domains;
use library\utils\Settings;
use Psr\Log\LoggerInterface;
use pm_Bootstrap;
use pm_Domain;
use pm_Settings;

require_once __DIR__ . '/desec/Dns.php';
require_once __DIR__ . '/utils/Settings.php';

class DomainUtils
{
    private $logger;
    //Lazy loading the logger
    public function getLogger() {
        if (!$this->logger) {
            $logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $logger;
    }

    /*
     * This function returns all the domains that are registered within Plesk + the details that will be shown in the main list
     * @var Settings $enum
     */

    public function getPleskDomains()
    {
        $domainsData = array();
        $desec = new Domains();

        // I am retrieving all the domains that are registered with deSEC and if an errors occurs, I rethrow it
        // to the ApiController
        try {
            $domains = $desec->getDesecDomains();

            $domainNameSet = array_fill_keys(
                array_column($domains['response'], 'name'),
                true
            );

        } catch(Exception $e) {
            throw $e;
        }

        foreach (pm_Domain::getAllDomains() as $pm_Domain) {

            if($pm_Domain->getSetting(Settings::DESEC_STATUS->value) !== "Error") {
                if (isset($domainNameSet[$pm_Domain->getName()])) {
                    $pm_Domain->setSetting(Settings::DESEC_STATUS->value, "Registered");
                } else {
                    $pm_Domain->setSetting(Settings::DESEC_STATUS->value, "Not Registered");
                }
            }

            $domainsData[] = [
                'domain-id' => $pm_Domain->getId(),
                'domain-name' => $pm_Domain->getName(),
                'last-sync-attempt' => $pm_Domain->getSetting(Settings::LAST_SYNC_ATTEMPT->value, "No date"),
                'last-sync-status' => $pm_Domain->getSetting(Settings::LAST_SYNC_STATUS->value, "No data"),
                'dns-status' => $pm_Domain->getDnsZone()->isEnabled(),
                'desec-status' => $pm_Domain->getSetting(Settings::DESEC_STATUS->value, "Not Registered"),
                'auto-sync-status' => $pm_Domain->getSetting(Settings::AUTO_SYNC_STATUS->value, "false")
            ];
        }


        return $domainsData;
    }

    /*
    * This function returns all the DNS records of a domain
    * @var int $domainId
    */
    public function getDNSRecords($domainId) {
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

            if ($type === 'NS' || $type === "SOA" || str_contains("ns", $subname)) { # excluding the NS, SOA - deSEC doesn't need them
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

            $key = $this->buildKey($type, $subname);

            if (!isset($rrsets[$key])) { # grouping the records by a custom key
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
        $summary['timestamp'] = (new DateTime())->format('Y-m-d H:i:s T');
        $domainName = pm_Domain::getByDomainId($domainId)->getName();

        try {
            $allDesecRRsets = $desec->getRRSets($domainName);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }


        foreach ($pleskRrsets as $rrset) {

            $response = $desec->getSpecificRRset($domainName, $rrset['subname'], $rrset['type']);
            $desecRRset = json_decode($response['response'], true);

            $pleskRecords = $rrset['records'];
            $desecRecords = ($response['code'] === 404) ? [] : $desecRRset['records'] ?? [];

            $this->getLogger()->debug("Plesk Record: " . json_encode($pleskRecords) . PHP_EOL);
            $this->getLogger()->debug("deSEC Record: " . json_encode($desecRecords) . PHP_EOL);


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

        if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
            $this->getLogger()->debug("Missing DNS records: " . json_encode($summary['missing'], true) . PHP_EOL);
            $this->getLogger()->debug("Modified DNS records: " . json_encode($summary['modified'], true) . PHP_EOL);
        }

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

            if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                $this->getLogger()->debug("Removable DNS records: " . json_encode($summary['deleted'], true) . PHP_EOL);
            }
        }

        if (count($summary['missing']) > 0) {
            try {
                $response = $desec->pushRRsetDesec($domainName, $summary['missing']);
                if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Created the missing RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);
                }
            } catch (Exception $e) {
                if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Failed to create the missing RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);
                }
                throw $e;
            }
        }

        if (count($summary['modified']) > 0) {
            try {
                $response = $desec->pushRRsetDesec($domainName, $summary['missing'], 'PUT');
                if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Successfully modified RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);
                }
            } catch (Exception $e) {
                throw $e;
            }
        }

        if (count($summary['deleted']) > 0) {
            try {
                $response = $desec->pushRRsetDesec($domainName, $summary['missing'], 'PATCH');
                if (pm_Settings::get(Settings::LOG_VERBOSITY->value, "true") === "true") {
                    $this->getLogger()->debug("Successfully deleted the RRsets in deSEC! API response: " . json_encode($response, true) . PHP_EOL);
                }
            } catch (Exception $e) {
                throw $e;
            }
        }



        return $summary;

    }
}