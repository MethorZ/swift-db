<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb;

use MethorZ\SwiftDb\Cache\MappingCache;
use MethorZ\SwiftDb\Connection\ConnectionManager;
use MethorZ\SwiftDb\Query\QueryLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ConfigProvider for Mezzio/Laminas integration
 */
final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                ConnectionManager::class => static function (ContainerInterface $container): ConnectionManager {
                    /** @var array<string, mixed> $config */
                    $config = $container->get('config');
                    /** @var array<string, mixed> $dbConfig */
                    $dbConfig = $config['database'] ?? [];

                    return new ConnectionManager($dbConfig);
                },
                QueryLogger::class => static function (ContainerInterface $container): QueryLogger {
                    $logger = $container->has(LoggerInterface::class)
                        ? $container->get(LoggerInterface::class)
                        : new NullLogger();

                    if (!$logger instanceof LoggerInterface) {
                        $logger = new NullLogger();
                    }

                    return new QueryLogger($logger);
                },
                MappingCache::class => static function (ContainerInterface $container): MappingCache {
                    /** @var array<string, mixed> $config */
                    $config = $container->get('config');
                    /** @var array<string, mixed> $dbConfig */
                    $dbConfig = $config['database'] ?? [];
                    $cacheDir = $dbConfig['cache_dir'] ?? null;

                    return new MappingCache(is_string($cacheDir) ? $cacheDir : null);
                },
            ],
        ];
    }
}
