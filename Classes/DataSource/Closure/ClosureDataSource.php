<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\Closure;

use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * This Data Source is mainly useful for testing.
 *
 * Example Mock Data Source:
 *
 * $mockDataSource = new ClosureDataSource(function(Options $options) { return $someMockDataRecords; });
 * $importService = $this->importServiceFactory->createWithDataSource($presetName, $mockDataSource);
 * // ...
 *
 */
#[Flow\Proxy(false)]
final class ClosureDataSource implements DataSourceInterface
{

    /**
     * @param \Closure $dataClosure callback to be invoked if load() is called
     * @param array $options Arbitrary options that will be passed to the closure
     */
    public function __construct(
        private \Closure $dataClosure,
        private readonly array $options
    ) {
    }

    public function setup(): Result
    {
        // ClosureDataSource can't be setup
        return new Result();
    }

    /**
     * @param \Closure $closure
     */
    public function replaceClosure(\Closure $closure): void
    {
        $this->dataClosure = $closure;
    }

    public function load(): DataRecords
    {
        return \call_user_func($this->dataClosure, $this->options);
    }
}
