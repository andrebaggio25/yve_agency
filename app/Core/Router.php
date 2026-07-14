<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var string[] — middlewares do grupo atual */
    private array $groupMiddlewares = [];

    public function __construct(private readonly Container $container) {}

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function get(string $uri, array $handler, array $middlewares = []): void
    {
        $this->add('GET', $uri, $handler, $middlewares);
    }

    public function post(string $uri, array $handler, array $middlewares = []): void
    {
        $this->add('POST', $uri, $handler, $middlewares);
    }

    public function put(string $uri, array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $uri, $handler, $middlewares);
    }

    public function patch(string $uri, array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $uri, $handler, $middlewares);
    }

    public function delete(string $uri, array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $uri, $handler, $middlewares);
    }

    /** Aceita qualquer método HTTP — útil para webhooks e endpoints de cron */
    public function any(string $uri, array $handler, array $middlewares = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'] as $method) {
            $this->add($method, $uri, $handler, $middlewares);
        }
    }

    /** Agrupa rotas com middlewares compartilhados */
    public function group(array $middlewares, \Closure $callback): void
    {
        $previous              = $this->groupMiddlewares;
        $this->groupMiddlewares = array_merge($previous, $middlewares);
        $callback($this);
        $this->groupMiddlewares = $previous;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function dispatch(Request $request): void
    {
        $this->handle($request)->send();
    }

    /**
     * Resolve a rota e devolve a Response **sem enviá-la**.
     *
     * Separado do `dispatch()` para que os testes de feature (QA-03) possam
     * exercitar o pipeline real — rota → middlewares → controller → banco — e
     * inspecionar o resultado, em vez de dar `echo` na saída do PHPUnit.
     */
    public function handle(Request $request): Response
    {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            if ($route->method !== $method) {
                continue;
            }

            if (!preg_match($route->pattern, $path, $matches)) {
                continue;
            }

            // Extract named route params (/clients/{id} → ['id' => '42'])
            $params = array_filter(
                $matches,
                fn($key) => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );
            $request->setParams($params);

            try {
                return $this->runRoute($route, $request);
            } catch (HttpException $e) {
                // Guard de autorização abortou (401/403) — resposta já pronta.
                return $e->getResponse();
            } catch (\Throwable $e) {
                return $this->handleError($e);
            }
        }

        return $this->handleNotFound($request);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function add(string $method, string $uri, array $handler, array $middlewares): void
    {
        $this->routes[] = new Route(
            method:      $method,
            uri:         $uri,
            pattern:     $this->compile($uri),
            handler:     $handler,
            middlewares: array_merge($this->groupMiddlewares, $middlewares),
        );
    }

    private function compile(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    private function runRoute(Route $route, Request $request): Response
    {
        [$class, $action] = $route->handler;

        $destination = function (Request $req) use ($class, $action): Response {
            /** @var Controller $controller */
            $controller = $this->container->make($class);
            return $controller->$action($req);
        };

        return (new Pipeline($this->container, $route->middlewares, $destination))->run($request);
    }

    private function handleNotFound(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => 'Not found'], 404);
        }

        if (file_exists(resource_path('views/errors/404.php'))) {
            return Response::view('errors.404', [], 404);
        }

        return Response::text('404 — Página não encontrada', 404);
    }

    private function handleError(\Throwable $e): Response
    {
        $isDev = env('APP_ENV', 'production') === 'development';

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            $body = ['error' => 'Internal server error'];
            if ($isDev) {
                $body['message'] = $e->getMessage();
                $body['trace']   = explode("\n", $e->getTraceAsString());
            }
            return Response::json($body, 500);
        }

        $message = $isDev ? $e->getMessage() : null;
        $trace   = $isDev ? $e->getTraceAsString() : null;

        if (file_exists(resource_path('views/errors/500.php'))) {
            return Response::view('errors.500', compact('message', 'trace'), 500);
        }

        return Response::text('500 — Erro interno', 500);
    }
}
