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

    private function handleDnsUpdate(int $domainId, array $oldValues, array $newValues, MyLogger $logger): void {
        $desec = new Domains();
        $utils = new DomainUtils();
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

            try {
                $summary = $utils->syncDomain($domainId);
                $this->markSyncResult($domain, true);
                $logger->log(
                    "debug",
                    "[ event-listener ] Synced {$oldDomainName} in deSEC: " . json_encode($summary, true)
                );
            } catch (Exception $e) {
                $this->markSyncResult($domain, false);
                $logger->log(
                    "error",
                    "[ event-listener ] Sync error for {$oldDomainName}: " . $e->getMessage()
                );
            }

            return;
        }

        // Domain rename
        $logger->log(
            "debug",
            "[ event-listener ] Domain renamed {$oldDomainName} => {$newDomainName} (ID={$domainId})"
        );

        try {
            $desec->addDomain($newDomainName);
            $utils->syncDomain($domainId);

            $retentionEnabled =
                pm_Settings::get(Settings::DOMAIN_RETENTION->value, "false") === "true";

            if (!$retentionEnabled && $desec->getDomain($oldDomainName)) {
                $desec->deleteDomain($oldDomainName);
            }

            $this->markSyncResult($domain, true);
        } catch (Exception $e) {
            $this->markSyncResult($domain, false);
            $logger->log(
                "error",
                "[ event-listener ] Rename/sync error for {$oldDomainName}: " . $e->getMessage()
            );
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

        $logger->log("debug", "[ event-listener ] Domain {$domainName} deleted");

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


    private function markSyncResult(pm_Domain $domain, bool $success): void
    {
        $domain->setSetting(
            Settings::LAST_SYNC_STATUS->value,
            $success ? 'SUCCESS(auto-sync)' : 'FAILED(auto-sync)'
        );

        $domain->setSetting(
            Settings::LAST_SYNC_ATTEMPT->value,
            (new DateTime())->format('Y-m-d H:i:s T')
        );
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



    /**
     * @throws pm_Exception
     */
}

return new Modules_LsDesecDns_EventListener();