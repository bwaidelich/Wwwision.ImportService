<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
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

    public function setup(): Result
    {
        $result = new Result();
        if (is_file($this->filePath) && is_readable($this->filePath)) {
            $result->addNotice(new Notice('Source file "%s" is readable', null, [$this->filePath]));
        } else {
            $result->addError(new Error('Source file "%s" is not readable', null, [$this->filePath]));
        }
        return $result;
    }

    public function load(): DataRecords
    {
        $fileContents = file_get_contents($this->filePath);
        return DataRecords::fromRawArray(json_decode($fileContents, true), $this->idAttributeName, $this->versionAttributeName);
    }
}
