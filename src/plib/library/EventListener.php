<?php

require_once pm_Context::getPlibDir() . 'bootstrap.php';

##### Custom Classes Imports #####
use PleskExt\Desec\Domains;
use PleskExt\Utils\Settings;
use PleskExt\Utils\DomainUtils;
use Psr\Log\LoggerInterface;
use PleskExt\Utils\MyLogger;
##### Plesk Classes Imports #####

class Modules_LsDesecDns_EventListener implements EventListener
{

    public function filterActions()
    {
        return [
            'domain_dns_update',
            'domain_delete'
        ];
    }

    /**
     * @throws pm_Exception
     */
    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {
        $logger = new MyLogger();

        switch($action) {
            case 'domain_dns_update':
                $logger->log("debug","[ event-listener ] Domain's DNS zone was updated!");
                $domain_id = $objectId;

                if(pm_Domain::getByDomainId($domain_id)->getSetting(Settings::AUTO_SYNC_STATUS->value, "false") === "true") {
                    try {
                        $domain_name = $newValues["Domain Name"];
                        $utils = new DomainUtils();

                        $summary = $utils->syncDomain($domain_id);
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "SUCCESS(auto-sync)");
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, new DateTime()->format('Y-m-d H:i:s T'));

                        $logger->log("debug", "[ event-listener ] Successfully synced the DNS zone of the " . $domain_name . " in deSEC:\n" . json_encode($summary, true));

                    } catch(Exception $e) {
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "FAILED(auto-sync)");
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, new DateTime()->format('Y-m-d H:i:s T'));

                        $logger->log("error","[ event-listener ] Error occurred during DNS synchronization with deSEC of " . $domain_name . ": " . $e->getMessage());
                    }
                }
                break;

            case 'domain_delete':
                $domain_name = $oldValues["Domain Name"];
                $logger->log("debug","[ event-listener ] Domain " . $domain_name . " was deleted!");

                $domain_id = $objectId;
                if((pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "false") &&
                    (pm_Domain::getByDomainId($domain_id)->getSetting(Settings::DESEC_STATUS->value, "Not Registered")) === "Registered") {
                    $desec = new Domains();

                    try {
                        $response = $desec->deleteDomain($domain_name);
                        $logger->log("debug","[ event-listener ] Domain " . $domain_name . " was successfully removed from deSEC! Details: " . json_encode($response, true));

                    } catch(Exception $e) {
                        $logger->log("error","[ event-listener ]" . $e->getMessage());
                    }
                }
                break;

        }

    }
}

return new Modules_LsDesecDns_EventListener();