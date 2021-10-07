<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget;

use Neos\Error\Messages\Result;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * Interface for ImportService data mappers
 */
interface DataTargetInterface
{
    public static function createWithMapperAndOptions(Mapper $mapper, array $options): self;

    public static function getOptionsSchema(): OptionsSchema;

    public function computeDataChanges(DataRecords $records, bool $forceUpdates, bool $skipAddedRecords, bool $skipRemovedRecords): ChangeSet;

    public function addRecord(DataRecordInterface $record): void;

    public function updateRecord(DataRecordInterface $record): void;

    public function removeRecord(DataId $dataId): void;

    public function removeAll(): int;

    public function finalize(): void;

    public function setup(): Result;

}
