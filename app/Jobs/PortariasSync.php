<?php

declare(strict_types=1);

namespace Ersus360\Jobs;

use Ersus360\Core\Database;
use Ersus360\Repositories\PortariasRepository;
use Ersus360\Repositories\MunicipioRepository;
use Ersus360\Repositories\UsuarioRepository;
use Ersus360\Services\PortariasService;
use Ersus360\Integrations\DouClient;
use Ersus360\Integrations\ResendClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Job de scraping DOU — executado via supervisord (24h).
 * php artisan portarias:sincronizar [--data=YYYY-MM-DD]
 */
final class PortariasSync
{
    public function handle(array $args = []): void
    {
        $logger = new Logger('portarias-sync');
        $logger->pushHandler(new StreamHandler('php://stdout'));

        $db = Database::fromEnv();

        $municipioRepo  = new MunicipioRepository($db);
        $portariasRepo  = new PortariasRepository($db);
        $usuarioRepo    = new UsuarioRepository($db);
        $dou            = new DouClient($logger);
        $resend         = new ResendClient($logger);
        $portariasService = new PortariasService($portariasRepo, $usuarioRepo, $dou, $resend, $logger);

        $data = $this->argValor($args, '--data') ?? date('Y-m-d');
        $municipios = $municipioRepo->listar();

        foreach ($municipios as $municipio) {
            $logger->info("Sincronizando portarias", [
                'municipio' => $municipio['nome'],
                'data'      => $data,
            ]);

            try {
                $resultado = $portariasService->sincronizar(
                    (int) $municipio['id'],
                    (string) $municipio['nome'],
                    $data,
                );

                $logger->info("Portarias OK", $resultado);
            } catch (\Throwable $e) {
                $logger->error("Portarias ERRO", [
                    'municipio' => $municipio['nome'],
                    'erro'      => $e->getMessage(),
                ]);
            }
        }

        $logger->info('PortariasSync concluído.');
    }

    private function argValor(array $args, string $nome): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $nome . '=')) {
                return substr($arg, strlen($nome) + 1);
            }
        }
        return null;
    }
}
