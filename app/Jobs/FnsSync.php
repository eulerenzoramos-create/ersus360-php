<?php

declare(strict_types=1);

namespace Ersus360\Jobs;

use Ersus360\Core\Database;
use Ersus360\Repositories\FnsRepository;
use Ersus360\Repositories\MunicipioRepository;
use Ersus360\Services\FnsService;
use Ersus360\Integrations\ConsultaFnsClient;
use Ersus360\Integrations\ApiFnsClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Job de sincronização FNS — executado via supervisord (1h).
 * php artisan fns:sincronizar [--municipio=1300144] [--competencia=YYYY-MM]
 */
final class FnsSync
{
    public function handle(array $args = []): void
    {
        $logger = new Logger('fns-sync');
        $logger->pushHandler(new StreamHandler('php://stdout'));

        $db = Database::fromEnv();

        $municipioRepo = new MunicipioRepository($db);
        $fnsRepo       = new FnsRepository($db);
        $consultaFns   = new ConsultaFnsClient($logger);
        $apiFns        = new ApiFnsClient($logger);
        $fnsService    = new FnsService($fnsRepo, $consultaFns, $apiFns, $logger);

        // Filtro de município (opcional)
        $ibgeFiltro = $this->argValor($args, '--municipio');

        // Competência: padrão = mês atual; também sincroniza o mês anterior
        $competenciaFiltro = $this->argValor($args, '--competencia');
        $competencias = $competenciaFiltro
            ? [$competenciaFiltro]
            : [date('Y-m'), date('Y-m', strtotime('-1 month'))];

        $municipios = $municipioRepo->listar();

        if ($ibgeFiltro) {
            $municipios = array_filter($municipios, fn($m) => $m['codigo_ibge'] === $ibgeFiltro);
        }

        foreach ($municipios as $municipio) {
            foreach ($competencias as $competencia) {
                $inicio = microtime(true);
                $logger->info("Sincronizando FNS", [
                    'municipio'   => $municipio['nome'],
                    'competencia' => $competencia,
                ]);

                try {
                    $resultado = $fnsService->sincronizarScraping(
                        (int) $municipio['id'],
                        (string) $municipio['codigo_ibge'],
                        $competencia,
                    );

                    $duracao = round(microtime(true) - $inicio, 2);
                    $logger->info("FNS OK", array_merge($resultado, ['duracao_s' => $duracao]));
                } catch (\Throwable $e) {
                    $logger->error("FNS ERRO", [
                        'municipio'  => $municipio['nome'],
                        'competencia' => $competencia,
                        'erro'       => $e->getMessage(),
                    ]);
                }
            }
        }

        $logger->info('FnsSync concluído.');
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
