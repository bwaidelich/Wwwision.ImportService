<?php
namespace Wwwision\ImportService\DataSource;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataRecords;
use Wwwision\ImportService\ValueObject\DataVersion;
use Wwwision\ImportService\ValueObject\LazyLoadingDataRecord;

/**
 * DBAL Data Source that allows to import records from a database table
 */
final class DbalSource implements DataSourceInterface
{

    /**
     * @var array|null
     */
    private $customBackendOptions;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="persistence.backendOptions")
     * @var array
     */
    protected $flowBackendOptions;

    /**
     * @var Connection
     */
    private $dbal;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $idColumn;

    /**
     * @var string|null
     */
    private $versionColumn;

    /**
     * @var bool
     */
    private $lazyLoading;

    protected function __construct(array $options)
    {
        $this->tableName = $options['table'];
        $this->idColumn = $options['idColumn'] ?? 'id';
        $this->versionColumn = $options['versionColumn'] ?? null;
        $this->customBackendOptions = $options['backendOptions'] ?? null;
        $this->lazyLoading = $options['lazyLoading'] ?? false;
    }

    public static function getOptionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('table', 'string')
            ->has('idColumn', 'string')
            ->has('versionColumn', 'string')
            ->has('backendOptions', 'array')
            ->has('lazyLoading', 'boolean')
            ->allowAdditionalOptions();
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static($options);
    }

    /**
     * @throws DBALException
     */
    public function initializeObject(): void
    {
        if ($this->customBackendOptions === null) {
            $this->dbal = DriverManager::getConnection($this->flowBackendOptions);
            return;
        }
        if (!\is_array($this->customBackendOptions)) {
            throw new \RuntimeException(sprintf('Option "backendOptions" must resolve to an array, given: %s', \is_object($this->customBackendOptions) ? \get_class($this->customBackendOptions) : \gettype($this->customBackendOptions)), 1563880453);
        }
        $backendOptions = Arrays::arrayMergeRecursiveOverrule($this->flowBackendOptions, $this->customBackendOptions);
        $this->dbal = DriverManager::getConnection($backendOptions);
    }

    public function load(): DataRecords
    {
        if ($this->lazyLoading) {
            return $this->loadLazily();
        }

        $rows = $this->dbal->fetchAll('SELECT * FROM ' . $this->dbal->quoteIdentifier($this->tableName));
        return DataRecords::fromRawArray($rows, $this->idColumn, $this->versionColumn);
    }

    private function loadLazily(): DataRecords
    {
        $columnNames = [$this->dbal->quoteIdentifier($this->idColumn)];
        if ($this->versionColumn !== null) {
            $columnNames[] = $this->dbal->quoteIdentifier($this->versionColumn);
        }
        $rows = $this->dbal->fetchAll('SELECT ' . implode(', ', $columnNames) . ' FROM ' . $this->dbal->quoteIdentifier($this->tableName));
        return DataRecords::fromRecords(array_map(function(array $row) {
            $id = DataId::fromString($row[$this->idColumn]);
            $closure = function() use ($id) {
                return $this->dbal->fetchAssoc('SELECT * FROM ' . $this->dbal->quoteIdentifier($this->tableName) . ' WHERE ' . $this->dbal->quoteIdentifier($this->idColumn) . ' = :id', ['id' => $id->toString()]);
            };
            if ($this->versionColumn !== null) {
                $version = DataVersion::parse($row[$this->versionColumn]);
                return LazyLoadingDataRecord::fromIdClosureAndVersion($id, $closure, $version);
            }
            return LazyLoadingDataRecord::fromIdAndClosure($id, $closure);

        }, $rows));
    }
}
