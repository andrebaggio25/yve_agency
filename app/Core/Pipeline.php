<?php

declare(strict_types=1);

namespace App\Core;

class Pipeline
{
    public function __construct(
        private readonly Container $container,
        private readonly array     $pipes,
        private readonly \Closure  $destination,
    ) {}

    public function run(Request $request): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            fn(\Closure $next, string $pipe) => fn(Request $req) =>
                $this->container->make($pipe)->handle($req, $next),
            $this->destination,
        );

        return $pipeline($request);
    }
}
