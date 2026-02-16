<?php

namespace PleskExt\Utils;


enum Settings: string
{
    case LAST_SYNC_ATTEMPT = "last-sync-attempt";
    case LAST_SYNC_STATUS = "last-sync-status";
    case DESEC_STATUS = "desec-status";
    case AUTO_SYNC_STATUS = "auto-sync-status";
    case DESEC_TOKEN = "desec_token";
    case DOMAIN_RETENTION = "domain_retention";
    case EULA_DECISION = "eula-decision";
}