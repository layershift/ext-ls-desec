<?php

namespace desec;

use Exception;
use library\utils\Settings;
use pm_Bootstrap;
use pm_Config;
use pm_Domain;
use Psr\Log\LoggerInterface;

class Domains
{
    private mixed $token;
    private $API_BASE_URL = "https://desec.io/api/v1/";


    public function __construct() {
        $this->token = pm_Config::get("DESEC_API_TOKEN");
    }

    private function getLogger() {
        if (!$this->logger) {
            $logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $logger;
    }
    public function getDomain($domain) {

        $curl = curl_init($this->API_BASE_URL . "domains/" . $domain . "/");
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Token $this->token",
            "Content-Type: application/json"
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ["code" => $httpCode, "response" => json_decode($response, true)];

    }

    public function getDesecDomains() {
        $curl = curl_init($this->API_BASE_URL . "domains/");
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Token $this->token",
            "Content-Type: application/json"
        ]);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ["code" => $httpCode, "response" => $response];
    }

    /**
     *
     */
    public function addDomain($domain) {
        $postData = json_encode(["name" => $domain]);
        $maxAttempts = 2;
        $attempt = 0;

        do {
            $attempt++;
            $curl = curl_init($this->API_BASE_URL . "domains/");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "Authorization: Token $this->token",
                "Content-Type: application/json"
            ]);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $responseData = json_decode($response, true);
            if ($httpCode >= 400 && $httpCode !== 429) {
                throw new Exception("Domain " . $domain  . " failed to register because the following error occurred: " . array_values($responseData)[0]);
            }

            if ($httpCode === 429 && $attempt < $maxAttempts) {
                $this->getLogger()->debug("Requests/sec limit exceeded. Waiting 3600 seconds and then will try again!");
                sleep(3600);
            } else {
                break;
            }
        } while ($attempt < $maxAttempts);

        pm_Domain::getByName($domain)->setSetting(Settings::DESEC_STATUS->value, "Registered");
        pm_Domain::getByName($domain)->setSetting(Settings::AUTO_SYNC_STATUS->value, "true");

        return $responseData;

    }


    public function deleteDomain($name) {
        $url = $this->API_BASE_URL . "domains/" . urlencode($name) . "/";
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => "DELETE",
            CURLOPT_HTTPHEADER     => [
                "Authorization: Token {$this->token}",
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return ["code" => 0, "error" => $error];
        }

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ["code" => $code, "response" => $response];
    }

}