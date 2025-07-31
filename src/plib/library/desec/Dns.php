<?php

namespace desec;

use Exception;
use library\utils\Settings;
use pm_Config;
use pm_Settings;

class Dns
{
    private $logger;
    private mixed $token;
    private $API_BASE_URL = "https://desec.io/api/v1/";

    private function getLogger()
    {
        if (!$this->logger) {
            $this->logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $this->logger;
    }

    public function __construct() {
        if(pm_Config::get("DESEC_API_TOKEN") !== "") {
            $this->token = pm_Config::get("DESEC_API_TOKEN");
        } else {
            $this->token = pm_Settings::get(Settings::DESEC_TOKEN->value);
        }
    }
    public function pushRRsetDesec($domainName, $payload, $method = 'POST', $maxRetries = 5) {
        $url = $this->API_BASE_URL . "domains/" . $domainName . "/rrsets/";
        $attempt = 0;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token {$this->token}",
                    "Content-Type: application/json"
                ],
                CURLOPT_POSTFIELDS  => json_encode($payload)
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            curl_close($curl);

            // Getting the headers and the body - deSEC response
            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            //Case 1: Everything works as expected
            if ($httpCode !== 429 && $httpCode < 400) {
                return ["code" => $httpCode, "response" => json_decode($body)];

                //Case 2: HTTP code 429 occurs, therefore I will have to look for the "Retry-After" header
            } else if ($httpCode == 429) {
                if (preg_match('/Retry-After:\s*(\d+)/i', $headerText, $matches)) {
                    $retryAfter = (int)$matches[1];
                } else {
                    $retryAfter = 5; // fallback delay
                }

                $this->getLogger()->debug("Requests/sec limit exceeded. Waiting " . $retryAfter . " seconds and then will try again!");
                sleep($retryAfter);

                //Case 3: Other unhandled situations
            } else {
                $errorData = json_decode($body, true);
                $errorMessage = is_array($errorData) ? reset($errorData) : 'Unknown error';
                throw new Exception("Error retrieving domains from deSEC! HTTP {$httpCode}: {$errorMessage}");
            }
        }

        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");
    }

    public function getRRSets($domainName, $maxRetries = 5)
    {
        $desecArray = array();
        $url = $this->API_BASE_URL . "domains/" . $domainName . "/rrsets/";

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token {$this->token}",
                    "Content-Type: application/json"
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            curl_close($curl);

            // Getting the headers and the body - deSEC response
            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            //Case 1: Everything works as expected
            if ($httpCode !== 429 && $httpCode < 400) {

                $desecArray = [];
                $decodedResponse = json_decode($body, true);
                if (!empty($decodedResponse)) {

                    foreach ($decodedResponse as $response) {
                        if (isset($response['type'], $response['subname'], $response['ttl'], $response['records']) &&
                            $response['type'] !== 'NS'
                        ) {
                            $desecArray[] = [
                                'subname' => $response['subname'],
                                'type' => $response['type'],
                                'ttl' => (int)$response['ttl'],
                                'records' => $response['records'],
                            ];
                        }
                    }

                    usort($desecArray, fn($a, $b) => strcmp($a['type'], $b['type']));

                }

                return ["code" => $httpCode, "response" => $desecArray];

            //Case 2: HTTP code 429 occurs, therefore I will have to look for the "Retry-After" header
            } else if ($httpCode == 429) {
                if (preg_match('/Retry-After:\s*(\d+)/i', $headerText, $matches)) {
                    $retryAfter = (int)$matches[1];
                } else {
                    $retryAfter = 5; // fallback delay
                }

                $this->getLogger()->debug("Requests/sec limit exceeded. Waiting " . $retryAfter . " seconds and then will try again!");
                sleep($retryAfter);

            //Case 3: Other unhandled situations
            } else {
                $errorData = json_decode($body, true);
                $errorMessage = is_array($errorData) ? reset($errorData) : 'Unknown error';
                throw new Exception("Error retrieving domains from deSEC! HTTP {$httpCode}: {$errorMessage}");
            }

        }

        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");

    }

    public function getSpecificRRset($domainName, $subname, $type, $maxRetries = 5) {
        if($subname == '') {
            $subname = '@';
        }
        $url = $this->API_BASE_URL . "domains/". $domainName . "/rrsets/" . $subname . "/" . $type . "/";

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token $this->token",
                    "Content-Type: application/json"
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            curl_close($curl);

            // Getting the headers and the body - deSEC response
            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            //Case 1: Everything works as expected
            if ($httpCode !== 429 && $httpCode < 400) {
                return ["code" => $httpCode, "response" => json_decode($body)];

                //Case 2: HTTP code 429 occurs, therefore I will have to look for the "Retry-After" header
            } else if ($httpCode == 429) {
                if (preg_match('/Retry-After:\s*(\d+)/i', $headerText, $matches)) {
                    $retryAfter = (int)$matches[1];
                } else {
                    $retryAfter = 5; // fallback delay
                }

                $this->getLogger()->debug("Requests/sec limit exceeded. Waiting " . $retryAfter . " seconds and then will try again!");
                sleep($retryAfter);

                //Case 3: Other unhandled situations
            } else {
                $errorData = json_decode($body, true);
                $errorMessage = is_array($errorData) ? reset($errorData) : 'Unknown error';
                throw new Exception("Error retrieving domains from deSEC! HTTP {$httpCode}: {$errorMessage}");
            }

        }

        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");

    }
}