<?php

namespace desec;

use Exception;
use library\utils\Settings;
use pm_Bootstrap;
use pm_Config;
use pm_Domain;
use pm_Settings;
use Psr\Log\LoggerInterface;

class Domains
{
    private mixed $token;
    private $logger;
    private $API_BASE_URL = "https://desec.io/api/v1/";


    public function __construct()
    {
        if (pm_Config::get("DESEC_API_TOKEN")) {
            $this->token = pm_Config::get("DESEC_API_TOKEN");
        } else {
            $this->token = pm_Settings::get(Settings::DESEC_TOKEN->value);
        }
    }

    private function getLogger()
    {
        if (!$this->logger) {
            $this->logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $this->logger;
    }

    /*
     * This function makes an API call to deSEC and retrieves all the domains registered with it
     * Input:
     *      @var Settings $enum
     *
     * Return:
     *      array => []
     */
    public function getDesecDomains($maxRetries = 5)
    {
        $url = $this->API_BASE_URL . "domains/";

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token {$this->token}",
                    "Content-Type: application/json"
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true
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
                $decodedBody = json_decode($body, true);

                if(is_array($decodedBody)) {
                    $firstValue = array_values($decodedBody)[0];

                    if(is_array($firstValue)) {
                        // Try to extract the error message from the nested array
                        $nestedFirst = array_values($firstValue)[0];
                        $errorMessage = is_string($nestedFirst) ? $nestedFirst : json_encode($nestedFirst);
                    } else {
                        $errorMessage = is_string($firstValue) ? $firstValue : json_encode($firstValue);
                    }
                } else {
                    $errorMessage = 'Unknown error or invalid JSON';
                }

                throw new Exception("Error retrieving domains from deSEC! HTTP {$httpCode}: {$errorMessage}");
            }
        }
        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");
    }

    /*
     * This function is used to register domains in deSEC
     * Input:
     *      @var String domain
     * Return
     *      array => []
     */
    public function addDomain($domain, $maxRetries = 5)
    {
        $postData = json_encode(["name" => $domain]);
        $url = $this->API_BASE_URL . "domains/";
        $attempt = 0;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token $this->token",
//                    "Authorization: Token asd",
                    "Content-Type: application/json"
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true
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
                pm_Domain::getByName($domain)->setSetting(Settings::DESEC_STATUS->value, "Registered");
                pm_Domain::getByName($domain)->setSetting(Settings::AUTO_SYNC_STATUS->value, "true");

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
                $decodedBody = json_decode($body, true);

                if(is_array($decodedBody)) {
                    $firstValue = array_values($decodedBody)[0];

                    if(is_array($firstValue)) {
                        // Try to extract the error message from the nested array
                        $nestedFirst = array_values($firstValue)[0];
                        $errorMessage = is_string($nestedFirst) ? $nestedFirst : json_encode($nestedFirst);
                    } else {
                        $errorMessage = is_string($firstValue) ? $firstValue : json_encode($firstValue);
                    }
                } else {
                    $errorMessage = 'Unknown error or invalid JSON';
                }

                throw new Exception("Error retrieving domains from deSEC! HTTP {$httpCode}: {$errorMessage}");
            }
        }

        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");
    }


    public function deleteDomain($domain, $maxRetries = 5)
    {
        $url = $this->API_BASE_URL . "domains/" . urlencode($domain) . "/";
        $attempt = 0;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "DELETE",
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
                $decodedBody = json_decode($body, true);

                if(is_array($decodedBody)) {
                    $firstValue = array_values($decodedBody)[0];

                    if(is_array($firstValue)) {
                        // Try to extract the error message from the nested array
                        $nestedFirst = array_values($firstValue)[0];
                        $errorMessage = is_string($nestedFirst) ? $nestedFirst : json_encode($nestedFirst);
                    } else {
                        $errorMessage = is_string($firstValue) ? $firstValue : json_encode($firstValue);
                    }
                } else {
                    $errorMessage = 'Unknown error or invalid JSON';
                }
                throw new Exception("Error retrieving domains from deSEC! HTTP {$httpCode}: {$errorMessage}");
            }
        }

        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");
    }
}


