<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Config;

use Bareapi\Config\MetastoreConfig;
use PHPUnit\Framework\TestCase;

final class MetastoreConfigTest extends TestCase
{
    public function testConstructorStoresAllConfigurationValues(): void
    {
        $config = new MetastoreConfig(
            apiKey: 'test-key',
            debugLog: true,
            defaultPageSize: 50,
            maxPageSize: 500,
            defaultBranch: 'develop'
        );

        $this->assertSame('test-key', $config->getApiKey());
        $this->assertTrue($config->isDebugLogEnabled());
        $this->assertSame(50, $config->getDefaultPageSize());
        $this->assertSame(500, $config->getMaxPageSize());
        $this->assertSame('develop', $config->getDefaultBranch());
    }

    public function testGetApiKeyReturnsApiKey(): void
    {
        $config = new MetastoreConfig(apiKey: 'my-secret-key');

        $this->assertSame('my-secret-key', $config->getApiKey());
    }

    public function testIsDebugLogEnabledReturnsDebugLogSetting(): void
    {
        $enabledConfig = new MetastoreConfig(apiKey: 'key', debugLog: true);
        $disabledConfig = new MetastoreConfig(apiKey: 'key', debugLog: false);

        $this->assertTrue($enabledConfig->isDebugLogEnabled());
        $this->assertFalse($disabledConfig->isDebugLogEnabled());
    }

    public function testGetDefaultPageSizeReturnsDefaultPageSize(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultPageSize: 25);

        $this->assertSame(25, $config->getDefaultPageSize());
    }

    public function testGetMaxPageSizeReturnsMaxPageSize(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', maxPageSize: 2000);

        $this->assertSame(2000, $config->getMaxPageSize());
    }

    public function testGetDefaultBranchReturnsDefaultBranch(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultBranch: 'production');

        $this->assertSame('production', $config->getDefaultBranch());
    }

    public function testGetEffectivePageSizeReturnsDefaultWhenRequestedIsZero(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultPageSize: 100, maxPageSize: 1000);

        $this->assertSame(100, $config->getEffectivePageSize(0));
    }

    public function testGetEffectivePageSizeReturnsDefaultWhenRequestedIsNegative(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultPageSize: 100, maxPageSize: 1000);

        $this->assertSame(100, $config->getEffectivePageSize(-5));
    }

    public function testGetEffectivePageSizeReturnsRequestedWhenUnderMax(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultPageSize: 100, maxPageSize: 1000);

        $this->assertSame(500, $config->getEffectivePageSize(500));
    }

    public function testGetEffectivePageSizeReturnsMaxWhenRequestedExceedsMax(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultPageSize: 100, maxPageSize: 1000);

        $this->assertSame(1000, $config->getEffectivePageSize(2000));
    }

    public function testGetEffectivePageSizeReturnsMaxWhenRequestedEqualsMax(): void
    {
        $config = new MetastoreConfig(apiKey: 'key', defaultPageSize: 100, maxPageSize: 1000);

        $this->assertSame(1000, $config->getEffectivePageSize(1000));
    }

    public function testConstructorUsesDefaultValues(): void
    {
        $config = new MetastoreConfig(apiKey: 'key');

        $this->assertFalse($config->isDebugLogEnabled());
        $this->assertSame(100, $config->getDefaultPageSize());
        $this->assertSame(1000, $config->getMaxPageSize());
        $this->assertSame('main', $config->getDefaultBranch());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFromEnvironmentReadsFromEnvWithDefaults(): void
    {
        $_ENV = [];

        $config = MetastoreConfig::fromEnvironment();

        $this->assertSame('default-api-key-change-me', $config->getApiKey());
        $this->assertFalse($config->isDebugLogEnabled());
        $this->assertSame(100, $config->getDefaultPageSize());
        $this->assertSame(1000, $config->getMaxPageSize());
        $this->assertSame('main', $config->getDefaultBranch());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFromEnvironmentReadsCustomValues(): void
    {
        $_ENV['API_KEY'] = 'custom-api-key';
        $_ENV['METASTORE_DEBUG_LOG'] = 'true';
        $_ENV['METASTORE_DEFAULT_PAGE_SIZE'] = '50';
        $_ENV['METASTORE_MAX_PAGE_SIZE'] = '500';
        $_ENV['METASTORE_DEFAULT_BRANCH'] = 'staging';

        $config = MetastoreConfig::fromEnvironment();

        $this->assertSame('custom-api-key', $config->getApiKey());
        $this->assertTrue($config->isDebugLogEnabled());
        $this->assertSame(50, $config->getDefaultPageSize());
        $this->assertSame(500, $config->getMaxPageSize());
        $this->assertSame('staging', $config->getDefaultBranch());
    }
}
