<?php

declare(strict_types=1);

namespace Bareapi\Controller;

final class ControllerUtil
{
    /**
     * @param array<mixed> $array
     */
    public static function isStringKeyedArray(array $array): bool
    {
        foreach (array_keys($array) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    public static function toStringSafe($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return '';
    }

    /**
     * Cast any array to array<string, mixed> by filtering only string keys.
     * @param mixed $array
     * @return array<string, mixed>
     */
    public static function toStringKeyedArray($array): array
    {
        if (! is_array($array)) {
            return [];
        }
        $result = [];
        foreach ($array as $k => $v) {
            if (is_string($k)) {
                $result[$k] = $v;
            }
        }
        return $result;
    }
}
