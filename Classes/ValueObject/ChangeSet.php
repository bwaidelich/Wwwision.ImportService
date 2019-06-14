<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class ChangeSet
{

    /**
     * @var DataRecords
     */
    private $addedRecords;

    /**
     * @var DataRecords
     */
    private $updatedRecords;

    /**
     * @var DataIds
     */
    private $removedIds;

    private function __construct(DataRecords $addedRecords, DataRecords $updatedRecords, DataIds $removedIds)
    {
        $this->addedRecords = $addedRecords;
        $this->updatedRecords = $updatedRecords;
        $this->removedIds = $removedIds;
    }

    public static function fromAddedUpdatedAndRemoved(DataRecords $addedRecords, DataRecords $updatedRecords, DataIds $removedIds): self
    {
        return new self($addedRecords, $updatedRecords, $removedIds);
    }

    public function addedRecords(): DataRecords
    {
        return $this->addedRecords;
    }

    public function updatedRecords(): DataRecords
    {
        return $this->updatedRecords;
    }

    public function removedIds(): DataIds
    {
        return $this->removedIds;
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
