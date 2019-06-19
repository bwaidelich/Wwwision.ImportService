<?php
declare(strict_types=1);
namespace Wwwision\ImportService\ValueObject;

interface DataRecordInterface extends \ArrayAccess, \JsonSerializable
{
    public function id(): DataId;

    public function version(): DataVersion;

    public function attributes(): array;

    public function hasAttribute(string $attributeName): bool;

    public function attribute(string $attributeName);
}
