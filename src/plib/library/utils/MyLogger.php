<?php

namespace PleskExt\Utils;

use PleskExt\Utils\Settings;

use pm_Bootstrap;
use pm_Settings;
use Psr\Log\LoggerInterface;

class MyLogger {
    private $logger;

    public function __construct() {
        $this->logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
    }

    public function log(string $level, string $message): void
    {
        switch($level) {
            case "info":
                $this->logger->info($message);
                break;
            case "debug":
                $this->logger->debug($message);
                break;
            case "error":
                $this->logger->error($message);
                break;
        }
    }

}