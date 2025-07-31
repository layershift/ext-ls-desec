<?php

//namespace library;

use desec\Domains;
use library\DomainUtils;
use library\utils\Settings;
use pm_Bootstrap;
use Psr\Log\LoggerInterface;

class Modules_LsDesecDns_EventListener implements EventListener
{
    public function getLogger()
    {
        if (!$this->logger) {
            $logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $logger;
    }

    public function filterActions()
    {
        return [
            'domain_dns_update',
            'domain-delete'
        ];
    }

    /**
     * @throws pm_Exception
     */
    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {

        switch($action) {
            case 'domain_dns_update':
                $this->getLogger()->debug("[ event-listener ] Domain's DNS zone was updated!");

                $domain_name = $newValues["Domain Name"];
                $domain_id = $objectId;
                $utils = new DomainUtils();

                if(pm_Domain::getByDomainId($domain_id)->getSetting(Settings::AUTO_SYNC_STATUS->value, "true") === "true") {
                    try {
                        $summary = $utils->syncDomain($domain_id);
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "SUCCESS(auto-sync)");
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, (new DateTime())->format('Y-m-d H:i:s T'));

                        $this->getLogger()->debug("[ event-listener ] Successfully synced the DNS zones of the domain(s) in deSEC:\n" . json_encode($summary, true));
                    } catch(Exception $e) {
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_STATUS->value, "FAILED(auto-sync)");
                        pm_Domain::getByDomainId($domain_id)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, (new DateTime())->format('Y-m-d H:i:s T'));

                        $this->getLogger()->error("[ event-listener ] Error occurred during DNS synchronization with deSEC: " . $e->getMessage());
                    }
                }

            case 'domain-delete':
                $domain_name = $oldValues["Domain Name"];
                $this->getLogger()->debug("[ event-listener ] Domain " . $domain_name . " was deleted!");

                $domain_id = $objectId;
                if((pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "false") &&
                    (pm_Domain::getByDomainId($domain_id)->getSetting(Settings::DESEC_STATUS->value, "Not Registered")) === "Registered") {
                    $desec = new Domains();

                    try {
                        $response = $desec->deleteDomain($domain_name);
                        $this->getLogger()->debug("[ event-listener ] Domain " . $domain_name . " was successfully deleted!");

                    } catch(Exception $e) {
                        $this->getLogger()->error("[ event-listener ]" . $e->getMessage());
                    }
                }

        }

    }
}

return new Modules_LsDesecDns_EventListener();