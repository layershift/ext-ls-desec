<?php

namespace PleskExt\Utils\Validation;

use Exception;

final class InputSanitizer
{

    public static function readJsonBody(int $maxBytes = 64 * 1024,  bool $assoc = true): array {
        $body = file_get_contents('php://input', false, null, 0, $maxBytes);

        if ($body === false) {
            throw new Exception('Faled to read request body');
        }

        if(strlen($body) > $maxBytes) {
            throw new Exception('Request body too long');
        }

        $decoded = json_decode($body, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new Exception('Invalid JSON payload.');
        }

        return $decoded;

    }

    public static function normalizeBool(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? "true" : "false";
        }

        if (is_int($value)) {
            return $value === 1 ? "true" : "false";
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ["1", "true", "on", "yes", true], true)) {
                return "true";
            }
            if (in_array($v, ["0", "false", "off", "no", false, "", null], true)) {
                return "false";
            }
        }
        throw new Exception('Data type is not valid.');
    }

    public static function validateDomainId(mixed $id): int
    {
        if (is_int($id) && $id > 0) {
            return $id;
        }
        if (is_string($id) && ctype_digit($id) && (int)$id > 0) {
            return (int)$id;
        }
        throw new Exception('Invalid domain id.');
    }


}