<?php

declare(strict_types=1);

namespace Core;

use ReflectionClass;
use RuntimeException;

final class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        if (!class_exists($id)) {
            throw new RuntimeException("Service not found: {$id}");
        }

        $reflection = new ReflectionClass($id);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $this->instances[$id] = new $id();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type === null || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException("Cannot autowire {$id}::\${$parameter->getName()}");
            }

            $arguments[] = $this->get($type->getName());
        }

        return $this->instances[$id] = $reflection->newInstanceArgs($arguments);
    }
}
