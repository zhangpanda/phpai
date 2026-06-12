<?php

declare(strict_types=1);

namespace Synapse\StructuredOutput;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

final class SchemaExtractor
{
    /**
     * @param class-string $className
     * @return array<string, mixed> JSON Schema
     */
    public function extract(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $classAttr = $reflection->getAttributes(AsOutput::class)[0] ?? null;
        $description = $classAttr?->newInstance()->description ?? '';

        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $paramAttr = $prop->getAttributes(Param::class)[0] ?? null;
            $param = $paramAttr?->newInstance();

            $schema = $this->propertySchema($prop);
            if ($param?->description) {
                $schema['description'] = $param->description;
            }
            if ($param?->enum) {
                $schema['enum'] = $param->enum;
            }

            $properties[$prop->getName()] = $schema;

            if ($param?->required ?? !$prop->getType()?->allowsNull()) {
                // Only required if: explicitly set required:true AND property is not nullable
                $isNullable = $prop->getType()?->allowsNull() ?? false;
                $hasDefault = $prop->hasDefaultValue();
                if (!$isNullable && !$hasDefault) {
                    $required[] = $prop->getName();
                }
            }
        }

        return array_filter([
            'type' => 'object',
            'description' => $description ?: null,
            'properties' => $properties,
            'required' => $required ?: null,
            'additionalProperties' => false,
        ]);
    }

    private function propertySchema(ReflectionProperty $prop): array
    {
        $type = $prop->getType();
        if (!$type instanceof ReflectionNamedType) {
            return ['type' => 'string'];
        }

        return match ($type->getName()) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'string' => ['type' => 'string'],
            default => ['type' => 'string'],
        };
    }
}
