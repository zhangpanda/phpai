<?php

declare(strict_types=1);

namespace Synapse\StructuredOutput;

use ReflectionClass;
use ReflectionNamedType;

final class Deserializer
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function deserialize(string $json, string $className): object
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        return $this->hydrate($data, $className);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function hydrate(array $data, string $className): object
    {
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (!array_key_exists($name, $data)) {
                // Check if property requires a value (typed, non-nullable, no default)
                $type = $prop->getType();
                if ($type !== null && !$type->allowsNull() && !$prop->hasDefaultValue()) {
                    throw new \RuntimeException(
                        "Missing required property '{$className}::\${$name}' in JSON data"
                    );
                }
                continue;
            }

            $value = $data[$name];
            $type = $prop->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_array($value)) {
                $value = $this->hydrate($value, $type->getName());
            }

            try {
                $prop->setValue($instance, $value);
            } catch (\TypeError $e) {
                throw new \RuntimeException(
                    "Failed to assign property '{$className}::\${$name}': expected {$prop->getType()}, got " . gettype($value) . " (" . json_encode($value) . ")",
                    0,
                    $e,
                );
            }
        }

        return $instance;
    }
}
