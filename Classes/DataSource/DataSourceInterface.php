<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * Interface for ImportService Data Sources
 */
interface DataSourceInterface
{

    public static function createWithOptions(array $options): self;

    public static function getOptionsSchema(): OptionsSchema;

    public function load(): DataRecords;

}
