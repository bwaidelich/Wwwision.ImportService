<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Wwwision\ImportService\DataTarget\DataTargetFactoryInterface;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\OptionsSchema;

/**
 * Factory for the {@see DbalTarget}
 */
#[Flow\Scope('singleton')]
final class DbalTargetFactory implements DataTargetFactoryInterface
{
    #[Flow\InjectConfiguration(path: 'persistence.backendOptions', package: 'Neos.Flow')]
    protected array $flowBackendOptions;

    public function create(Mapper $mapper, array $options): DbalTarget
    {
        return new DbalTarget(
            mapper: $mapper,
            dbal: $this->getDatabaseConnection($options['backendOptions'] ?? null),
            tableName: $options['table'],
            idColumn: $options['idColumn'] ?? 'id',
            versionColumn: $options['versionColumn'] ?? null,
        );
    }

    public function optionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('table', 'string')
            ->has('idColumn', 'string')
            ->has('versionColumn', 'string')
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
