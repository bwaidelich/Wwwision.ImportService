<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Wwwision\ImportService\DataSource\DataSourceFactoryInterface;
use Wwwision\ImportService\OptionsSchema;

/**
 * Factory for the {@see DbalSource}
 */
#[Flow\Scope('singleton')]
final class DbalSourceFactory implements DataSourceFactoryInterface
{
    #[Flow\InjectConfiguration(path: 'persistence.backendOptions', package: 'Neos.Flow')]
    protected array $flowBackendOptions;

    public function create(array $options): DbalSource
    {
        return new DbalSource(
            dbal: $this->getDatabaseConnection($options['backendOptions'] ?? null),
            tableName: $options['table'],
            idColumn: $options['idColumn'] ?? 'id',
            versionColumn: $options['versionColumn'] ?? null,
            lazyLoading: $options['lazyLoading'] ?? false,
        );
    }

    public function optionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('table', 'string')
            ->has('idColumn', 'string')
            ->has('versionColumn', 'string')
            ->has('lazyLoading', 'boolean')
            ->has('backendOptions', 'array');
    }

    private function getDatabaseConnection(array|null $customBackendOptions): Connection
    {
        if ($customBackendOptions === null) {
            return DriverManager::getConnection($this->flowBackendOptions);
        }
        return DriverManager::getConnection(Arrays::arrayMergeRecursiveOverrule($this->flowBackendOptions, $customBackendOptions));
    }
}
