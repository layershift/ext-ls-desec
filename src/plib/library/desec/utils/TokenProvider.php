<?php

namespace PleskExt\Desec\Utils;

use PleskExt\Utils\Settings;
use pm_Config;
use pm_Settings;

final class TokenProvider
{
    public static function getToken(): string {
        $token = pm_Config::get('DESEC_TOKEN');

        if($token && $token !== '') {
            return $token;
        }

        return pm_Settings::get(Settings::DESEC_TOKEN->value) ?? "";
    }
}