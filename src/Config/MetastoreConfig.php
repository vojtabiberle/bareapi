<?php

declare(strict_types=1);

namespace Bareapi\Config;

class MetastoreConfig
{
    public function __construct(
        private string $apiKey,
        private bool $debugLog = false,
        private int $defaultPageSize = 100,
        private int $maxPageSize = 1000,
        private string $defaultBranch = 'main',
    ) {
    }

    public static function fromEnvironment(): self
    {
        /** @var string $apiKey */
        $apiKey = $_ENV['API_KEY'] ?? 'default-api-key-change-me';

        /** @var string|bool $debugLogValue */
        $debugLogValue = $_ENV['METASTORE_DEBUG_LOG'] ?? false;

        /** @var string|int $pageSizeValue */
        $pageSizeValue = $_ENV['METASTORE_DEFAULT_PAGE_SIZE'] ?? 100;

        /** @var string|int $maxPageSizeValue */
        $maxPageSizeValue = $_ENV['METASTORE_MAX_PAGE_SIZE'] ?? 1000;

        /** @var string $defaultBranch */
        $defaultBranch = $_ENV['METASTORE_DEFAULT_BRANCH'] ?? 'main';

        return new self(
            apiKey: $apiKey,
            debugLog: (bool) filter_var($debugLogValue, FILTER_VALIDATE_BOOLEAN),
            defaultPageSize: (int) $pageSizeValue,
            maxPageSize: (int) $maxPageSizeValue,
            defaultBranch: $defaultBranch,
        );
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function isDebugLogEnabled(): bool
    {
        return $this->debugLog;
    }

    public function getDefaultPageSize(): int
    {
        return $this->defaultPageSize;
    }

    public function getMaxPageSize(): int
    {
        return $this->maxPageSize;
    }

    public function getDefaultBranch(): string
    {
        return $this->defaultBranch;
    }

    public function getEffectivePageSize(int $requested): int
    {
        if ($requested <= 0) {
            return $this->defaultPageSize;
        }

        return min($requested, $this->maxPageSize);
    }
}
