<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class LazyLoadingDataRecord implements DataRecordInterface
{
    /**
     * @var DataId
     */
    private $id;

    /**
     * @var DataVersion|null
     */
    private $version;

    /**
     * @var \Closure|null
     */
    private $lazyLoadingCallback;

    /**
     * @var bool
     */
    private $isLoaded = false;

    /**
     * @var array
     */
    private $attributes;

    private function __construct(DataId $id, \Closure $lazyLoadingCallback, ?DataVersion $version)
    {
        $this->id = $id;
        $this->lazyLoadingCallback = $lazyLoadingCallback;
        $this->version = $version;
    }

    public static function fromIdAndClosure(DataId $id, \Closure $lazyLoadingCallback): self
    {
        return new self($id, $lazyLoadingCallback, null);
    }

    public static function fromIdClosureAndVersion(DataId $id, \Closure $lazyLoadingCallback, DataVersion $version): self
    {
        return new self($id, $lazyLoadingCallback, $version);
    }

    private function load(): void
    {
        if ($this->isLoaded) {
            return;
        }
        $this->attributes = \call_user_func($this->lazyLoadingCallback, $this);
        $this->isLoaded = true;
    }

    public function id(): DataId
    {
        return $this->id;
    }

    public function hasVersion(): bool
    {
        return $this->version !== null;
    }

    public function version(): ?DataVersion
    {
        return $this->version;
    }

    public function attributes(): array
    {
        $this->load();
        return $this->attributes;
    }

    public function hasAttribute(string $attributeName): bool
    {
        $this->load();
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
