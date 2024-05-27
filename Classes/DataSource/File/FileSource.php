<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\File;

use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * File Data Source that allows to import records from a file
 */
#[Flow\Proxy(false)]
final class FileSource implements DataSourceInterface
{

    public function __construct(
        private readonly string $filePath,
        private readonly string $idAttributeName,
        private readonly string|null $versionAttributeName,
    ) {
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
        return DataRecords::fromRawArray(json_decode($fileContents, true, 512, JSON_THROW_ON_ERROR), $this->idAttributeName, $this->versionAttributeName);
    }
}
