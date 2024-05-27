<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget;

use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\OptionsSchema;

/**
 * Interface for ImportService data target factories
 */
interface DataTargetFactoryInterface
{
    public function create(Mapper $mapper, array $options): DataTargetInterface;

    public function optionsSchema(): OptionsSchema;
}
