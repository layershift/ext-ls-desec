<?php

//namespace library;

use pm_Bootstrap;
use Psr\Log\LoggerInterface;

class Modules_LsDesecDns_EventListener implements EventListener
{
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
        ];
    }

    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {
        $this->getLogger()->debug("[ event-listener ] Domain's DNS zone was updated!");


    }
}

return new Modules_LsDesecDns_EventListener();