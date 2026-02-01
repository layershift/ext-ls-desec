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
            'subdomain_dns_update',
            'site_dns_update'
        ];
    }

    /**
     * @throws pm_Exception
     */
    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {
        $logger = new MyLogger();

        $logger->log(
            "debug",
            "Event listener objects: {$objectId} {$action} " .
            json_encode($oldValues) . " " . json_encode($newValues)
        );

        $logger->log('debug', 'ACTION RAW: [' . $action . ']');


        match ($action) {
            'domain_dns_update',
            'subdomain_dns_update',
            'site_dns_update'
            => $this->handleDnsUpdate($objectId, $oldValues, $newValues, $logger),

            'domain_delete'
            => $this->handleDomainDelete($oldValues, $logger),

            default
            => null, // intentionally ignore other events
        };
    }

    /**
     * @throws pm_Exception
     */
    private function handleDnsUpdate(int $domainId, array $oldValues, array $newValues, MyLogger $logger): void {
        $desec = new Domains();
        $manager = new pm_LongTask_Manager();
        $domain = pm_Domain::getByDomainId($domainId);

        $oldDomainName = $this->toAsciiDomain($oldValues['Domain Name'] ?? '');
        $newDomainName = $this->toAsciiDomain($newValues['Domain Name'] ?? '');

        if ($oldDomainName === '' || $newDomainName === '') {
            $logger->log("error", "[ event-listener ] Missing Domain Name for domainId={$domainId}");
            return;
        }

        $autoSyncEnabled =
            $domain->getSetting(Settings::AUTO_SYNC_STATUS->value, "false") === "true";

        // DNS change, name unchanged
        if ($oldDomainName === $newDomainName) {
            if (!$autoSyncEnabled || !$desec->getDomain($oldDomainName)) {
                return;
            }

            $syncDomainTask = new Modules_LsDesecDns_Task_SyncDnsZones();
            $syncDomainTask->setParam('ids', $domainId);
            $manager->start($syncDomainTask);

            return;
        }

        // Domain rename
        $logger->log(
            "debug",
            "[ event-listener ] Domain renamed {$oldDomainName} => {$newDomainName} (ID={$domainId})"
        );

        $desec->addDomain($newDomainName);

        $syncDomainTask = new Modules_LsDesecDns_Task_SyncDnsZones();
        $syncDomainTask->setParam('ids', $domainId);
        $manager->start($syncDomainTask);

        $retentionEnabled =
            pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "true";

        if (!$retentionEnabled && $desec->getDomain($oldDomainName)) {
            $desec->deleteDomain($oldDomainName);
        }

    }

    private function handleDomainDelete(array $oldValues, MyLogger $logger): void
    {
        $desec = new Domains();
        $domainName = $this->toAsciiDomain($oldValues['Domain Name'] ?? '');

        if ($domainName === '') {
            $logger->log("error", "[ event-listener ] Missing Domain Name in delete event");
            return;
        }

        $logger->log("debug", "[ event-listener ] Domain {$domainName} deleted from Plesk Dashboard!");

        $retentionEnabled =
            pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "true";

        if ($retentionEnabled || !$desec->getDomain($domainName)) {
            return;
        }

        try {
            $response = $desec->deleteDomain($domainName);
            $logger->log(
                "debug",
                "[ event-listener ] Removed {$domainName} from deSEC: " . json_encode($response, true)
            );
        } catch (Exception $e) {
            $logger->log(
                "error",
                "[ event-listener ] deSEC delete error for {$domainName}: " . $e->getMessage()
            );
        }
    }


    private function toAsciiDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }

        if (function_exists('idn_to_ascii')) {
            return idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        return $domain;
    }

}

return new Modules_LsDesecDns_EventListener();