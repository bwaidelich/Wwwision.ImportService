<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\OptionsSchema;

/**
 * Interface for ImportService Data Source factories
 */
interface DataSourceFactoryInterface
{
    public function create(array $options): DataSourceInterface;

    public function optionsSchema(): OptionsSchema;
}
