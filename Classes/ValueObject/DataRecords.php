<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class DataRecords implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var DataRecordInterface[]
     */
    private $records;

    /**
     * @var int
     */
    private $count;

    private function __construct(array $records)
    {
        $this->records = $records;
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public static function fromRawArray(array $array, string $idAttributeName, ?string $versionAttributeName): self
    {
        $records = [];
        foreach ($array as $item) {
            if (!isset($item[$idAttributeName])) {
                throw new \RuntimeException(sprintf('the id attribute "%s" is not part of the data source', $idAttributeName), 1558001632);
            }
            $id = (string)$item[$idAttributeName];
            if ($versionAttributeName !== null) {
                if (!isset($item[$versionAttributeName])) {
                    throw new \RuntimeException(sprintf('the version attribute "%s" is not part of the data source', $versionAttributeName), 1560523547);
                }
                $records[$id] = DataRecord::fromIdVersionAndAttributes(DataId::fromString($id), DataVersion::parse($item[$versionAttributeName]), $item);
            } else {
                $records[$id] = DataRecord::fromIdAndAttributes(DataId::fromString($id), $item);
            }
        }
        return new self($records);
    }

    public static function fromRecords(array $records): self
    {
        $processedRecords = [];
        foreach ($records as $record) {
            if (!$record instanceof DataRecordInterface) {
                throw new \RuntimeException(sprintf('Excepted array of %s instances, got: %s', DataRecord::class, \is_object($record) ? \get_class($record) : \gettype($record)), 1558352216);
            }
            $processedRecords[$record->id()->toString()] = $record;
        }
        return new self($processedRecords);
    }

    public function withRecord(DataRecordInterface $record): self
    {
        if ($this->hasRecord($record)) {
            return $this;
        }
        $newRecords = $this->records;
        $newRecords[(string)$record->id()] = $record;
        return new self($newRecords);
    }

    public function hasRecord(DataRecordInterface $record): bool
    {
        return $this->hasRecordWithId($record->id());
    }

    public function hasRecordWithId(DataId $dataId): bool
    {
        return \array_key_exists($dataId->toString(), $this->records);
    }

    /**
     * @return DataRecordInterface[]|\Iterator
     */
    public function getIterator(): \Iterator
    {
        yield from $this->records;
    }

    public function count(): int
    {
        if ($this->count === null) {
            $this->count = \count($this->records);
        }
        return $this->count;
    }

    public function isEmpty(): bool
    {
        return $this->records === [];
    }

    public function getIds(): DataIds
    {
        return DataIds::fromStringArray(array_keys($this->records));
    }

    public function map(\Closure $filter): self
    {
        return new self(array_map($filter, $this->records));
    }

    public function filter(\Closure $filter): self
    {
        return new self(array_filter($this->records, $filter));
    }

    public function jsonSerialize(): array
    {
        return array_values($this->records);
    }
}
