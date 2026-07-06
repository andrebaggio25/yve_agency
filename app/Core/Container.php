<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

final class Container implements ContainerInterface
{
    private static ?self $instance = null;

    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public function bind(string $abstract, callable $factory, bool $singleton = false): void
    {
        $this->bindings[$abstract]   = $factory;
        $this->singletons[$abstract] = $singleton;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bind($abstract, $factory, true);
    }

    public function instance(string $abstract, mixed $concrete): void
    {
        $this->instances[$abstract] = $concrete;
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $result = ($this->bindings[$abstract])($this);

            if ($this->singletons[$abstract] ?? false) {
                $this->instances[$abstract] = $result;
            }

            return $result;
        }

        return $this->resolve($abstract);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    private function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [{$class}]: class not found.");
        }

        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = array_map(function (\ReflectionParameter $param) use ($class) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->make($type->getName());
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new RuntimeException(
                "Cannot resolve parameter [{$param->getName()}] for [{$class}]."
            );
        }, $constructor->getParameters());

        return $reflection->newInstanceArgs($params);
    }
}
