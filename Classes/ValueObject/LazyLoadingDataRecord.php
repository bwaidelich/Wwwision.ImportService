<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class LazyLoadingDataRecord implements DataRecordInterface
{
    private bool $isLoaded = false;

    /**
     * @var array<string, mixed>|null
     */
    private array|null $attributes = null;

    private function __construct(
        public readonly DataId $id,
        public readonly \Closure $lazyLoadingCallback,
        public readonly DataVersion $version
    ) {
    }

    public static function fromIdAndClosure(DataId $id, \Closure $lazyLoadingCallback): self
    {
        return new self($id, $lazyLoadingCallback, DataVersion::none());
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

    public function version(): DataVersion
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

    public function attribute(string $attributeName): mixed
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
