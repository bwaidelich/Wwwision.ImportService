<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @implements \IteratorAggregate<string>
 */
#[Flow\Proxy(false)]
final class DataIds implements \IteratorAggregate, \Countable
{

    /**
     * @param array<string, DataId> $ids
     */
    private function __construct(
        private readonly array $ids
    )
    {
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    public static function fromStringArray(array $ids): self
    {
        $convertedIds = [];
        foreach ($ids as $id) {
            if ($id instanceof DataId) {
                $convertedIds[$id->value] = $id;
            } else {
                $id = (string)$id;
                $convertedIds[$id] = DataId::fromString($id);
            }
        }
        return new self($convertedIds);
    }

    public function withId(DataId $id): self
    {
        if (\array_key_exists($id->value, $this->ids)) {
            return $this;
        }
        $newIds = $this->ids;
        $newIds[$id->value] = $id;
        return new self($newIds);
    }

    public function diff(DataIds $other): self
    {
        return self::fromStringArray(array_diff_key($this->ids, $other->ids));
    }

    public function has(DataId $dataId): bool
    {
        return \array_key_exists($dataId->value, $this->ids);
    }

    /**
     * @return \Traversable<string>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->ids;
    }

    public function count(): int
    {
        return \count($this->ids);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }
}
