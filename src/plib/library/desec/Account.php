<?php

namespace PleskExt\Desec;


##### Plesk Classes Imports #####
use PleskExt\Desec\Utils\DesecApiClient;
use PleskExt\Utils\MyLogger;

##### Plesk
use Exception;
use Psr\Log\LoggerInterface;


class Account
{
    private MyLogger $myLogger;
    private DesecApiClient $client;

    public function __construct()
    {
        $this->myLogger = new MyLogger();
    }

    public function validateTokenFormat(string $token): bool {
        return (bool)preg_match("/^[a-km-zA-HJ-NP-Z1-9]{28}$/", $token);
    }

    /**
     * @throws Exception
     */
    public function validateToken(string $token): array
    {
        $client = new DesecApiClient($token);
        if(!$this->validateTokenFormat($token)) {
            $this->myLogger->log("debug", "The provided token is invalid! Token either doesn't respect the reGEX format or is too long!");
            return [ "token" => "false" ];
        }

        $res = $client->request('GET', 'auth/account/');

        if ($res['code'] === 401) {
            $this->myLogger->log("debug", "The provided token is invalid! Response: " . PHP_EOL . json_encode($res, JSON_PRETTY_PRINT));
            return ["token" => "false"];
        }

        if ($res['code'] < 500) {
            $this->myLogger->log("debug", "The provided token is valid!");
            return ["token" => "true"];
        }

        throw new Exception("Unexpected response validating token. HTTP {$res['code']}");
    }
}