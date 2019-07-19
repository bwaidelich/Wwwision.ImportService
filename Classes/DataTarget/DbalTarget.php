<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget;

use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Wwwision\ImportService\ValueObject\DataVersion;

/**
 * DBAL data target
 */
final class DbalTarget implements DataTargetInterface
{

    /**
     * @var array
     */
    private $customBackendOptions;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="persistence.backendOptions")
     * @var array
     */
    protected $flowBackendOptions;

    /**
     * @var Mapper
     */
    private $mapper;

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
     * @var array
     */
    private $localRowsCache;

    /**
     * @var array
     */
    private $localVersionsCache;

    protected function __construct(Mapper $mapper, array $options)
    {
        $this->mapper = $mapper;
        if (!isset($options['table'])) {
            throw new \InvalidArgumentException('Missing option "table"', 1558001987);
        }
        $this->tableName = $options['table'];
        $this->idColumn = $options['idColumn'] ?? 'id';
        $this->versionColumn = $options['versionColumn'] ?? null;
        $this->customBackendOptions = $options['backendOptions'] ?? [];
    }

    public static function createWithMapperAndOptions(Mapper $mapper, array $options): DataTargetInterface
    {
        return new static($mapper, $options);
    }

    /**
     * @throws DBALException
     */
    public function initializeObject(): void
    {
        $backendOptions = Arrays::arrayMergeRecursiveOverrule($this->flowBackendOptions, $this->customBackendOptions);
        $this->dbal = DriverManager::getConnection($backendOptions);
    }

    public function computeDataChanges(DataRecords $records, bool $forceUpdates, bool $skipAddedRecords, bool $skipRemovedRecords): ChangeSet
    {
        $localIds = $this->getLocalIds();
        $removedIds = $skipRemovedRecords ? DataIds::createEmpty() : $localIds->diff($records->getIds());

        $updatedRecords = DataRecords::createEmpty();
        $addedRecords = DataRecords::createEmpty();
        foreach ($records as $record) {
            if (!$localIds->has($record->id())) {
                if (!$skipAddedRecords) {
                    $addedRecords = $addedRecords->withRecord($record);
                }
                continue;
            }
            if ($forceUpdates || $this->isRecordUpdated($record)) {
                $updatedRecords = $updatedRecords->withRecord($record);
            }
        }
        return ChangeSet::fromAddedUpdatedAndRemoved($addedRecords, $updatedRecords, $removedIds);
    }

    private function getLocalIds(): DataIds
    {
        return DataIds::fromStringArray(array_column($this->localRows(), $this->idColumn));
    }

    private function localVersion(DataId $dataId): DataVersion
    {
        if ($this->versionColumn === null) {
            return DataVersion::none();
        }
        if ($this->localVersionsCache === null) {
            $this->localVersionsCache = array_column($this->localRows(), $this->versionColumn, $this->idColumn);
        }
        if (!isset($this->localVersionsCache[$dataId->toString()])) {
            return DataVersion::none();
        }
        return DataVersion::parse($this->localVersionsCache[$dataId->toString()]);
    }

    public function isRecordUpdated(DataRecordInterface $record): bool
    {
        if ($record->version()->isNotSet()) {
            return true;
        }
        $localVersion = $this->localVersion($record->id());
        if ($localVersion->isNotSet()) {
            return true;
        }
        return $record->version()->isHigherThan($localVersion);
    }

    private function localRows(): array
    {
        if ($this->localRowsCache === null) {
            if ($this->versionColumn === null) {
                $this->localRowsCache = $this->dbal->fetchAll(sprintf('SELECT %s FROM %s', $this->dbal->quoteIdentifier($this->idColumn), $this->dbal->quoteIdentifier($this->tableName)));
            } else {
                $this->localRowsCache = $this->dbal->fetchAll(sprintf('SELECT %s, %s FROM %s', $this->dbal->quoteIdentifier($this->idColumn), $this->dbal->quoteIdentifier($this->versionColumn), $this->dbal->quoteIdentifier($this->tableName)));
            }
        }
        return $this->localRowsCache;
    }

    /**
     * @param DataRecordInterface $record
     * @throws DBALException
     */
    public function addRecord(DataRecordInterface $record): void
    {
        $this->dbal->insert($this->tableName, $this->mapper->mapRecord($record));
    }

    /**
     * @param DataRecordInterface $record
     * @throws DBALException
     */
    public function updateRecord(DataRecordInterface $record): void
    {
        $this->dbal->update($this->tableName, $this->mapper->mapRecord($record), [$this->idColumn => $record->id()->toString()]);
    }

    /**
     * @param DataId $dataId
     * @throws DBALException
     */
    public function removeRecord(DataId $dataId): void
    {
        $this->dbal->delete($this->tableName, [$this->idColumn => $dataId->toString()]);
    }

    /**
     * @throws DBALException
     */
    public function removeAll(): int
    {
        $result = $this->dbal->executeQuery('DELETE FROM ' . $this->dbal->quoteIdentifier($this->tableName));
        if ($result instanceof Statement) {
            return $result->rowCount();
        }
        return 0;
    }

    public function finalize(): void
    {
        // nothing to do here
    }
}
