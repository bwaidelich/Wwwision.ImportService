<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class DataIds implements \IteratorAggregate, \Countable
{
    /**
     * @var string[]
     */
    private $ids;

    /**
     * @var int
     */
    private $count;

    private function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public static function fromStringArray(array $ids)
    {
        $convertedIds = [];
        foreach ($ids as $id) {
            if (!\is_string($id)) {
                $id = (string)$id;
            }
            $convertedIds[$id] = DataId::fromString($id);
        }
        return new self($convertedIds);
    }

    public function withId(DataId $id): self
    {
        if (\array_key_exists($id->toString(), $this->ids)) {
            return $this;
        }
        $newIds = $this->ids;
        $newIds[$id->toString()] = $id;
        return new self($newIds);
    }

    public function diff(DataIds $other): self
    {
        return new self(array_diff($this->ids, $other->ids));
    }

    public function has(DataId $dataId): bool
    {
        return \array_key_exists($dataId->toString(), $this->ids);
    }

    /**
     * @return string[]|\Iterator
     */
    public function getIterator(): \Iterator
    {
        yield from $this->ids;
    }

    public function count(): int
    {
        if ($this->count === null) {
            $this->count = \count($this->ids);
        }
        return $this->count;
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }
}
