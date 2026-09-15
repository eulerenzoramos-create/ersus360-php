<?php

declare(strict_types=1);

namespace Ersus360\Core;

use Dotenv\Dotenv;
use Throwable;

/**
 * Bootstrap da aplicação ERSUS 360.
 * Carrega .env, registra serviços, resolve a rota e despacha.
 */
final class App
{
    private static self $instance;
    private Container $container;
    private Router $router;

    private function __construct(private readonly string $rootPath)
    {
        $this->boot();
    }

    public static function create(string $rootPath): self
    {
        self::$instance = new self($rootPath);
        return self::$instance;
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    // ── Boot ─────────────────────────────────────────────────

    private function boot(): void
    {
        $this->loadEnv();
        $this->configurePhp();
        $this->container = new Container();
        $this->registerCoreServices();
        $this->router = new Router($this->container);
        $this->loadRoutes();
    }

    private function loadEnv(): void
    {
        $dotenv = Dotenv::createImmutable($this->rootPath);
        $dotenv->safeLoad();
        $dotenv->required([
            'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
            'JWT_SECRET', 'APP_KEY',
        ])->notEmpty();
    }

    private function configurePhp(): void
    {
        $tz = $_ENV['APP_TIMEZONE'] ?? 'America/Manaus';
        date_default_timezone_set($tz);

        $debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting($debug ? E_ALL : E_ERROR | E_WARNING);
    }

    private function registerCoreServices(): void
    {
        $c = $this->container;

        // Database
        $c->singleton(Database::class, fn() => new Database(
            host:    $_ENV['DB_HOST'],
            port:    (int) ($_ENV['DB_PORT'] ?? 3306),
            dbname:  $_ENV['DB_NAME'],
            user:    $_ENV['DB_USER'],
            pass:    $_ENV['DB_PASS'],
            charset: $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ));

        // Request / Response
        $c->singleton(Request::class, fn() => Request::fromGlobals());

        // Logger (Monolog) — resolve Psr\Log\LoggerInterface
        $c->singleton(\Psr\Log\LoggerInterface::class, function () {
            $log = new \Monolog\Logger('ersus360');
            $log->pushHandler(new \Monolog\Handler\StreamHandler('php://stderr', \Monolog\Level::Warning));
            return $log;
        });

        // Serviços core
        $c->singleton(\Ersus360\Services\JwtService::class, fn() =>
            new \Ersus360\Services\JwtService(
                secret:    $_ENV['JWT_SECRET'],
                algorithm: $_ENV['JWT_ALGORITHM'] ?? 'HS256',
                expireMin: (int) ($_ENV['JWT_EXPIRE_MINUTES'] ?? 480),
            )
        );

        $c->singleton(\Ersus360\Services\AuditService::class, fn() =>
            new \Ersus360\Services\AuditService($c->get(Database::class))
        );

        $c->singleton(\Ersus360\Services\PermissaoService::class,
            fn() => new \Ersus360\Services\PermissaoService()
        );

        // Repositories
        $c->singleton(\Ersus360\Repositories\FnsRepository::class,
            fn() => new \Ersus360\Repositories\FnsRepository($c->get(Database::class))
        );
        $c->singleton(\Ersus360\Repositories\MunicipioRepository::class,
            fn() => new \Ersus360\Repositories\MunicipioRepository($c->get(Database::class))
        );
        $c->singleton(\Ersus360\Repositories\FolhaRepository::class,
            fn() => new \Ersus360\Repositories\FolhaRepository($c->get(Database::class))
        );
        $c->singleton(\Ersus360\Repositories\PortariasRepository::class,
            fn() => new \Ersus360\Repositories\PortariasRepository($c->get(Database::class))
        );

        // Integrations
        $c->singleton(\Ersus360\Integrations\ConsultaFnsClient::class,
            fn() => new \Ersus360\Integrations\ConsultaFnsClient($c->get(\Psr\Log\LoggerInterface::class))
        );
        $c->singleton(\Ersus360\Integrations\ApiFnsClient::class,
            fn() => new \Ersus360\Integrations\ApiFnsClient($c->get(\Psr\Log\LoggerInterface::class))
        );

        // Services com dependências complexas
        $c->singleton(\Ersus360\Services\FnsService::class, fn() =>
            new \Ersus360\Services\FnsService(
                $c->get(\Ersus360\Repositories\FnsRepository::class),
                $c->get(\Ersus360\Integrations\ConsultaFnsClient::class),
                $c->get(\Ersus360\Integrations\ApiFnsClient::class),
                $c->get(\Psr\Log\LoggerInterface::class),
            )
        );
    }

    private function loadRoutes(): void
    {
        $router    = $this->router;
        $container = $this->container;
        require $this->rootPath . '/routes/api.php';
    }

    // ── Run ──────────────────────────────────────────────────

    public function run(): void
    {
        try {
            $this->setCorsHeaders();

            $request = $this->container->get(Request::class);

            // Preflight CORS
            if ($request->method() === 'OPTIONS') {
                http_response_code(204);
                exit;
            }

            $response = $this->router->dispatch($request);
            $response->send();
        } catch (\Ersus360\Exceptions\HttpException $e) {
            $this->sendError($e->getStatusCode(), $e->getMessage());
        } catch (Throwable $e) {
            $debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
            $msg   = $debug ? $e->getMessage() : 'Erro interno do servidor.';
            error_log('[ERSUS360] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->sendError(500, $msg);
        }
    }

    private function setCorsHeaders(): void
    {
        $allowed = explode(',', $_ENV['CORS_ORIGINS'] ?? '*');
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowed, true) || in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            header('Access-Control-Max-Age: 86400');
        }
    }

    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => $message, 'codigo' => $code], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
