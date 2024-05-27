<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\File;

use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\DataSource\DataSourceFactoryInterface;
use Wwwision\ImportService\OptionsSchema;

/**
 * Factory for the {@see FileSource}
 */
#[Flow\Scope('singleton')]
final class FileSourceFactory implements DataSourceFactoryInterface
{
    public function create(array $options): FileSource
    {
        return new FileSource(
            filePath: $options['filePath'],
            idAttributeName: $options['idAttributeName'],
            versionAttributeName: $options['versionAttributeName'] ?? null,
        );
    }

    public function optionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('filePath', 'string')
            ->has('idAttributeName', 'string')
            ->has('versionAttributeName', 'string');
    }
}
