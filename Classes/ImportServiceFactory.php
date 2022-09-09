<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\DataSource\FileSource;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

/**
 * Factory for the ImportService
 *
 * @Flow\Scope("singleton")
 */
final class ImportServiceFactory
{

    /**
     * @Flow\InjectConfiguration(path="presetTemplates")
     * @var array
     */
    protected $presetTemplates;

    /**
     * @Flow\InjectConfiguration(path="presets")
     * @var array
     */
    protected $presets;

    public function create(string $presetName): ImportService
    {
        return new ImportService($this->createPreset($presetName));
    }

    public function createFromPreset(Preset $preset): ImportService
    {
        return new ImportService($preset);
    }

    public function createWithFixture(string $presetName): ImportService
    {
        $preset = $this->createPreset($presetName);
        if (!isset($this->presets[$presetName]['source']['fixture']['file'])) {
            throw new \RuntimeException(sprintf('Missing "source.fixture.file" configuration for preset "%s"', $presetName), 1558433554);
        }
        $fixtureOptions = [
            'filePath' => $this->presets[$presetName]['source']['fixture']['file'],
            'idAttributeName' => $this->presets[$presetName]['source']['fixture']['idAttributeName'] ?? 'id',
        ];
        $fixtureSource = FileSource::createWithOptions($fixtureOptions);
        return new ImportService($preset->withDataSource($fixtureSource));
    }

    public function createWithDataSource(string $presetName, DataSourceInterface $dataSource): ImportService
    {
        return new ImportService($this->createPreset($presetName)->withDataSource($dataSource));
    }

    public function createPreset(string $presetName): Preset
    {
        $presetConfiguration = $this->getPresetConfiguration($presetName);
        try {
            return Preset::fromConfiguration($presetConfiguration);
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Error while loading preset "%s": %s', $presetName, $exception->getMessage()), 1558340308, $exception);
        }
    }

    public function getPresetConfiguration(string $presetName): array
    {
        if (!\array_key_exists($presetName, $this->presets)) {
            throw new \InvalidArgumentException(sprintf('Preset "%s" is not configured', $presetName), 1557127480);
        }
        $presetConfiguration = $this->presets[$presetName];
        if (\array_key_exists('template', $presetConfiguration)) {
            if (!\array_key_exists($presetConfiguration['template'], $this->presetTemplates)) {
                throw new \RuntimeException(sprintf('Preset "%s" refers to a non-existing preset template "%s"', $presetName, $presetConfiguration['template']), 1558951624);
            }
            $presetConfiguration = Arrays::arrayMergeRecursiveOverrule($this->presetTemplates[$presetConfiguration['template']], $presetConfiguration);
        }
        return $presetConfiguration;
    }

    /**
     * @return string[]
     */
    public function getPresetNames(): array
    {
        return array_keys($this->presets);
    }

}
