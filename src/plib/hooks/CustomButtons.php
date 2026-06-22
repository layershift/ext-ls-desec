<?php

class Modules_LsDesecDns_CustomButtons extends pm_Hook_CustomButtons
{

    public function getButtons()
    {
        return [
            [
                'place'       => self::PLACE_ADMIN_NAVIGATION,
                'title'       => 'deSEC',
                'description' => 'Open deSEC extension',
                'icon'        => "/extras/ls-desec-dns/_meta/icons/32x32_white.png",
                'link'        => pm_Context::getActionUrl('index', 'index'),
            ],
            [
                'place'       => self::PLACE_ADMIN_TOOLS_AND_SETTINGS,
                'title'       => 'deSEC',
                'description' => 'Open deSEC extension',
                'link'        => pm_Context::getActionUrl('index', 'index'),
            ],
        ];
    }
}