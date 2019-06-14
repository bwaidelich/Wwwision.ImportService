<?php
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\ValueObject\DataRecords;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

final class DbalSource implements DataSourceInterface
{

    /**
     * @var array
     */
    private $customBackendOptions;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="persistence.backendOptions")
     * @var array
     */
    protected $flowBackendOptions;

    /**
     * @var Connection
     */
    private $dbal;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $idColumn;

    protected function __construct(array $options)
    {
        if (!isset($options['table'])) {
            throw new \InvalidArgumentException('Missing option "table"', 1557999786);
        }
        $this->tableName = $options['table'];
        $this->idColumn = $options['idColumn'] ?? 'id';
        $this->customBackendOptions = $options['backendOptions'] ?? [];
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static($options);
    }

    /**
     * @throws DBALException
     */
    public function initializeObject(): void
    {
        $backendOptions = Arrays::arrayMergeRecursiveOverrule($this->flowBackendOptions, $this->customBackendOptions);
        $this->dbal = DriverManager::getConnection($backendOptions);
    }

    public function load(): DataRecords
    {
        return DataRecords::fromRawArray($this->dbal->fetchAll('SELECT * FROM ' . $this->dbal->quoteIdentifier($this->tableName)), $this->idColumn);
    }
}
