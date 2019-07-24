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
     * @var array
     */
    private $options;

    protected function __construct(DataSourceInterface $dataSource, DataTargetInterface $dataTarget, array $options)
    {
        $this->dataSource = $dataSource;
        $this->dataTarget = $dataTarget;
        $this->options = $options;
    }

    public static function fromConfiguration(array $configuration): self
    {
        if (!isset($configuration['source']['className'])) {
            throw new \RuntimeException('Missing "source.className" configuration', 1557238721);
        }
        /** @var DataSourceInterface $dataSourceClassName */
        $dataSourceClassName = $configuration['source']['className'];
        $dataSourceOptions = $configuration['source']['options'] ?? [];
        try {
            $dataSourceClassName::getOptionsSchema()->validate($dataSourceOptions);
            $dataSource = $dataSourceClassName::createWithOptions($dataSourceOptions);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating data source (%s): %s', $configuration['source']['className'], $exception->getMessage()), 1557999968, $exception);
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
            throw new \RuntimeException(sprintf('Exception while instantiating Mapper: %s', $exception->getMessage()), 1558096738, $exception);
        }

        if (!isset($configuration['target']['className'])) {
            throw new \RuntimeException('Missing "target.className" configuration', 1557238852);
        }
        /** @var DataTargetInterface $dataTargetClassName */
        $dataTargetClassName = $configuration['target']['className'];
        $dataTargetOptions = $configuration['target']['options'] ?? [];
        try {
            $dataTargetClassName::getOptionsSchema()->validate($dataTargetOptions);
            $dataTarget = $dataTargetClassName::createWithMapperAndOptions($mapper, $dataTargetOptions);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating data target (%s): %s', $configuration['target']['className'], $exception->getMessage()), 1558000004, $exception);
        }
        if (!$dataTarget instanceof DataTargetInterface) {
            throw new \RuntimeException(sprintf('The configured "target.className" is not an instance of %s', DataTargetInterface::class), 1557238877);
        }

        $presetOptions = $configuration['options'] ?? [];
        OptionsSchema::create()
            ->has('skipAddedRecords', 'boolean')
            ->has('skipRemovedRecords', 'boolean')
            ->has('dataProcessor', 'callable')
            ->validate($presetOptions);
        return new static($dataSource, $dataTarget, $presetOptions);
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
                throw new \RuntimeException(sprintf('The "dataPostprocessor" must return an instance of %s but returned a %s', DataRecords::class, TypeHandling::getTypeForValue($records)), 1563978776);
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
