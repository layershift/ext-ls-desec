<?php

namespace library\desec;

use Exception;
use pm_Bootstrap;
use Psr\Log\LoggerInterface;

class Account
{

    private $logger;
    private $API_BASE_URL = "https://desec.io/api/v1/";

    private function getLogger()
    {
        if (!$this->logger) {
            $this->logger = pm_Bootstrap::getContainer()->get(LoggerInterface::class);
        }

        return $this->logger;
    }

    public function validateToken($token, $maxRetries = 10) {
        $url = $this->API_BASE_URL . "auth/account/";

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {

            // Settings up curl for what is about to come
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token $token",
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
            if ($httpCode !== 401 && $httpCode < 500) {
                return ["token" => "true"];

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