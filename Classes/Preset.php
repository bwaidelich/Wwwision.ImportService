<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\DataTarget\DataTargetInterface;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * The representation of a Import Service preset
 */
final class Preset
{

    /**
     * @var DataSourceInterface
     */
    private $dataSource;

    /**
     * @var DataTargetInterface
     */
    private $dataTarget;

    /**
     * @var bool if true no new records will be added - even if they don't exist locally
     */
    private $skipAddedRecords;

    /**
     * @var bool if true no new records will be removed - even if they don't exist externally any longer
     */
    private $skipRemovedRecords;

    /**
     * @var ?callable
     */
    private $dataSourceProcessor;

    protected function __construct(DataSourceInterface $dataSource, DataTargetInterface $dataTarget, bool $skipAddedRecords, bool $skipRemovedRecords, $dataSourceProcessor)
    {
        $this->dataSource = $dataSource;
        $this->dataTarget = $dataTarget;
        $this->skipAddedRecords = $skipAddedRecords;
        $this->skipRemovedRecords = $skipRemovedRecords;
        $this->dataSourceProcessor = $dataSourceProcessor;
    }


    public static function fromConfiguration(array $configuration): self
    {
        if (!isset($configuration['source']['className'])) {
            throw new \RuntimeException('Missing "source.className" configuration', 1557238721);
        }
        /** @var DataSourceInterface $dataSourceClassName */
        $dataSourceClassName = $configuration['source']['className'];
        try {
            $dataSource = $dataSourceClassName::createWithOptions($configuration['source']['options'] ?? []);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating data source (%s)', $configuration['source']['className']), 1557999968, $exception);
        }
        if (!$dataSource instanceof DataSourceInterface) {
            throw new \RuntimeException(sprintf('The configured "source.className" is not an instance of %s', DataSourceInterface::class), 1557238800);
        }
        if (!isset($configuration['mapping'])) {
            throw new \RuntimeException(sprintf('Missing "mapping" configuration'), 1558080904);
        }
        try {
            $mapper = Mapper::fromArray($configuration['mapping']);
        } catch (\Exception $exception) {
            throw new \RuntimeException('Exception while instantiating Mapper', 1558096738, $exception);
        }

        if (!isset($configuration['target']['className'])) {
            throw new \RuntimeException('Missing "target.className" configuration', 1557238852);
        }
        /** @var DataTargetInterface $dataTargetClassName */
        $dataTargetClassName = $configuration['target']['className'];
        try {
            $dataTarget = $dataTargetClassName::createWithMapperAndOptions($mapper, $configuration['target']['options'] ?? []);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating data target (%s)', $configuration['target']['className']), 1558000004, $exception);
        }
        if (!$dataTarget instanceof DataTargetInterface) {
            throw new \RuntimeException(sprintf('The configured "target.className" is not an instance of %s', DataTargetInterface::class), 1557238877);
        }
        $skipAddedRecords = $configuration['skipAddedRecords'] ?? false;
        $skipRemovedRecords = $configuration['skipRemovedRecords'] ?? false;
        $dataSourceProcessor = isset($configuration['source']['postProcessor']) ? explode('::', $configuration['source']['postProcessor'], 2) : null;
        return new static($dataSource, $dataTarget, $skipAddedRecords, $skipRemovedRecords, $dataSourceProcessor);
    }

    public function withDataSource(DataSourceInterface $dataSource): self
    {
        return new static($dataSource, $this->dataTarget, $this->skipAddedRecords, $this->skipRemovedRecords, $this->dataSourceProcessor);
    }

    public function isSkipAddedRecords(): bool
    {
        return $this->skipAddedRecords;
    }

    public function isSkipRemovedRecords(): bool
    {
        return $this->skipRemovedRecords;
    }

    public function load(): DataRecords
    {
        $records = $this->dataSource->load();
        if ($this->dataSourceProcessor !== null) {
            $records = \call_user_func($this->dataSourceProcessor, $records);
        }
        return $records;
    }

    public function computeDataChanges(DataRecords $records, bool $forceUpdates): ChangeSet
    {
        return $this->dataTarget->computeDataChanges($records, $forceUpdates, $this->skipAddedRecords, $this->skipRemovedRecords);
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
