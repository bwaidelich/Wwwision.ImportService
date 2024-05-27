<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataRecords;
use Wwwision\ImportService\ValueObject\DataVersion;
use Wwwision\ImportService\ValueObject\LazyLoadingDataRecord;

/**
 * DBAL Data Source that allows to import records from a database table
 */
#[Flow\Proxy(false)]
final class DbalSource implements DataSourceInterface
{
    public function __construct(
        private readonly Connection $dbal,
        private readonly string $tableName,
        private readonly string $idColumn,
        private readonly string|null $versionColumn,
        private readonly bool $lazyLoading,
    ) {
    }

    public function setup(): Result
    {
        $result = new Result();
        if ($this->dbal->getSchemaManager() === null) {
            $result->addError(new Error('Failed to retrieve DBAL schema manager'));
            return $result;
        }
        try {
            if ($this->dbal->getSchemaManager()->tablesExist([$this->tableName])) {
                $result->addNotice(new Notice('Source table "%s" exists', null, [$this->tableName]));
            } else {
                $result->addError(new Error('Source table "%s" does not exist', null, [$this->tableName]));
            }
        } catch (DBALException $exception) {
            $result->addError(new Error('Failed to connect to source database: %s', $exception->getCode(), [$exception->getMessage()]));
        }
        return $result;
    }

    public function load(): DataRecords
    {
        if ($this->lazyLoading) {
            return $this->loadLazily();
        }

        $rows = $this->dbal->fetchAllAssociative('SELECT * FROM ' . $this->dbal->quoteIdentifier($this->tableName));
        return DataRecords::fromRawArray($rows, $this->idColumn, $this->versionColumn);
    }

    private function loadLazily(): DataRecords
    {
        $columnNames = [$this->dbal->quoteIdentifier($this->idColumn)];
        if ($this->versionColumn !== null) {
            $columnNames[] = $this->dbal->quoteIdentifier($this->versionColumn);
        }
        $rows = $this->dbal->fetchAllAssociative('SELECT ' . implode(', ', $columnNames) . ' FROM ' . $this->dbal->quoteIdentifier($this->tableName));
        return DataRecords::fromRecords(array_map(function(array $row) {
            $id = DataId::fromString($row[$this->idColumn]);
            $closure = function() use ($id) {
                return $this->dbal->fetchAllAssociative('SELECT * FROM ' . $this->dbal->quoteIdentifier($this->tableName) . ' WHERE ' . $this->dbal->quoteIdentifier($this->idColumn) . ' = :id', ['id' => $id->value]);
            };
            if ($this->versionColumn !== null) {
                $version = DataVersion::parse($row[$this->versionColumn]);
                return LazyLoadingDataRecord::fromIdClosureAndVersion($id, $closure, $version);
            }
            return LazyLoadingDataRecord::fromIdAndClosure($id, $closure);

        }, $rows));
    }
}
