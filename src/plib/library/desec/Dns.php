<?php

namespace desec;

use Exception;
use pm_Config;

class Dns
{
    private mixed $token;
    private $API_BASE_URL = "https://desec.io/api/v1/";


    public function __construct() {
        $this->token = pm_Config::get("DESEC_API_TOKEN");
    }
    public function pushRRsetDesec($domainName, $payload, $method = 'POST') {
        $url = $this->API_BASE_URL . "domains/" . $domainName . "/rrsets/";
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method)); // Accepts POST, PATCH, PUT.
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Token $this->token",
            "Content-Type: application/json"
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode($response, true);


        if($httpCode >= 400) {
            $nonEmpty = array_filter($decoded, function ($item) {
                return !empty($item);
            });
            $errorMessage = empty($nonEmpty)
                ? 'No error details available.'
                : json_encode($nonEmpty, JSON_PRETTY_PRINT);

            throw new Exception(
                "Desec API error: " . $errorMessage . " (HTTP {$httpCode})"
            );
        }

        return ["code" => $httpCode, "response" => $decoded === null ? $response : $decoded];
    }

    public function getRRSets($domainName)
    {
        $desecArray = array();

        $curl = curl_init($this->API_BASE_URL . "domains/" . $domainName . "/rrsets/");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Token $this->token",
            "Content-Type: application/json"
        ]);

        $desecResponse = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $decodedResponse = json_decode($desecResponse, true);

        if ($httpCode < 400) {
            if(!empty($decodedResponse)) {
                $desecArray = [];
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

                return ["code" => $httpCode, "response" => $desecArray];
            } else {
                return ["code" => $httpCode, "response" => $decodedResponse];
            }
        } else {
            throw new Exception("Error occurred while retrieving the RRsets! Error(" . $httpCode . "): " . array_values($decodedResponse)[0]);
        }


    }

    public function getSpecificRRset($domainName, $subname, $type) {
        if($subname == '') {
            $subname = '@';
        }

        $curl = curl_init($this->API_BASE_URL . "domains/". $domainName . "/rrsets/" . $subname . "/" . $type . "/");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Token $this->token",
            "Content-Type: application/json"
        ]);

        $desecResponse = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);



        return ["code" => $httpCode, "response" => $desecResponse];
    }
}