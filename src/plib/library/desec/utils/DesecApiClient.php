<?php

namespace PleskExt\Desec\Utils;

use Exception;
use PleskExt\Utils\MyLogger;

final class DesecApiClient
{
    private const BASE_URL = 'https://desec.io/api/v1/';
    private MyLogger $myLogger;
    private string $token;

    public function __construct(?string $token = null) {
        $this->token = $token ?? TokenProvider::getToken();
        $this->myLogger = new MyLogger();
    }

    /**
     * @param string $method GET|POST|PATCH|PUT|DELETE
     * @param string $path
     * @param array  $options [
     *   'json' => mixed payload to json_encode,
     *   'headers' => [ 'Header: value' ],
     *   'maxRetries' => int,
     *   'accept404' => bool (treat 404 as success for reads)
     * ]
     * @return array [ 'code'=>int, 'headers'=>string, 'body'=>string, 'json'=>mixed|null ]
     * @throws Exception
     */

    public function request(string $method, string $path, array $options = []): array
    {
        $maxRetries = $options['maxRetries'] ?? 5;
        $accept404  = (bool)($options['accept404'] ?? false);
        $payload    = array_key_exists('json', $options) ? json_encode($options['json']) : null;

        $headers = array_merge([
            "Authorization: Token {$this->token}",
            "Content-Type: application/json",
        ], $options['headers'] ?? []);

        $url = rtrim(self::BASE_URL, '/') . '/' . ltrim($path, '/');

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => strtoupper($method),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER         => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 30,
            ]);

            $response = curl_exec($curl);

            if ($response === false) {
                $err = curl_error($curl);
                $no  = curl_errno($curl);
                curl_close($curl);
                throw new Exception("cURL error: [{$no}] {$err}");
            }

            $httpCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            curl_close($curl);

            $headerText = substr($response, 0, $headerSize);
            $body       = substr($response, $headerSize);
            $decoded    = $this->tryJsonDecode($body);

            // Success cases
            $this->myLogger->log('debug', "Debug message: " . PHP_EOL . $headerText . PHP_EOL . "Body: " . $body . PHP_EOL);
            if ($httpCode < 400 || ($accept404 && $httpCode === 404)) {
                return [
                    'code'    => $httpCode,
                    'headers' => $headerText,
                    'body'    => $body,
                    'json'    => $decoded,
                ];
            }

            // Rate limit handling
            if ($httpCode === 429) {
                $retryAfter = $this->extractRetryAfter($headerText) ?? 5;
                // small cushion for concurrency bursts
                $retryAfterFloat = $retryAfter + 0.5;
                $this->myLogger->log('debug', "deSEC 429 rate limit. Retrying in {$retryAfterFloat}s");
                usleep((int)round($retryAfterFloat * 1_000_000));
                continue;
            }

            // Auth invalidation shortcut for the Account use-case
            if ($httpCode === 401) {
                return [
                    'code'    => $httpCode,
                    'headers' => $headerText,
                    'body'    => $body,
                    'json'    => $decoded,
                ];
            }

            // Other errors
            $message = $this->firstErrorMessage($decoded) ?? 'Unknown error or invalid JSON';
            throw new Exception("deSEC error {$httpCode}: {$message}");
        }

        throw new Exception("Rate limit hit. Max retries ({$maxRetries}) exceeded.");
    }

    private function tryJsonDecode(string $body)
    {
        $decoded = json_decode($body, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    private function extractRetryAfter(string $headers): ?int
    {
        if (preg_match('/Retry-After:\s*(\d+)/i', $headers, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function firstErrorMessage(?array $decoded): ?string
    {
        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        // deSEC usually sends {"field": ["detail", ...]} or {"detail": "message"}
        $first = array_values($decoded)[0];
        if (is_array($first)) {
            $nestedFirst = array_values($first)[0] ?? null;
            return is_string($nestedFirst) ? $nestedFirst : json_encode($nestedFirst);
        }
        return is_string($first) ? $first : json_encode($first);
    }
}