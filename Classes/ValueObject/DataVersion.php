<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class DataVersion
{
    /**
     * @const int
     */
    private const NONE = -1;

    /**
     * @var int
     */
    private $value;

    private function __construct(int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('version must not be less than 0, given: %d', $value), 1560596325);
        }
        $this->value = $value;
    }

    public static function none(): self
    {
        $instance = new self(0);
        $instance->value = self::NONE;
        return $instance;
    }

    public static function fromNumber(int $value): self
    {
        return new self($value);
    }

    public static function fromDateTime(\DateTimeInterface $date): self
    {
        return new self($date->getTimestamp());
    }

    public static function fromDateString(string $dateString, ?\DateTimeZone $dateTimeZone): self
    {
        try {
            $date = new \DateTimeImmutable($dateString, $dateTimeZone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Could not parse "%s" as date', $dateString), 1560525412);
        }
        return self::fromDateTime($date);
    }

    public static function parse($value): self
    {
        if (\is_array($value)) {
            if (!isset($value['date'])) {
                throw new \InvalidArgumentException('Could not extract date from array', 1560525257);
            }
            $timezone = isset($value['timezone']) ? new \DateTimeZone($value['timezone']) : null;
            return self::fromDateString($value['date'], $timezone);
        }
        if ($value instanceof \DateTimeInterface) {
            return self::fromDateTime($value);
        }
        if (is_numeric($value)) {
            return new self((int)$value);
        }
        if (\is_string($value)) {
            return self::fromDateString($value, null);
        }
        if (\is_object($value)) {
            throw new \InvalidArgumentException(sprintf('Could not parse object of type %s as DataVersion', get_class($value)), 1560523738);
        }
        throw new \InvalidArgumentException(sprintf('Could not parse %s "%s" as DataVersion', gettype($value), $value), 1560526428);
    }

    public function toNumber(): int
    {
        return $this->value;
    }

    public function isHigherThan(DataVersion $otherVersion): bool
    {
        return $this->value > $otherVersion->value;
    }

    public function isNotSet(): bool
    {
        return $this->value === self::NONE;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }


}
