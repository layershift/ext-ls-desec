<?php

namespace library\utils;

use pm_Domain;

enum Settings: string
{
    case LAST_SYNC_ATTEMPT = "last-sync-attempt";
    case LAST_SYNC_STATUS = "last-sync-status";
    case DESEC_STATUS = "desec-status";
    case AUTO_SYNC_STATUS = "auto-sync-status";
    case DOMAIN_RETENTION = "ext_ls_desec_domain_retention";
    case LOG_VERBOSITY = "ext_ls_desec_log_verbosity";
}