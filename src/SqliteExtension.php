<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\DatabaseSqlite;

use Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory;
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\TransactionManager;
use InvalidArgumentException;
use LogicException;

final class SqliteExtension implements Extension
{
    /**
     * @param array<string, mixed> $config Configuracao SQLite unica ou mapa de conexoes.
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Registra a factory PDO configurada para SQLite.
     */
    public function register(Application $application): void
    {
        $factory = new PdoConnectionFactory(config: $this->pdoConfig());

        $application->container()->bind(
            DatabaseConnectionFactory::class,
            $factory
        );

        $application->container()->bind(
            PdoDatabase::class,
            static fn (Container $container): PdoDatabase => self::database($container)
        );

        $application->container()->bind(
            TransactionManager::class,
            static fn (Container $container): TransactionManager => self::transactionManager($container)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pdoConfig(): array
    {
        if (!isset($this->config['connections'])) {
            return $this->withDsn($this->config);
        }

        if (!is_array($this->config['connections'])) {
            throw new InvalidArgumentException('A chave connections do SQLite deve ser um array.');
        }

        $connections = [];
        foreach ($this->config['connections'] as $name => $config) {
            if (!is_array($config)) {
                throw new InvalidArgumentException("Configuracao SQLite '$name' deve ser um array.");
            }

            $connections[$name] = $this->withDsn($config);
        }

        return ['connections' => $connections];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function withDsn(array $config): array
    {
        if (($config['memory'] ?? false) === true) {
            $config['dsn'] = 'sqlite::memory:';

            return $config;
        }

        $path = $config['path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new InvalidArgumentException('A configuracao SQLite deve informar path ou memory=true.');
        }

        $config['dsn'] = "sqlite:$path";

        return $config;
    }

    private static function database(Container $container): PdoDatabase
    {
        $factory = $container->get(DatabaseConnectionFactory::class);

        if (!$factory instanceof DatabaseConnectionFactory) {
            throw new LogicException('O servico SQLite deve resolver para DatabaseConnectionFactory.');
        }

        return new PdoDatabase(connection: $factory->connection());
    }

    private static function transactionManager(Container $container): TransactionManager
    {
        $database = $container->get(PdoDatabase::class);

        if (!$database instanceof PdoDatabase) {
            throw new LogicException('O servico SQLite deve resolver para PdoDatabase.');
        }

        return $database;
    }
}
