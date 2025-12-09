<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests;

use MethorZ\SwiftDb\Cache\MappingCache;
use MethorZ\SwiftDb\ConfigProvider;
use MethorZ\SwiftDb\Connection\ConnectionManager;
use MethorZ\SwiftDb\Query\QueryLogger;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ConfigProviderTest extends TestCase
{
    public function testInvokeReturnsArrayWithDependencies(): void
    {
        $configProvider = new ConfigProvider();
        $config = $configProvider();

        $this->assertArrayHasKey('dependencies', $config);
    }

    public function testGetDependenciesReturnsFactories(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        $this->assertArrayHasKey('factories', $dependencies);

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $this->assertArrayHasKey(ConnectionManager::class, $factories);
        $this->assertArrayHasKey(QueryLogger::class, $factories);
        $this->assertArrayHasKey(MappingCache::class, $factories);
    }

    public function testConnectionManagerFactoryCreatesInstance(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[ConnectionManager::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([
                'database' => [
                    'connections' => [
                        'default' => [
                            'dsn' => 'mysql:host=localhost;dbname=test',
                            'username' => 'root',
                            'password' => '',
                        ],
                    ],
                ],
            ]);

        $result = $factory($container);

        $this->assertInstanceOf(ConnectionManager::class, $result);
    }

    public function testConnectionManagerFactoryHandlesEmptyConfig(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[ConnectionManager::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([]);

        $result = $factory($container);

        $this->assertInstanceOf(ConnectionManager::class, $result);
    }

    public function testQueryLoggerFactoryCreatesInstanceWithLogger(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[QueryLogger::class];

        $logger = new NullLogger();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(LoggerInterface::class)
            ->willReturn(true);
        $container->method('get')
            ->with(LoggerInterface::class)
            ->willReturn($logger);

        $result = $factory($container);

        $this->assertInstanceOf(QueryLogger::class, $result);
    }

    public function testQueryLoggerFactoryCreatesInstanceWithoutLogger(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[QueryLogger::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(LoggerInterface::class)
            ->willReturn(false);

        $result = $factory($container);

        $this->assertInstanceOf(QueryLogger::class, $result);
    }

    public function testQueryLoggerFactoryHandlesInvalidLogger(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[QueryLogger::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(LoggerInterface::class)
            ->willReturn(true);
        $container->method('get')
            ->with(LoggerInterface::class)
            ->willReturn('not a logger');

        $result = $factory($container);

        $this->assertInstanceOf(QueryLogger::class, $result);
    }

    public function testMappingCacheFactoryCreatesInstanceWithCacheDir(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[MappingCache::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([
                'database' => [
                    'cache_dir' => '/tmp/cache',
                ],
            ]);

        $result = $factory($container);

        $this->assertInstanceOf(MappingCache::class, $result);
    }

    public function testMappingCacheFactoryCreatesInstanceWithoutCacheDir(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[MappingCache::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([]);

        $result = $factory($container);

        $this->assertInstanceOf(MappingCache::class, $result);
    }

    public function testMappingCacheFactoryHandlesNonStringCacheDir(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        /** @var array<string, callable> $factories */
        $factories = $dependencies['factories'];
        $factory = $factories[MappingCache::class];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn([
                'database' => [
                    'cache_dir' => 12345, // Not a string
                ],
            ]);

        $result = $factory($container);

        $this->assertInstanceOf(MappingCache::class, $result);
    }
}
