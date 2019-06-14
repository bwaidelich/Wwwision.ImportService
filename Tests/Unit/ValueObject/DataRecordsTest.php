<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Tests\Unit\ValueObject;

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

    public function countDataProvider(): array
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
}
