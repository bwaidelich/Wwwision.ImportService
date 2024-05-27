<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Neos\Error\Messages\Result;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * Interface for ImportService Data Sources
 */
interface DataSourceInterface
{

    public function load(): DataRecords;

    public function setup(): Result;

}
