<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * File Data Source that allows to import records from a file
 */
final class FileSource implements DataSourceInterface
{

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var string
     */
    private $idAttributeName;

    /**
     * @var string|null
     */
    private $versionAttributeName;

    protected function __construct(array $options)
    {
        $this->filePath = $options['filePath'];
        $this->idAttributeName = $options['idAttributeName'] ?? 'id';
        $this->versionAttributeName = $options['versionAttributeName'] ?? null;
    }

    public static function getOptionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('filePath', 'string')
            ->has('idAttributeName', 'string')
            ->has('versionAttributeName', 'string');
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static($options);
    }

    public function load(): DataRecords
    {
        $fileContents = file_get_contents($this->filePath);
        return DataRecords::fromRawArray(json_decode($fileContents, true), $this->idAttributeName, $this->versionAttributeName);
    }
}
