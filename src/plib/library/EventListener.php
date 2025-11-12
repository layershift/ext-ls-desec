<?php

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
            'domain_delete',
            'phys_domain_delete'

        ];
    }

    /**
     * @throws pm_Exception
     */
    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {

        $logger = new MyLogger();
        $logger->log("debug", "Event listener objects:" . $objectId . " " . $action . " " . json_encode($oldValues) . " " . json_encode($newValues));

        switch($action) {
            case 'domain_dns_update':
                $logger->log("debug","[ event-listener ] Domain's DNS zone was updated!");
                $domain_id = $objectId;
                $desec = new Domains();
                $utils = new DomainUtils();
                $oldDomainName = $oldValues["Domain Name"];
                $newDomainName = $newValues["Domain Name"];

            if($oldDomainName === $newDomainName) {
                if (pm_Domain::getByDomainId($domain_id)->getSetting(Settings::AUTO_SYNC_STATUS->value, "false") === "true" && $desec->getDomain($oldDomainName)) {
                    try {

                        $summary = $utils->syncDomain($domain_id);
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "SUCCESS(auto-sync)");
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, new DateTime()->format('Y-m-d H:i:s T'));

                        $logger->log("debug", "[ event-listener ] Successfully synced the DNS zone of the " . $oldDomainName . " in deSEC:\n" . json_encode($summary, true));

                    } catch (Exception $e) {
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "FAILED(auto-sync)");
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, new DateTime()->format('Y-m-d H:i:s T'));

                        $logger->log("error", "[ event-listener ] Error occurred during DNS synchronization with deSEC of " . $oldDomainName . ": " . $e->getMessage());
                    }
                }
            } else {
                $logger->log("debug","[ event-listener ] Domain's name was changed from " . $oldDomainName . " to " . $newValues["Domain Name"] . PHP_EOL . "Domain's ID: " . $domain_id);

                # Adding domain and sync it with deSEC, and delete it(take DOMAIN_RETENTION into consideration)
                try {
                    $addDomainResponse = $desec->addDomain($newDomainName);
                    $syncDomainResponse = $utils->syncDomain($domain_id);

                    if((pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "false")) {
                        $response = $desec->deleteDomain($oldDomainName);
                    }

                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "SUCCESS(auto-sync)");
                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, new DateTime()->format('Y-m-d H:i:s T'));

                } catch (Exception $e) {
                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "FAILED(auto-sync)");
                    pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, new DateTime()->format('Y-m-d H:i:s T'));

                    $logger->log("error", "[ event-listener ] Error occurred during DNS synchronization with deSEC of " . $oldDomainName . ": " . $e->getMessage());
                }

            }
                break;

            case 'domain_delete':
                $desec = new Domains();
                $oldDomainName = idn_to_ascii($oldValues["Domain Name"]); //here
                $logger->log("debug","[ event-listener ] Domain " . $oldDomainName . " was deleted!");

                $logger->log("debug", pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") . " " . $desec->getDomain($oldDomainName));

                if((pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "false") && $desec->getDomain($oldDomainName)) {

                    try {
                        $response = $desec->deleteDomain($oldDomainName);
                        $logger->log("debug","[ event-listener ] Domain " . $oldDomainName . " was successfully removed from deSEC! Details: " . json_encode($response, true));

                    } catch(Exception $e) {
                        $logger->log("error","[ event-listener ]" . $e->getMessage());
                    }

                }
                break;

        }

    }
}

return new Modules_LsDesecDns_EventListener();