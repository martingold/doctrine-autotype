<?php

declare(strict_types=1);

namespace MartinGold\AutoType\DynamicType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;
use MartinGold\AutoType\Exception\ShouldNotHappen;
use MartinGold\AutoType\Exception\UnsupportedType;

use function call_user_func;
use function is_callable;
use function is_object;
use function is_string;

class StringDynamicType extends StringType implements DynamicType
{
    /**
     * @var class-string<object>
     */
    private string $class;

    private string $getterMethodName;

    private string|null $constructorMethodName = null;

    /**
     * @param class-string<object> $class
     */
    public static function create(
        string $class, string $getterMethodName, string|null $constructorMethodName = null,
    ): self {
        $type = new self();
        $type->class = $class;
        $type->constructorMethodName = $constructorMethodName;
        $type->getterMethodName = $getterMethodName;

        return $type;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): object|null
    {
        if ($value !== null && !is_string($value)) {
            throw new UnsupportedType('Value must be string');
        }

        if ($value === null) {
            return null;
        }

        if ($this->constructorMethodName === null) {
            return new $this->class($value);
        }

        $factoryCallable = [$this->class, $this->constructorMethodName];

        if (!is_callable($factoryCallable)) {
            throw new ShouldNotHappen("$this->class::$this->constructorMethodName() is not callable.");
        }

        /** @var object|null $valueObject */
        $valueObject = call_user_func($factoryCallable, $value);

        return $valueObject;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_object($value)) {
            throw new InvalidType('Unsupported type ' . get_debug_type($value));
        }

        $getterCallable = [$value, $this->getterMethodName];

        if (!is_callable($getterCallable)) {
            throw new ShouldNotHappen("$this->class::$this->getterMethodName() is not callable.");
        }

        $stringValue = call_user_func($getterCallable);
        if ($stringValue !== null && !is_string($stringValue)) {
            $class = $value::class;
            $type = get_debug_type($stringValue);
            throw new ShouldNotHappen("{$class}::{$this->getterMethodName} must return a string. {$type} returned.");
        }

        return $stringValue;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return false;
    }
}
