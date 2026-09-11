<?php

declare(strict_types=1);

/**
 * Registro de rotas da API REST do ERSUS360.
 *
 * Convenções:
 *   - Todas as rotas são prefixadas com /api
 *   - Rotas protegidas usam AuthMiddleware
 *   - Autorização por módulo usa PermissaoMiddleware::para('modulo')
 *   - Controladores são resolvidos via Container (auto-wiring)
 */

use Ersus360\Core\Router;
use Ersus360\Core\Container;
use Ersus360\Middleware\AuthMiddleware;
use Ersus360\Middleware\PermissaoMiddleware;
use Ersus360\Controllers\Auth\LoginController;
use Ersus360\Controllers\Auth\UsuarioController;
use Ersus360\Controllers\MunicipioController;
use Ersus360\Controllers\FnsController;
use Ersus360\Controllers\ApsController;
use Ersus360\Controllers\InvestSusController;
use Ersus360\Controllers\FolhaController;
use Ersus360\Controllers\PortariasController;
use Ersus360\Controllers\AlertasController;
use Ersus360\Controllers\IndicadoresController;
use Ersus360\Controllers\CnesController;

/** @var Router    $router    */
/** @var Container $container */

// ─────────────────────────────────────────────────────────────
// AUTH — rotas públicas
// ─────────────────────────────────────────────────────────────
$router->post('/api/auth/login',  fn($req) => $container->make(LoginController::class)->login($req));

// Rotas autenticadas de auth
$router->group('/api/auth', [AuthMiddleware::class], function (Router $r) use ($container): void {
    $r->post('/logout',       fn($req) => $container->make(LoginController::class)->logout($req));
    $r->post('/renovar',      fn($req) => $container->make(LoginController::class)->renovar($req));
    $r->post('/trocar-senha', fn($req) => $container->make(LoginController::class)->trocarSenha($req));
});

// ─────────────────────────────────────────────────────────────
// USUÁRIOS
// ─────────────────────────────────────────────────────────────
$router->group('/api/usuarios', [AuthMiddleware::class, PermissaoMiddleware::para('usuarios')], function (Router $r) use ($container): void {
    $r->get('',       fn($req) => $container->make(UsuarioController::class)->index($req));
    $r->post('',      fn($req) => $container->make(UsuarioController::class)->store($req));
    $r->get('/{id}',  fn($req) => $container->make(UsuarioController::class)->show($req));
    $r->put('/{id}',  fn($req) => $container->make(UsuarioController::class)->update($req));
    $r->delete('/{id}', fn($req) => $container->make(UsuarioController::class)->destroy($req));
});

// ─────────────────────────────────────────────────────────────
// MUNICÍPIOS
// ─────────────────────────────────────────────────────────────
$router->group('/api/municipios', [AuthMiddleware::class], function (Router $r) use ($container): void {
    $r->get('',       fn($req) => $container->make(MunicipioController::class)->index($req));
    $r->get('/{id}',  fn($req) => $container->make(MunicipioController::class)->show($req));
    $r->get('/{id}/dashboard', fn($req) => $container->make(MunicipioController::class)->dashboard($req));

    // Criação/edição requer permissão de admin
    $r->post('',      fn($req) => $container->make(MunicipioController::class)->store($req));
    $r->put('/{id}',  fn($req) => $container->make(MunicipioController::class)->update($req));
});

// ─────────────────────────────────────────────────────────────
// FNS — Transferências do Fundo Nacional de Saúde
// ─────────────────────────────────────────────────────────────
$router->group('/api/fns', [AuthMiddleware::class, PermissaoMiddleware::para('fns')], function (Router $r) use ($container): void {
    $r->get('',                   fn($req) => $container->make(FnsController::class)->index($req));
    $r->get('/resumo',            fn($req) => $container->make(FnsController::class)->resumo($req));
    $r->get('/grafico',           fn($req) => $container->make(FnsController::class)->grafico($req));
    $r->get('/exportar',          fn($req) => $container->make(FnsController::class)->exportar($req));
    $r->get('/{id}',              fn($req) => $container->make(FnsController::class)->show($req));
    $r->post('/sincronizar',      fn($req) => $container->make(FnsController::class)->sincronizar($req));
    $r->post('/sincronizar-api',  fn($req) => $container->make(FnsController::class)->sincronizarApi($req));
});

// ─────────────────────────────────────────────────────────────
// APS — Atenção Primária à Saúde (e-Gestor)
// ─────────────────────────────────────────────────────────────
$router->group('/api/aps', [AuthMiddleware::class, PermissaoMiddleware::para('aps')], function (Router $r) use ($container): void {
    $r->get('',              fn($req) => $container->make(ApsController::class)->index($req));
    $r->get('/resumo',       fn($req) => $container->make(ApsController::class)->resumo($req));
    $r->get('/competencias', fn($req) => $container->make(ApsController::class)->competencias($req));
    $r->post('/sincronizar', fn($req) => $container->make(ApsController::class)->sincronizar($req));
    $r->get('/exportar',     fn($req) => $container->make(ApsController::class)->exportar($req));
    $r->get('/diagnostico',  fn($req) => $container->make(ApsController::class)->diagnostico($req));
});

// ─────────────────────────────────────────────────────────────
// INVESTSUS — Emendas Parlamentares / Propostas
// ─────────────────────────────────────────────────────────────
$router->group('/api/emendas', [AuthMiddleware::class, PermissaoMiddleware::para('emendas')], function (Router $r) use ($container): void {
    $r->get('',        fn($req) => $container->make(InvestSusController::class)->index($req));
    $r->get('/resumo', fn($req) => $container->make(InvestSusController::class)->resumo($req));
    $r->post('',       fn($req) => $container->make(InvestSusController::class)->store($req));
    $r->get('/{id}',   fn($req) => $container->make(InvestSusController::class)->show($req));
    $r->put('/{id}',   fn($req) => $container->make(InvestSusController::class)->update($req));
    $r->delete('/{id}', fn($req) => $container->make(InvestSusController::class)->destroy($req));
});

// ─────────────────────────────────────────────────────────────
// FOLHA DE PAGAMENTO / PRESENÇA
// ─────────────────────────────────────────────────────────────
$router->group('/api/folha', [AuthMiddleware::class, PermissaoMiddleware::para('folha')], function (Router $r) use ($container): void {
    $r->get('/funcionarios',              fn($req) => $container->make(FolhaController::class)->funcionarios($req));
    $r->get('/presenca',                  fn($req) => $container->make(FolhaController::class)->presenca($req));
    $r->post('/presenca',                 fn($req) => $container->make(FolhaController::class)->salvarPresenca($req));
    $r->post('/funcionarios',             fn($req) => $container->make(FolhaController::class)->adicionarFuncionario($req));
    $r->delete('/funcionarios/{id}',      fn($req) => $container->make(FolhaController::class)->removerFuncionario($req));
    $r->put('/funcionarios/{id}/status',  fn($req) => $container->make(FolhaController::class)->atualizarStatus($req));
    $r->get('/exportar',                  fn($req) => $container->make(FolhaController::class)->exportar($req));
});

// ─────────────────────────────────────────────────────────────
// PORTARIAS — Diário Oficial da União
// ─────────────────────────────────────────────────────────────
$router->group('/api/portarias', [AuthMiddleware::class, PermissaoMiddleware::para('portarias')], function (Router $r) use ($container): void {
    $r->get('',              fn($req) => $container->make(PortariasController::class)->index($req));
    $r->get('/{id}',         fn($req) => $container->make(PortariasController::class)->show($req));
    $r->post('/sincronizar', fn($req) => $container->make(PortariasController::class)->sincronizar($req));
    $r->post('/notificar',   fn($req) => $container->make(PortariasController::class)->notificar($req));
    $r->put('/{id}/lida',    fn($req) => $container->make(PortariasController::class)->marcarLida($req));
});

// ─────────────────────────────────────────────────────────────
// ALERTAS
// ─────────────────────────────────────────────────────────────
$router->group('/api/alertas', [AuthMiddleware::class], function (Router $r) use ($container): void {
    $r->get('',              fn($req) => $container->make(AlertasController::class)->index($req));
    $r->put('/{id}/lido',    fn($req) => $container->make(AlertasController::class)->marcarLido($req));
    $r->post('/marcar-todos', fn($req) => $container->make(AlertasController::class)->marcarTodosLidos($req));
});

// ─────────────────────────────────────────────────────────────
// INDICADORES / DASHBOARD
// ─────────────────────────────────────────────────────────────
$router->group('/api/indicadores', [AuthMiddleware::class, PermissaoMiddleware::para('indicadores')], function (Router $r) use ($container): void {
    $r->get('',            fn($req) => $container->make(IndicadoresController::class)->index($req));
    $r->get('/dashboard',  fn($req) => $container->make(IndicadoresController::class)->dashboard($req));
    $r->post('/sincronizar', fn($req) => $container->make(IndicadoresController::class)->sincronizar($req));
});

// ─────────────────────────────────────────────────────────────
// CNES — Cadastro Nacional de Estabelecimentos de Saúde
// ─────────────────────────────────────────────────────────────
$router->group('/api/cnes', [AuthMiddleware::class, PermissaoMiddleware::para('cnes')], function (Router $r) use ($container): void {
    $r->get('/estabelecimentos', fn($req) => $container->make(CnesController::class)->estabelecimentos($req));
    $r->get('/equipes',          fn($req) => $container->make(CnesController::class)->equipes($req));
    $r->get('/profissionais',    fn($req) => $container->make(CnesController::class)->profissionais($req));
    $r->post('/sincronizar',     fn($req) => $container->make(CnesController::class)->sincronizar($req));
});

// ─────────────────────────────────────────────────────────────
// FOLHA — rota extra: importação legada (migração única)
// ─────────────────────────────────────────────────────────────
$router->group('/api/folha', [AuthMiddleware::class, PermissaoMiddleware::para('admin')], function (Router $r) use ($container): void {
    $r->post('/importar-legacy', fn($req) => $container->make(FolhaController::class)->importarLegacy($req));
});

// ─────────────────────────────────────────────────────────────
// HEALTH CHECK — sem autenticação
// ─────────────────────────────────────────────────────────────
$router->get('/api/health', fn() => \Ersus360\Core\Response::json([
    'status'  => 'ok',
    'version' => '2.0.0',
    'runtime' => 'PHP ' . PHP_VERSION,
    'ts'      => date('c'),
]));
