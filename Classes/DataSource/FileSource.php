<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\ValueObject\DataRecords;

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

    protected function __construct(array $options)
    {
        if (!isset($options['filePath'])) {
            throw new \InvalidArgumentException('Missing option "filePath"', 1558015623);
        }
        $this->filePath = $options['filePath'];
        $this->idAttributeName = $options['idAttributeName'] ?? 'id';
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static($options);
    }

    public function load(): DataRecords
    {
        $fileContents = file_get_contents($this->filePath);
        return DataRecords::fromRawArray(json_decode($fileContents, true), $this->idAttributeName);
    }
}
