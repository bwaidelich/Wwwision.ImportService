<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\ValueObject\DataRecordInterface;

/**
 * Data mapper that can be used from data targets
 */
final class Mapper
{
    /**
     * @Flow\Inject
     * @var EelEvaluator
     */
    protected $eelRenderer;

    /**
     * @var array
     */
    private $mapping;

    protected function __construct(array $mapping)
    {
        foreach ($mapping as $name => $rule) {
            if (!\is_string($rule)) {
                throw new \InvalidArgumentException(sprintf('Mapping rules have to be strings, got %s for mapping "%s"', \is_object($rule) ? \get_class($rule) : \gettype($rule), $name), 1558096675);
            }
        }
        $this->mapping = $mapping;
    }

    public static function fromArray(array $mapping): self
    {
        return new static($mapping);
    }

    public function mapRecord(DataRecordInterface $record): array
    {
        $result = [];
        foreach ($this->mapping as $targetColumnName => $configuration) {
            $result[$targetColumnName] = $this->attributeValueForColumn($record, $targetColumnName);
        }
        return $result;
    }

    public function attributeValueForColumn(DataRecordInterface $record, string $columnName)
    {
        if (!isset($this->mapping[$columnName])) {
            throw new \RuntimeException(sprintf('Missing mapping configuration for column "%s"', $columnName), 1558010499);
        }
        $attributeMapping = $this->mapping[$columnName];
        if (!$this->eelRenderer->isEelExpression($attributeMapping)) {
            return $record->hasAttribute($attributeMapping) ? $record->attribute($attributeMapping) : null;
        }
        return $this->eelRenderer->evaluate($attributeMapping, ['record' => $record]);
    }

}
