<?php

use PleskExt\Desec\Domains;
use PleskExt\Utils\MyLogger;
use PleskExt\Utils\Settings;
use PleskExt\Utils\Status;

class Modules_LsDesecDns_Task_RegisterDomains extends pm_LongTask_Task
{
    public $trackProgress = true;
    public $hidden = false;

    public function getId()
    {
        return 'task_registerdomains';
    }

    public function statusMessage(): string
    {
        $status = $this->getStatus();
        $summary = (array) $this->getParam('summary');
        $domainName = $this->getParam('domainName');

        return match ($status) {
            static::STATUS_RUNNING => 'Registering domains...' . $domainName,
            static::STATUS_DONE => $this->formatDoneMessage($summary),
            static::STATUS_ERROR => $this->formatErrorMessage($summary),
            default => '',
        };
    }

    private function formatDoneMessage(array $summary): string
    {
        if (empty($summary)) {
            return 'No domains were processed.';
        }

        $total = count($summary);
        return "Domain registration completed successfully ({$total} domain(s)).";
    }

    private function formatErrorMessage(array $summary): string
    {
        $processed = count($summary);
        if ($processed === 0) {
            return 'Domain registration failed (no domains processed).';
        }

        $failed = [];
        $succeeded = 0;
        foreach ($summary as $domainId => $result) {
            if (!empty($result['error'])) {
                $failed[] = (string)$domainId;
            } else {
                $succeeded++;
            }
        }

        $failCount = count($failed);

        // Resolve all failed domain labels (no limit, since task stops on first failure)
        $failedLabels = array_map(
            fn(string $id) => $this->resolveDomainLabel($id),
            $failed
        );
        $failedPart = implode(', ', $failedLabels);

        return sprintf(
            'Domain registration failed (processed %d domain(s) — %d succeeded, %d failed: %s).',
            $processed,
            $succeeded,
            $failCount,
            $failedPart === '' ? 'none' : $failedPart
        );
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
        } catch (Throwable $e) {
            // ignore and fall back to id
        }
        return $domainId;
    }

    public function run()
    {
        $myLogger = new MyLogger();
        $ids = (array)$this->getParam('ids');
        $count = count($ids);
        $i = 0;

        $desecDomains = new Domains();
        $summary = [];
        $successfulIds = [];

        foreach ($ids as $domainId) {
            $i++;

            try {
                $domainObj = pm_Domain::getByDomainId((int)$domainId);
                $domainName = $domainObj->getName();

                $this->setParam('domainName', $domainName);

                $myLogger->log('info', "Registering domain with deSEC: {$domainName} (id: {$domainId})");
                $result = $desecDomains->addDomain($domainName);

                // Record success in summary
                $summary[$domainId] = $result;
                $successfulIds[] = (int)$domainId;

                // Persist progress & summary
                if ($this->trackProgress && $count > 0) {
                    $this->updateProgress((int) floor($i * 100 / $count));
                }

                pm_Domain::getByDomainId($domainId)->setSetting(Settings::DESEC_STATUS->value, Status::STATUS_REGISTERED->value);
                $this->setParam('summary', $summary);
                $this->setParam('successful_ids', $successfulIds);

            } catch (Exception $e) {
                pm_Domain::getByDomainId($domainId)->setSetting(Settings::DESEC_STATUS->value, Status::STATUS_ERROR->value);;

                $summary[$domainId] = [
                    'error' => [
                        'message'   => $e->getMessage(),
                        'domain'    => $domainName ?? null,
                    ],
                ];

                $this->setParam('summary', $summary);
                $this->setParam('successful_ids', $successfulIds);

                // Rethrow wrapped exception to fail the long task (fail-fast behavior)
                throw new Exception($e->getMessage(), 0, $e);
            }
        }

        // Final persistence of summary and clear current domainName
        $this->setParam('summary', $summary);
        $this->setParam('domainName', null);
        $this->setParam('successful_ids', $successfulIds);
    }

    public function onDone()
    {
        $myLogger = new MyLogger();
        $summary = (array)$this->getParam('summary');
        $successfulIds = (array)$this->getParam('successful_ids');

        $myLogger->log('info', "Registering domain task '{$this->getId()}' finished successfully. Summary: " . json_encode($summary));
        if(!empty($successfulIds)) {
            try {

                $syncTask = new Modules_LsDesecDns_Task_SyncDnsZones();
                $syncTask->setParam('ids', $successfulIds);

                $manager = new pm_LongTask_Manager();
                $manager->start($syncTask);

                $myLogger->log('info', sprintf(
                    "Started SyncDnsZones task for registered domains: %s (task: %s)",
                    implode(', ', $successfulIds),
                    $syncTask->getInstanceId()
                ));
            } catch (Exception $e) {
                // Log but don't rethrow — onDone must not throw
                $myLogger->log('error', "Failed to start SyncDnsZones after registration: " . $e->getMessage());
            }
        }
    }

    public function onError(Exception $e)
    {
        $myLogger = new MyLogger();
        $summary = (array)$this->getParam('summary');

        $myLogger->log('error', "Task '{$this->getId()}' failed: " . $e->getMessage() . ' Summary: ' . json_encode($summary));
    }
}
