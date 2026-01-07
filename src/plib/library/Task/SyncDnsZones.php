<?php

use PleskExt\Utils\Settings;
use PleskExt\Utils\DomainUtils;
use PleskExt\Utils\MyLogger;

class
Modules_LsDesecDns_Task_SyncDnsZones extends pm_LongTask_Task
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
        $domainName = $this->getParam('domainName');

        return match ($status) {
            static::STATUS_RUNNING => 'Syncing DNS zone of ' . $domainName . '...' . PHP_EOL,
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

        return "DNS zone sync completed successfully ({$totalDomains} domain(s))";
    }

    private function formatErrorMessage(array $summary): string
    {
        $processedCount = count($summary);

        if ($processedCount === 0) {
            return 'DNS zone sync failed (no domains processed).';
        }

        $failed = [];
        $succeededCount = 0;

        foreach ($summary as $domainId => $result) {
            if (!empty($result['error'])) {
                $failed[] = (string)$domainId;
            } else {
                $succeededCount++;
            }
        }

        $failCount = count($failed);

        $failedLabels = array_map(function (string $id) {
            try {
                $label = (string)$this->resolveDomainLabel($id);
                return $label !== '' ? $label : $id;
            } catch (\Throwable $e) {
                return $id;
            }
        }, $failed);

        $failedPart = $failCount === 0
            ? 'no failures'
            : implode(', ', $failedLabels);

        $domainWord = $processedCount === 1 ? 'domain' : 'domains';

        return sprintf(
            'DNS zone sync failed (processed %d %s — %d %s, %d %s: %s)',
            $processedCount,
            $domainWord,
            $succeededCount,
            'succeeded',
            $failCount,
            'failed',
            $failedPart
        );
    }

    public function getOutputSummary(): array
    {
        $summary = (array) $this->getParam('summary');
        if (empty($summary)) {
            return [];
        }

        $lines = [];
        foreach ($summary as $domainId => $result) {
            $domainLabel = $this->resolveDomainLabel((string)$domainId);

            if (isset($result['error'])) {
                $lines[] = "✘ {$domainLabel}: Failed - " . $result['error']['message'];
            } else {
                $changes = [];
                if (!empty($result['missing'])) $changes[] = count($result['missing']) . " added";
                if (!empty($result['modified'])) $changes[] = count($result['modified']) . " updated";
                if (!empty($result['deleted'])) $changes[] = count($result['deleted']) . " removed";

                $changeStr = empty($changes) ? "no changes" : implode(', ', $changes);
                $lines[] = "✔ {$domainLabel}: Success ({$changeStr})";
            }
        }

        return $lines;
    }

    private function resolveDomainLabel(string $domainId): string
    {
        try {
            $d = pm_Domain::getByDomainId((int)$domainId);
            if ($d) {
                $name = $d->getName();
                if (!empty($name)) {
                    return "{$name} ({$domainId})";
                }
            }
        } catch (Exception $e) {
            // ignore - fall through to id-only label
        }

        return $domainId;
    }


    public function run(): void
    {
        /** @var array<int> $ids */
        $ids = (array)$this->getParam('ids');
        $summary = [];
        $additionalData = [];
        $count = count($ids);
        $i = 0;

        $domainUtils = new DomainUtils();
        $myLogger = new MyLogger();

        $manager = new pm_LongTask_Manager();
        $tasks = $manager->getTasks([$this->getId()]);
        $currentTaskId = $this->getInstanceId();

        $myLogger->log('debug', 'Current task id: ' . $currentTaskId);

        foreach ($tasks as $task) {
            $myLogger->log('info', 'Task uid: ' . $task->getInstanceId() . " status: " . $task->getStatus());

            if ($task->getStatus() === "running" && $task !== $this && $task->getInstanceId() !== $currentTaskId) {
                throw new Exception("DNS Sync already running!");
            }
        }

        foreach ($ids as $domainId) {
            try {

                $this->setParam('domainName', pm_Domain::getByDomainId($domainId)->getName());

                $result = $domainUtils->syncDomain($domainId);
                $summary[$domainId] = $result;
                $additionalData[$domainId] = ['status' => 'SUCCESS', 'timestamp' => $result['timestamp']];

                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_STATUS->value, 'SUCCESS');
                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $result['timestamp']);

            } catch (Exception $e) {
                $timestamp = new DateTime()->format('Y-m-d H:i:s T');

                // Persist failure in the in-memory summary
                $summary[$domainId] = [
                    'error' => [
                        'message' => $e->getMessage(),
                        'timestamp' => $timestamp,
                    ],
                ];
                $additionalData[$domainId] = ['status' => 'SUCCESS', 'timestamp' => $result['timestamp']];

                // Persist domain settings for this failed domain
                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_STATUS->value, 'FAILED');
                pm_Domain::getByDomainId($domainId)->setSetting(Settings::LAST_SYNC_ATTEMPT->value, $timestamp);

                $this->setParam('summary', $summary);
                $myLogger->log('error', "Error syncing $domainId: " . $e->getMessage());

                // Rethrow
                throw new Exception($e->getMessage(), 0, $e);
            }

            $i++;

            if ($this->trackProgress && $count > 0) {
                $this->updateProgress((int)floor($i * 100 / $count));
            }
        }

        $this->setParam('summary', $summary);
        $this->setParam('output', $this->getOutputSummary());
        $this->setParam('additionalData', $additionalData);

    }
    public function onStart()
    {

    }

    public function onDone(): void
    {

    }

    /**
     * @throws pm_Exception
     */
    public function onError(Exception $e): void
    {
        $summary = (array) $this->getParam('summary');
        $this->setParam('summary', $summary);

        // Log the outer exception
        $myLogger = new MyLogger();
        $myLogger->log('error', "Task '{$this->getId()}' failed: " . $e->getMessage() . " Summary: " . json_encode($summary, true) . PHP_EOL);
    }

}