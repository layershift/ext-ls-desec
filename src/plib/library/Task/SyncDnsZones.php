<?php

use PleskExt\Utils\Settings;

class Modules_LsDesecDns_Task_SyncDnsZones extends pm_LongTask_Task
{
    public $trackProgress = true;
    public $hidden = false;

    public function getId() {
        return 'task_sync_dns_zones';
    }

    public function statusMessage(): string
    {
        return match ($this->getStatus()) {
            static::STATUS_RUNNING => 'Syncing DNS Zones',
            static::STATUS_DONE => 'DNS zone sync completed',
            static::STATUS_ERROR => 'DNS zone sync failed',
            default => '',
        };
    }

    public function run()
    {
        /** @var array<int> $ids */
        $ids = (array) $this->getParam('ids');
        $summary = [];
        $count   = count($ids);
        $i       = 0;

        // Services injected via params to keep the task decoupled from controllers.
        /** @var callable(int $id): array $syncFn */
        $syncFn = $this->getParam('syncFn'); // see controller below
        if (!is_callable($syncFn)) {
            throw new Exception('syncFn not provided to long task');
        }

        foreach ($ids as $domainId) {
            try {
                $result = $syncFn($domainId);
                $summary[$domainId] = $result;

                $ts = $result['timestamp'] ?? new DateTime()->format('Y-m-d H:i:s T');

                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_STATUS->value, 'SUCCESS');
                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $ts);

            } catch (Exception $e) {
                // Persist failure per-domain but keep the task going for the rest
                $timestamp = new DateTime()->format('Y-m-d H:i:s T');

                $summary[$domainId] = [
                    'error' => [
                        'message'  => $e->getMessage(),
                        'domainId' => $domainId,
                        'timestamp'=> $timestamp,
                    ],
                ];

                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_STATUS->value, 'FAILED');
                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $timestamp);
            }

            $i++;
            if ($this->trackProgress && $count > 0) {
                $this->updateProgress((int) floor($i * 100 / $count));
            }

            // Keep latest partial results in task params for status polling
            $this->setParam('summary', $summary);
        }
    }

    public function onDone() {
        $summary = (array) $this->getParam('summary');
        $this->setParam('summary', $summary);
    }

    public function onError(Exception $e) {
        $summary = (array) $this->getParam('summary');
        $this->setParam('summary', $summary);
    }
}