<?php

declare(strict_types=1);

namespace Ersus360\Core;

use Ersus360\Exceptions\HttpException;

/**
 * IoC Container simples com suporte a singletons e auto-wiring via reflection.
 */
final class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Registra um singleton — instanciado uma única vez.
     * @param class-string $abstract
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Alias de get() — resolve sempre uma nova instância (sem cache de singleton).
     * @param class-string $abstract
     */
    public function make(string $abstract): object
    {
        return $this->resolve($abstract);
    }

    /**
     * Resolve uma dependência.
     * Tenta binding registrado primeiro; depois reflection auto-wiring.
     * @param class-string $abstract
     */
    public function get(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $instance = ($this->bindings[$abstract])($this);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        return $this->resolve($abstract);
    }

    /**
     * Auto-wiring: resolve dependências do construtor por reflection.
     * @param class-string $class
     */
    private function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new HttpException(500, "Classe não encontrada: {$class}");
        }

        $ref         = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new HttpException(500, "Não foi possível resolver: {$class}::\${$param->getName()}");
            }
        }

        $instance = $ref->newInstanceArgs($args);
        $this->instances[$class] = $instance;
        return $instance;
    }
}
