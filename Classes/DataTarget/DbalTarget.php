<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget;

use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Wwwision\ImportService\ValueObject\DataVersion;

/**
 * DBAL Data Target that allows to import records into a database table
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
        $this->tableName = $options['table'];
        $this->idColumn = $options['idColumn'] ?? 'id';
        $this->versionColumn = $options['versionColumn'] ?? null;
        $this->customBackendOptions = $options['customBackendOptions'] ?? null;
    }

    public static function getOptionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('table', 'string')
            ->has('idColumn', 'string')
            ->has('versionColumn', 'string')
            ->has('backendOptions', 'array');
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
        if ($this->customBackendOptions === null) {
            $this->dbal = DriverManager::getConnection($this->flowBackendOptions);
            return;
        }
        if (!\is_array($this->customBackendOptions)) {
            throw new \RuntimeException(sprintf('Option "backendOptions" must resolve to an array, given: %s', \is_object($this->customBackendOptions) ? \get_class($this->customBackendOptions) : \gettype($this->customBackendOptions)), 1563881291);
        }
        $backendOptions = Arrays::arrayMergeRecursiveOverrule($this->flowBackendOptions, $this->customBackendOptions);
        $this->dbal = DriverManager::getConnection($backendOptions);
    }

    public function setup(): Result
    {
        $result = new Result();
        try {
            /** @noinspection NullPointerExceptionInspection */
            if ($this->dbal->getSchemaManager()->tablesExist([$this->tableName])) {
                $result->addNotice(new Notice('Target table "%s" exists', null, [$this->tableName]));
            } else {
                $result->addError(new Error('Target table "%s" doesn\'t exist', null, [$this->tableName]));
            }
        } catch (DBALException $exception) {
            $result->addError(new Error('Failed to connect to target database: %s', $exception->getCode(), [$exception->getMessage()]));
        }
        return $result;
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
                $this->localRowsCache = $this->dbal->fetchAllAssociative(sprintf('SELECT %s FROM %s', $this->dbal->quoteIdentifier($this->idColumn), $this->dbal->quoteIdentifier($this->tableName)));
            } else {
                $this->localRowsCache = $this->dbal->fetchAllAssociative(sprintf('SELECT %s, %s FROM %s', $this->dbal->quoteIdentifier($this->idColumn), $this->dbal->quoteIdentifier($this->versionColumn), $this->dbal->quoteIdentifier($this->tableName)));
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
        $this->dbal->insert($this->tableName, $this->mapper->mapRecord($record, []));
    }

    /**
     * @param DataRecordInterface $record
     * @throws DBALException
     */
    public function updateRecord(DataRecordInterface $record): void
    {
        $this->dbal->update($this->tableName, $this->mapper->mapRecord($record, []), [$this->idColumn => $record->id()->toString()]);
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
