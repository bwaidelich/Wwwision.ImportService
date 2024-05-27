<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Factory;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Wwwision\ImportService\DataSource\DataSourceFactoryInterface;
use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\DataTarget\DataTargetFactoryInterface;
use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\Preset;

/**
 * Factory for {@see Preset} instances
 */
#[Flow\Scope('singleton')]
final class PresetFactory
{

    protected function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly MapperFactory $mapperFactory,
    )
    {
    }

    public function create(array $configuration, array|null $customSourceOptions, array|null $customTargetOptions): Preset
    {
        if (!isset($configuration['source']['factory'])) {
            throw new \RuntimeException('Missing "source.factory" configuration', 1557238721);
        }
        $factoryClassName = $configuration['source']['factory'];
        try {
            $dataSourceFactory = $this->objectManager->get($factoryClassName);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating data source (%s): %s', $factoryClassName, $exception->getMessage()), 1557999968, $exception);
        }
        if (!$dataSourceFactory instanceof DataSourceFactoryInterface) {
            throw new \RuntimeException(sprintf('The configured "source.factory" %s is not an instance of %s', $factoryClassName, DataSourceFactoryInterface::class), 1557238800);
        }
        $options = $configuration['source']['options'] ?? [];
        if ($customSourceOptions !== null) {
            $options = [...$options, ...$customSourceOptions];
        }
        try {
            $dataSourceFactory->optionsSchema()->validate($options);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException(sprintf('Failed to create data source for factory of type %s: %s', $factoryClassName, $e->getMessage()), 1716822862, $e);
        }
        return $this->createWithDataSource($configuration, $dataSourceFactory->create($options), $customTargetOptions);
    }

    public function createWithDataSource(array $configuration, DataSourceInterface $dataSource, array|null $customTargetOptions): Preset
    {
        if (!isset($configuration['mapping'])) {
            throw new \RuntimeException('Missing "mapping" configuration', 1558080904);
        }
        try {
            $mapper = $this->mapperFactory->create($configuration['mapping']);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating Mapper: %s', $exception->getMessage()), 1558096738, $exception);
        }

        if (!isset($configuration['target']['factory'])) {
            throw new \RuntimeException('Missing "target.factory" configuration', 1557238852);
        }
        $factoryClassName = $configuration['target']['factory'];
        try {
            $dataTargetFactory = $this->objectManager->get($factoryClassName);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Exception while instantiating data target (%s): %s', $factoryClassName, $exception->getMessage()), 1558000004, $exception);
        }
        if (!$dataTargetFactory instanceof DataTargetFactoryInterface) {
            throw new \RuntimeException(sprintf('The configured "target.factory" %s is not an instance of %s', $factoryClassName, DataTargetFactoryInterface::class), 1557238877);
        }
        $options = $configuration['target']['options'] ?? [];
        if ($customTargetOptions !== null) {
            $options = [...$options, ...$customTargetOptions];
        }
        try {
            $dataTargetFactory->optionsSchema()->validate($options);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException(sprintf('Failed to create data target for factory of type %s: %s', $factoryClassName, $e->getMessage()), 1716822920, $e);
        }
        $dataTarget = $dataTargetFactory->create($mapper, $options);
        $presetOptions = $configuration['options'] ?? [];
        OptionsSchema::create()
            ->has('skipAddedRecords', 'boolean')
            ->has('skipRemovedRecords', 'boolean')
            ->has('dataProcessor', 'callable')
            ->validate($presetOptions);
        return new Preset($dataSource, $dataTarget, $presetOptions);
    }
}
