<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Tests\Unit\ValueObject;

use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecord;
use Wwwision\ImportService\ValueObject\DataRecords;
use Neos\Flow\Tests\UnitTestCase;
use PHPUnit\Framework\Assert;

class DataRecordsTest extends UnitTestCase
{

    /**
     * @test
     */
    public function createEmptyCreatesAnEmptyDataRecordsCollection(): void
    {
        $records = DataRecords::createEmpty();
        Assert::assertCount(0, $records);
    }

    public static function countDataProvider(): array
    {
        return [
            ['rows' => [], 'expectedCount' => 0],
            ['rows' => [['id' => 'first']], 'expectedCount' => 1],
            ['rows' => [['id' => 'first'], ['id' => 'second']], 'expectedCount' => 2],
        ];
    }

    /**
     * @param array $rows
     * @param int $expectedCount
     * @test
     * @dataProvider countDataProvider
     */
    public function countTests(array $rows, int $expectedCount): void
    {
        $records = DataRecords::fromRawArray($rows, 'id', null);
        Assert::assertCount($expectedCount, $records);
    }

    /**
     * @test
     */
    public function mapAllowsChangingRecordIds(): void
    {
        $records = DataRecords::fromRawArray([['id' => 'first'], ['id' => 'second']], 'id', null);
        $recordsWithChangedIds = $records->map(static function(DataRecord $record) {
            return $record->withId(DataId::fromString($record->id()->value . '-changed'));
        });

        $expectedIds = DataIds::fromStringArray(['first-changed', 'second-changed']);
        Assert::assertTrue($recordsWithChangedIds->getIds()->diff($expectedIds)->isEmpty());
    }
}
