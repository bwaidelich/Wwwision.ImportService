<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class DataRecord implements DataRecordInterface
{
    /**
     * @var DataId
     */
    private $id;

    /**
     * @var DataVersion
     */
    private $version;

    /**
     * @var array
     */
    private $attributes;

    private function __construct(DataId $id, array $attributes, DataVersion $version)
    {
        $this->id = $id;
        $this->attributes = $attributes;
        $this->version = $version;
    }

    public static function fromIdAndAttributes(DataId $id, array $attributes): self
    {
        return new self($id, $attributes, DataVersion::none());
    }

    public static function fromIdVersionAndAttributes(DataId $id, DataVersion $version, array $attributes): self
    {
        return new self($id, $attributes, $version);
    }

    public function withId(DataId $newId): self
    {
        return new self($newId, $this->attributes, $this->version);
    }

    public function withAttribute(string $attributeName, $attributeValue): self
    {
        $attributes = $this->attributes;
        $attributes[$attributeName] = $attributeValue;
        return new self($this->id, $attributes, $this->version);
    }

    public function id(): DataId
    {
        return $this->id;
    }

    public function version(): DataVersion
    {
        return $this->version;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $attributeName): bool
    {
        return \array_key_exists($attributeName, $this->attributes);
    }

    public function attribute(string $attributeName)
    {
        if (!$this->hasAttribute($attributeName)) {
            throw new \InvalidArgumentException(sprintf('attribute "%s" is not set!', $attributeName), 1558005761);
        }
        return $this->attributes[$attributeName];
    }

    public function offsetExists($attributeName): bool
    {
        return $this->hasAttribute($attributeName);
    }

    public function offsetGet($attributeName)
    {
        return $this->attribute($attributeName);
    }

    public function offsetSet($attributeName, $value): void
    {
        throw new \RuntimeException('Immutable object must not be modified', 1558097629);
    }

    public function offsetUnset($attributeName): void
    {
        throw new \RuntimeException('Immutable object must not be modified', 1558097630);
    }

    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
