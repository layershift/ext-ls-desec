<?php

use PleskExt\Utils\Settings;
use PleskExt\Utils\DomainUtils;
use PleskExt\Utils\MyLogger;

class Modules_LsDesecDns_Task_SyncDnsZones extends pm_LongTask_Task
{
    public $trackProgress = true;
    public $hidden = false;

    public function getId() {
        return 'task_syncdnszones';
    }

    public function statusMessage(): string
    {
        $status = $this->getStatus();
        $summary = (array) $this->getParam('summary');

        return match ($status) {
            static::STATUS_RUNNING => 'Syncing DNS Zones...',
            static::STATUS_DONE => $this->formatDoneMessage($summary),
            static::STATUS_ERROR => $this->formatErrorMessage($summary),
            default => '',
        };
    }

    private function formatDoneMessage(array $summary): string
    {
        if (empty($summary)) {
            return 'DNS zone sync completed (no changes)';
        }

        $totalDomains = count($summary);
        $successCount = 0;
        $failCount = 0;

        foreach ($summary as $result) {
            if (isset($result['error'])) {
                $failCount++;
            } else {
                $successCount++;
            }
        }

        if ($failCount === 0) {
            return "DNS zone sync completed successfully ({$successCount} domain(s))";
        }

        return "DNS zone sync completed with errors ({$successCount} succeeded, {$failCount} failed)";
    }

    private function formatErrorMessage(array $summary): string
    {
        $processedCount = count($summary);

        if ($processedCount === 0) {
            return 'DNS zone sync failed';
        }

        return "DNS zone sync failed (processed {$processedCount} domain(s) before error)";
    }

    public function run()
    {
        /** @var array<int> $ids */
        $ids = (array) $this->getParam('ids');
        $summary = [];
        $count   = count($ids);
        $i       = 0;

        $domainUtils = new DomainUtils();
        $myLogger = new MyLogger();


        foreach ($ids as $domainId) {
            try {
                $result = $domainUtils->syncDomain($domainId);
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

                $myLogger->log('error', "Error syncing $domainId: " . $e->getMessage());
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