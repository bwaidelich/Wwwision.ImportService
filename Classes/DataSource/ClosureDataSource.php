<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * This Data Source is mainly useful for testing.
 *
 * Example Mock Data Source:
 *
 * $mockDataSource = ClosureDataSource::forClosure(function(Options $options) { return $someMockDataRecords; });
 * $importService = $this->importServiceFactory->createWithDataSource($presetName, $mockDataSource);
 * // ...
 *
 */
final class ClosureDataSource implements DataSourceInterface
{
    /**
     * @var \Closure
     */
    private $dataClosure;

    /**
     * @var array
     */
    private $options;

    /**
     * @param \Closure callback to be invoked if load() is called
     * @param array $options Arbitrary options that will be passed to the closure
     */
    protected function __construct(\Closure $dataClosure, array $options)
    {
        $this->dataClosure = $dataClosure;
        $this->options = $options;
    }

    public static function forClosure(\Closure $dataClosure, array $options): self
    {
        return new static($dataClosure, $options);
    }

    public static function getOptionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()->allowAdditionalOptions();
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static(static function() { return DataRecords::createEmpty();}, $options);
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
