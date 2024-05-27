<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Factory;

use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\DataSource\File\FileSource;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Wwwision\ImportService\ImportService;
use Wwwision\ImportService\Preset;

/**
 * Factory for the ImportService
 */
#[Flow\Scope('singleton')]
final class ImportServiceFactory
{

    public function __construct(
        private readonly array $presets,
        private readonly array $presetTemplates,
        private readonly PresetFactory $presetFactory,
    ) {
    }

    public function create(string $presetName, array|null $customSourceOptions, array|null $customTargetOptions): ImportService
    {
        return new ImportService($this->presetFactory->create($this->getPresetConfiguration($presetName), $customSourceOptions, $customTargetOptions));
    }

    public function createFromPreset(Preset $preset): ImportService
    {
        return new ImportService($preset);
    }

    public function createWithFixture(string $presetName, array|null $customTargetOptions): ImportService
    {
        $preset = $this->presetFactory->create($this->getPresetConfiguration($presetName), null, $customTargetOptions);
        if (!isset($this->presets[$presetName]['source']['fixture']['file'])) {
            throw new \RuntimeException(sprintf('Missing "source.fixture.file" configuration for preset "%s"', $presetName), 1558433554);
        }
        $fixtureSource = new FileSource(
            filePath: $this->presets[$presetName]['source']['fixture']['file'],
            idAttributeName: $this->presets[$presetName]['source']['fixture']['idAttributeName'] ?? 'id',
            versionAttributeName: $this->presets[$presetName]['source']['fixture']['versionAttributeName'] ?? null
        );
        return new ImportService($preset->withDataSource($fixtureSource));
    }

    public function createWithDataSource(string $presetName, DataSourceInterface $dataSource): ImportService
    {
        return new ImportService($this->presetFactory->create($this->getPresetConfiguration($presetName), null, null)->withDataSource($dataSource));
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
        return array_keys($this->presets ?? []);
    }

}
