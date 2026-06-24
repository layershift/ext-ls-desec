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

    public function formatElapsed(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000) . ' ms';
        }

        if ($seconds < 60) {
            return round($seconds, 2) . ' s';
        }

        $minutes = (int) floor($seconds / 60);
        $remaining = $seconds - ($minutes * 60);

        return sprintf('%dm %.1fs', $minutes, $remaining);
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
        $elapsed = $this->formatElapsed((float) $this->getParam('elapsed'));

        if (empty($summary)) {
            return "No domains were deleted (completed in {$elapsed}).";
        }

        $total = count($summary);
        return "Domain was removed from deSEC ({$total} domain(s)) in {$elapsed}.";
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
        $elapsed = $this->formatElapsed((float) $this->getParam('elapsed'));

        return sprintf(
            'Domain deletion failed (processed %d domain(s) — %d succeeded, %d failed: %s) after %s.',
            $processed,
            $succeeded,
            $failCount,
            $failedPart,
            $elapsed
        );
    }

    public function run(): void
    {
        $startTime = microtime(true);

        $myLogger = new MyLogger();
        $desecDomains = new Domains();

        $summary = [];
        $domainNames = (array) $this->getParam('domains');
        $count = count($domainNames);
        $i = 0;

        foreach ($domainNames as $domainName) {
            $i++;

            try {
                $this->setParam('domainName', $domainName);

                # Nothing to do if the domain isn't registered in deSEC
                if (!$desecDomains->getDomain($domainName)) {
                    $myLogger->log('info', "Domain {$domainName} not present in deSEC, skipping delete.");
                    $summary[$domainName] = ['status' => 'Skipped (not registered)'];
                } else {
                    $myLogger->log('info', "Deleting domain from deSEC: {$domainName}");
                    $response = $desecDomains->deleteDomain($domainName);
                    $summary[$domainName] = ['status' => 'Deleted', 'response' => $response];
                }

                if ($this->trackProgress && $count > 0) {
                    $this->updateProgress((int) floor($i * 100 / $count));
                }

                $this->setParam('summary', $summary);

            } catch (Exception $e) {
                $summary[$domainName] = [
                    'error' => [
                        'message' => $e->getMessage(),
                    ],
                ];
                $this->setParam('summary', $summary);
                $myLogger->log('error', "Error deleting {$domainName} from deSEC: " . $e->getMessage());

                $this->setParam('elapsed', microtime(true) - $startTime);
                throw new Exception($e->getMessage(), 0, $e);
            }
        }

        $this->setParam('summary', $summary);
        $this->setParam('elapsed', microtime(true) - $startTime);
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
