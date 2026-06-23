<?php

use PleskExt\Desec\Domains;
use PleskExt\Utils\MyLogger;

class Modules_LsDesecDns_Task_DeleteDomains extends pm_LongTask_Task
{
    public $trackProgress = true;
    public $hidden = false;
    public $poolSize = 1; # Number of concurrent tasks

    public function getId()
    {
        return 'task_deletedomains';
    }

    public function getConcurrencyRules(): array
    {
        return [
            'long_task/task_deletedomains',
        ];
    }

    public function statusMessage(): string
    {
        $status = $this->getStatus();
        $summary = (array) $this->getParam('summary');
        $domainName = $this->getParam('domainName');

        # If the task hasn't actually started deleting yet, domainName is null and
        # we show a queued message instead (mirrors RegisterDomains/SyncDnsZones).
        if (!$domainName) {
            return 'Queued - waiting for another task to finish...';
        }

        return match ($status) {
            static::STATUS_RUNNING => 'Deleting domain from deSEC... ' . $domainName,
            static::STATUS_DONE => $this->formatDoneMessage($summary),
            static::STATUS_ERROR => $this->formatErrorMessage($summary),
            default => '',
        };
    }

    private function formatDoneMessage(array $summary): string
    {
        if (empty($summary)) {
            return 'No domains were deleted.';
        }

        $total = count($summary);
        return "Domain deletion completed successfully ({$total} domain(s)).";
    }

    private function formatErrorMessage(array $summary): string
    {
        $processed = count($summary);
        if ($processed === 0) {
            return 'Domain deletion failed (no domains processed).';
        }

        $failed = [];
        $succeeded = 0;
        foreach ($summary as $domainName => $result) {
            if (!empty($result['error'])) {
                $failed[] = (string) $domainName;
            } else {
                $succeeded++;
            }
        }

        $failCount = count($failed);
        $failedPart = $failCount === 0 ? 'none' : implode(', ', $failed);

        return sprintf(
            'Domain deletion failed (processed %d domain(s) — %d succeeded, %d failed: %s).',
            $processed,
            $succeeded,
            $failCount,
            $failedPart
        );
    }

    public function run(): void
    {
        $myLogger = new MyLogger();
        $desecDomains = new Domains();

        $summary = [];
        $domainName = $this->getParam('domains');

        try {
            $this->setParam('domainName', $domainName);

            # Nothing to do if the domain isn't registered in deSEC.
            if (!$desecDomains->getDomain($domainName)) {
                $myLogger->log('info', "Domain {$domainName} not present in deSEC, skipping delete.");
                $summary[$domainName] = ['status' => 'Skipped (not registered)'];
            } else {
                $myLogger->log('info', "Deleting domain from deSEC: {$domainName}");
                $response = $desecDomains->deleteDomain($domainName);
                $summary[$domainName] = ['status' => 'Deleted', 'response' => $response];
            }

            $this->updateProgress(100);
            $this->setParam('summary', $summary);

        } catch (Exception $e) {
            $summary[$domainName] = [
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ];
            $this->setParam('summary', $summary);

            $myLogger->log('error', "Error deleting {$domainName} from deSEC: " . $e->getMessage());

            // Fail-fast, consistent with the other tasks.
            throw new Exception($e->getMessage(), 0, $e);
        }
        

        $this->setParam('summary', $summary);
    }

    public function onDone()
    {
        $this->handleFinish('info', "Domain deletion task '{$this->getId()}' finished successfully.");
    }

    public function onError(Exception $e)
    {
        $this->handleFinish('error', "Task '{$this->getId()}' failed: " . $e->getMessage());
    }

    private function handleFinish(string $level, string $message): void
    {
        $myLogger = new MyLogger();
        $summary = (array) $this->getParam('summary');
        $myLogger->log($level, $message . ' Summary: ' . json_encode($summary));
    }
}
