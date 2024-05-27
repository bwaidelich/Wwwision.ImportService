<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Neos\Utility\TypeHandling;

/**
 * Simple array schema validation.
 *
 * Usage:
 *
 * OptionsSchema::create()
 *   ->requires('someRequiredString', 'string')
 *   ->has('someOptionalString', 'string')
 *   ->has('someOptionalInt', 'integer')
 *   ->allowAdditionalOptions()
 *   ->validate($options);
 *
 * With the allowAdditionalOptions() call _additional_ options are ignored
 */
final class OptionsSchema
{

    protected function __construct(
        private readonly array $schema,
        private readonly bool $allowAdditionalOptions
    ) {
    }

    /**
     * Named constructor for this class
     */
    public static function create(): self
    {
        return new static([], false);
    }

    /**
     * Add some required option to the schema
     *
     * @param string $optionName name of the required option to add
     * @param string $type expected type of the option ('string', 'array', 'boolean', 'integer', 'callable')
     * @return OptionsSchema
     */
    public function requires(string $optionName, string $type): self
    {
        $schema = $this->schema;
        $schema[$optionName] = ['required' => true, 'type' => $type];
        return new static($schema, $this->allowAdditionalOptions);
    }

    /**
     * Add some optional option to the schema
     *
     * @param string $optionName name of the optional option to add
     * @param string $type expected type of the option ('string', 'array', 'boolean', 'integer', 'callable')
     * @return OptionsSchema
     */
    public function has(string $optionName, string $type): self
    {
        $schema = $this->schema;
        $schema[$optionName] = ['required' => false, 'type' => $type];
        return new static($schema, $this->allowAdditionalOptions);
    }

    /**
     * Ignore any additional options that have not been explicitly added to the schema
     *
     * @return OptionsSchema
     */
    public function allowAdditionalOptions(): self
    {
        return new static($this->schema, true);
    }

    /**
     * Validate the given $options array against this schema.
     *
     * @param array $options
     * @throws \InvalidArgumentException if the options don't adhere to the schema
     */
    public function validate(array $options): void
    {
        $uncoveredOptions = $options;
        foreach ($this->schema as $optionName => $optionSchema) {
            if (!isset($options[$optionName])) {
                if ($optionSchema['required']) {
                    throw new \InvalidArgumentException(sprintf('Missing required option "%s"', $optionName), 1563961736);
                }
                continue;
            }
            $expectedType = $optionSchema['type'] ?? 'string';
            $actualType = TypeHandling::getTypeForValue($options[$optionName]);
            if ($expectedType === 'callable') {
                if (!\is_callable($options[$optionName], true)) {
                    throw new \InvalidArgumentException(sprintf('Option "%s" must be a callable in the format: "Some\ClassName::someMethodName" but it is: "%s" and that is not callable', $optionName, \is_string($options[$optionName]) ? $options[$optionName] : $actualType), 1563962182);
                }
            } elseif ($actualType !== $expectedType) {
                throw new \InvalidArgumentException(sprintf('Option "%s" must be of type %s but it is a %s', $optionName, $expectedType, $actualType), 1563962182);
            }
            unset($uncoveredOptions[$optionName]);
        }
        if (!$this->allowAdditionalOptions && $uncoveredOptions !== []) {
            $errorMessage = \count($uncoveredOptions) === 1 ? 'The following option is not supported: "%s"' : 'The following options are not supported: "%s"';
            throw new \InvalidArgumentException(sprintf($errorMessage, implode('", "', array_keys($uncoveredOptions))), 1563961836);
        }
    }


}
