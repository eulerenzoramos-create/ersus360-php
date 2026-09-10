<?php

declare(strict_types=1);

namespace Ersus360\Core;

use Ersus360\Exceptions\HttpException;

/**
 * Router PSR-7-inspired. Suporta parâmetros de rota {id}, middleware por grupo.
 */
final class Router
{
    /** @var array<string, array<string, array{handler: callable, middleware: string[]}>> */
    private array $routes = [];

    /** @var string[] */
    private array $groupMiddleware = [];
    private string $groupPrefix    = '';

    public function __construct(private readonly Container $container) {}

    // ── Registro de rotas ─────────────────────────────────────

    public function get(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    /** Grupo de rotas com prefixo e/ou middleware compartilhados. */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $prevPrefix     = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;

        $this->groupPrefix     = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix     = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    // ── Dispatch ─────────────────────────────────────────────

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $pattern => $route) {
            $params = $this->match($pattern, $path);
            if ($params !== null) {
                $request->setRouteParams($params);
                return $this->runMiddleware(
                    $route['middleware'],
                    $route['handler'],
                    $request,
                );
            }
        }

        throw new HttpException(404, "Rota não encontrada: {$method} {$path}");
    }

    // ── Interno ──────────────────────────────────────────────

    private function add(string $method, string $path, callable $handler, array $middleware): self
    {
        $full = $this->groupPrefix . $path;
        $this->routes[$method][$full] = [
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
        return $this;
    }

    /**
     * Tenta casar padrão de rota com URI. Retorna array de params ou null.
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $uri): ?array
    {
        $regex  = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex  = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Executa cadeia de middleware → handler (closure ou [class, method]).
     * @param string[] $middleware
     */
    private function runMiddleware(array $middleware, callable $handler, Request $request): Response
    {
        $final = function (Request $req) use ($handler): Response {
            return $handler($req);
        };

        $chain = array_reduce(
            array_reverse($middleware),
            function (callable $next, string $mwClass) {
                return function (Request $req) use ($mwClass, $next): Response {
                    $mw = $this->container->get($mwClass);
                    return $mw->handle($req, $next);
                };
            },
            $final,
        );

        return $chain($request);
    }
}
