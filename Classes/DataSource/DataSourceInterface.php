<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * Interface for ImportService data sources
 */
interface DataSourceInterface
{

    public static function createWithOptions(array $options): self;

    public function load(): DataRecords;

}
