<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\ValueObject\DataRecords;

final class ClosureDataSource implements DataSourceInterface
{
    /**
     * @var \Closure
     */
    private $dataClosure;

    /**
     * @param \Closure callback to be invoked if load() is called
     */
    protected function __construct(\Closure $dataClosure)
    {
        $this->dataClosure = $dataClosure;
    }

    public static function forClosure(\Closure $dataClosure): self
    {
        return new static($dataClosure);
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static(static function() { return DataRecords::createEmpty();});
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
        return \call_user_func($this->dataClosure);
    }
}
