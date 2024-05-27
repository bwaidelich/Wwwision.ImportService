<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Factory;

use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\EelEvaluator;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\Preset;

/**
 * Factory for {@see Preset} instances
 */
#[Flow\Scope('singleton')]
final class MapperFactory
{
    protected function __construct(
        private readonly EelEvaluator $eelEvaluator,
    )
    {
    }

    public function create(array $mapping): Mapper
    {
        return new Mapper($this->eelEvaluator, $mapping);
    }
}
