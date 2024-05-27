<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Neos\Utility\TypeHandling;
use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\DataTarget\DataTargetInterface;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * The representation of an Import Service preset
 */
final class Preset
{

    public function __construct(
        public readonly DataSourceInterface $dataSource,
        public readonly DataTargetInterface $dataTarget,
        private readonly array $options
    ) {
    }

    public function withDataSource(DataSourceInterface $dataSource): self
    {
        return new static($dataSource, $this->dataTarget, $this->options);
    }

    public function isSkipAddedRecords(): bool
    {
        return $this->options['skipAddedRecords'] ?? false;
    }

    public function isSkipRemovedRecords(): bool
    {
        return $this->options['skipRemovedRecords'] ?? false;
    }

    public function load(): DataRecords
    {
        $records = $this->dataSource->load();
        if (isset($this->options['dataProcessor'])) {
            [$className, $methodName] = explode('::', $this->options['dataProcessor'], 2);
            $records = \call_user_func([new $className(), $methodName], $records);
            if (!$records instanceof DataRecords) {
                throw new \RuntimeException(sprintf('The "dataProcessor" must return an instance of %s but returned a %s', DataRecords::class, TypeHandling::getTypeForValue($records)), 1563978776);
            }
        }
        return $records;
    }

    public function computeDataChanges(DataRecords $records, bool $forceUpdates): ChangeSet
    {
        return $this->dataTarget->computeDataChanges($records, $forceUpdates, $this->isSkipAddedRecords(), $this->isSkipRemovedRecords());
    }

    public function addRecord(DataRecordInterface $record): void
    {
        $this->dataTarget->addRecord($record);
    }

    public function updateRecord(DataRecordInterface $record): void
    {
        $this->dataTarget->updateRecord($record);
    }

    public function removeRecord(DataId $dataId): void
    {
        $this->dataTarget->removeRecord($dataId);
    }

    public function removeAll(): int
    {
        return $this->dataTarget->removeAll();
    }

    public function finalize(): void
    {
        $this->dataTarget->finalize();
    }
}
