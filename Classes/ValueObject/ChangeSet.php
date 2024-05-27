<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class ChangeSet
{

    private function __construct(
        public readonly DataRecords $addedRecords,
        public readonly DataRecords $updatedRecords,
        public readonly DataIds $removedIds
    ) {
    }

    public static function fromAddedUpdatedAndRemoved(DataRecords $addedRecords, DataRecords $updatedRecords, DataIds $removedIds): self
    {
        return new self($addedRecords, $updatedRecords, $removedIds);
    }

    public function hasDataToAdd(): bool
    {
        return !$this->addedRecords->isEmpty();
    }

    public function hasDataToRemove(): bool
    {
        return !$this->removedIds->isEmpty();
    }

}
