<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Symfony\Component\HttpFoundation\Request;

final class FilterParser
{
    public function parse(Request $request): FilterQuery
    {
        $filters = [];
        $orderBy = null;
        $orderDirection = 'asc';
        $limit = null;
        $offset = 0;

        foreach ($this->rawPairs($request) as $key => $stringValue) {
            if ($key === 'limit') {
                $limit = $this->positiveInteger($key, $stringValue);
                continue;
            }
            if ($key === 'offset') {
                $offset = $this->positiveInteger($key, $stringValue);
                continue;
            }

            if (str_ends_with($key, '[order]')) {
                $field = substr($key, 0, -7);
                $this->assertField($field);
                if (! in_array($stringValue, ['asc', 'desc'], true)) {
                    throw new \InvalidArgumentException('Invalid order value for ' . $key);
                }
                $orderBy = $field;
                $orderDirection = $stringValue;
                continue;
            }

            $this->assertField($key);
            $filters[$key] = $stringValue;
        }

        return new FilterQuery($filters, $orderBy, $orderDirection, $limit, $offset);
    }

    /**
     * @return array<string, string>
     */
    private function rawPairs(Request $request): array
    {
        $queryString = $request->server->get('QUERY_STRING');
        if (! is_string($queryString) || $queryString === '') {
            return [];
        }

        $pairs = [];
        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $rawKey = $parts[0];
            $rawValue = $parts[1] ?? '';
            $key = urldecode($rawKey);
            $value = urldecode($rawValue);
            $pairs[$key] = trim($value);
        }

        return $pairs;
    }

    private function positiveInteger(string $key, string $value): int
    {
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid ' . $key . ' value');
        }

        return (int) $value;
    }

    private function assertField(string $field): void
    {
        if ($field === '' || preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/', $field) !== 1) {
            throw new \InvalidArgumentException('Invalid filter field ' . $field);
        }
    }
}
