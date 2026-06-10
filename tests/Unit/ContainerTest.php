<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_bind_and_make(): void
    {
        $this->container->bind('greeting', fn() => 'hello');
        $this->assertEquals('hello', $this->container->make('greeting'));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton('obj', fn() => new \stdClass());

        $a = $this->container->make('obj');
        $b = $this->container->make('obj');

        $this->assertSame($a, $b);
    }

    public function test_bind_factory_is_called_each_time(): void
    {
        $this->container->bind('obj', fn() => new \stdClass());

        $a = $this->container->make('obj');
        $b = $this->container->make('obj');

        $this->assertNotSame($a, $b);
    }

    public function test_instance_returns_given_object(): void
    {
        $obj = new \stdClass();
        $obj->value = 42;

        $this->container->instance('thing', $obj);
        $resolved = $this->container->make('thing');

        $this->assertSame($obj, $resolved);
        $this->assertEquals(42, $resolved->value);
    }

    public function test_has_returns_true_when_registered(): void
    {
        $this->container->bind('foo', fn() => 'bar');
        $this->assertTrue($this->container->has('foo'));
    }

    public function test_has_returns_false_when_not_registered(): void
    {
        $this->assertFalse($this->container->has('unknown'));
    }

    public function test_auto_resolve_class_without_constructor(): void
    {
        $result = $this->container->make(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $result);
    }

    public function test_throws_for_unresolvable_class(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('App\\NonExistent\\SomeClass');
    }
}

// ── Test doubles ──────────────────────────────────────────────────────────────

class SimpleClass
{
    public string $name = 'simple';
}
