<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Wwwision\ImportService\ValueObject\DataRecordInterface;

/**
 * Data mapper that can be used from data targets
 */
final class Mapper
{

    public function __construct(
        private readonly EelEvaluator $eelEvaluator,
        private readonly array $mapping
    ) {
        foreach ($mapping as $name => $rule) {
            if (!\is_string($rule)) {
                throw new \InvalidArgumentException(sprintf('Mapping rules have to be strings, got %s for mapping "%s"', \get_debug_type($rule), $name), 1558096675);
            }
        }
    }

    public function mapRecord(DataRecordInterface $record, array $additionalVariables): array
    {
        $result = [];
        foreach ($this->mapping as $targetColumnName => $configuration) {
            $result[$targetColumnName] = $this->attributeValueForColumn($record, $targetColumnName, $additionalVariables);
        }
        return $result;
    }

    private function attributeValueForColumn(DataRecordInterface $record, string $columnName, array $additionalVariables)
    {
        if (!isset($this->mapping[$columnName])) {
            throw new \RuntimeException(sprintf('Missing mapping configuration for column "%s"', $columnName), 1558010499);
        }
        $attributeMapping = $this->mapping[$columnName];
        if (!$this->eelEvaluator->isEelExpression($attributeMapping)) {
            return $record->hasAttribute($attributeMapping) ? $record->attribute($attributeMapping) : null;
        }
        $variables = $additionalVariables;
        $variables['record'] = $record;
        try {
            return $this->eelEvaluator->evaluate($attributeMapping, $variables);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to map column "%s": %s', $columnName, $e->getMessage()), 1706890124, $e);
        }
    }

}
