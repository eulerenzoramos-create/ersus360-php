<?php

declare(strict_types=1);

/**
 * ERSUS 360 — Entry Point
 * Todos os requests passam por aqui via nginx rewrite.
 * Rotas /api/* → REST API (JSON)
 * Demais rotas → Frontend SPA (Bootstrap 5.3)
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// ── API requests ──────────────────────────────────────────────
if (str_starts_with($requestPath, '/api/')) {
    require ROOT_PATH . '/vendor/autoload.php';
    $app = \Ersus360\Core\App::create(ROOT_PATH);
    $app->run();
    exit;
}

// ── Static assets (CSS, JS, images served by nginx directly) ──
// Chegam aqui apenas se nginx não encontrou o arquivo.
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff2?)$/', $requestPath)) {
    http_response_code(404);
    exit;
}

// ── Frontend SPA ──────────────────────────────────────────────
// Serve o mesmo HTML para qualquer rota de frontend;
// o roteamento client-side (history API) cuida do resto.
require ROOT_PATH . '/resources/views/app.php';
