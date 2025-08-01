<?php

require_once __DIR__ . '/utils/Settings.php';
require_once __DIR__ . '/desec/Domains.php';
require_once __DIR__ . '/DomainUtils.php';

use desec\Domains;
use library\DomainUtils;
use library\utils\Settings;
use Psr\Log\LoggerInterface;

class Modules_LsDesecDns_EventListener implements EventListener
{

    private $logger;

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
            'domain_delete'
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
                $domain_id = $objectId;

                if(pm_Domain::getByDomainId($domain_id)->getSetting(Settings::AUTO_SYNC_STATUS->value, "false") === "true") {
                    try {
                        $domain_name = $newValues["Domain Name"];
                        $utils = new DomainUtils();

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
                break;

            case 'domain_delete':
                $domain_name = $oldValues["Domain Name"];
                $this->getLogger()->debug("[ event-listener ] Domain " . $domain_name . " was deleted!");

                $domain_id = $objectId;
                if((pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "false") &&
                    (pm_Domain::getByDomainId($domain_id)->getSetting(Settings::DESEC_STATUS->value, "Not Registered")) === "Registered") {
                    $desec = new Domains();

                    try {
                        $response = $desec->deleteDomain($domain_name);
                        $this->getLogger()->debug("[ event-listener ] Domain " . $domain_name . " was successfully removed from deSEC! Details: " . json_encode($response, true));

                    } catch(Exception $e) {
                        $this->getLogger()->error("[ event-listener ]" . $e->getMessage());
                    }
                }
                break;

        }

    }
}

return new Modules_LsDesecDns_EventListener();