<?php

class Modules_LsDesecDns_LongTasks extends pm_Hook_LongTasks
{
    public function getLongTasks(): array
    {
        return [
            new Modules_LsDesecDns_Task_SyncDnsZones(),
            new Modules_LsDesecDns_Task_RegisterDomains()
        ];
    }
}