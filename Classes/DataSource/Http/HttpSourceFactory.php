<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\Http;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\DataSource\DataSourceFactoryInterface;
use Wwwision\ImportService\OptionsSchema;

/**
 * Factory for the {@see HttpSource}
 */
#[Flow\Scope('singleton')]
final class HttpSourceFactory implements DataSourceFactoryInterface
{
    public function create(array $options): HttpSource
    {
        return new HttpSource(
            endpoint: new Uri($options['endpoint']),
            idAttributeName: $options['idAttributeName'] ?? 'id',
            versionAttributeName: $options['versionAttributeName'] ?? null,
            httpOptions: $options['httpOptions'] ?? ['headers' => ['Accept' => 'application/json']],
        );
    }

    public function optionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('endpoint', 'string')
            ->has('idAttributeName', 'string')
            ->has('versionAttributeName', 'string')
            ->has('httpOptions', 'array');
    }
}
