<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Repositories\FnsRepository;
use Ersus360\Integrations\ConsultaFnsClient;
use Ersus360\Integrations\ApiFnsClient;
use Psr\Log\LoggerInterface;

/**
 * Orquestra a coleta e armazenamento de transferências FNS.
 * Credenciais NUNCA ficam aqui — são lidas via env no cliente de integração.
 */
final class FnsService
{
    public function __construct(
        private readonly FnsRepository      $repo,
        private readonly ConsultaFnsClient  $consultaFns,
        private readonly ApiFnsClient       $apiFns,
        private readonly LoggerInterface    $logger,
    ) {}

    /**
     * Scraping via consultafns.saude.gov.br.
     * @return array{novos: int, total: int, erros: int}
     */
    public function sincronizarScraping(int $municipioId, string $codigoIbge, string $competencia): array
    {
        $inicio = microtime(true);
        $novos  = 0;
        $erros  = 0;

        try {
            $registros = $this->consultaFns->listarTransferencias($codigoIbge, $competencia);

            foreach ($registros as $reg) {
                $chave = $this->gerarChave($municipioId, $reg);

                if ($this->repo->chaveExiste($chave)) {
                    continue;
                }

                $this->repo->inserir(array_merge($reg, [
                    'municipio_id' => $municipioId,
                    'chave_unica'  => $chave,
                    'fonte'        => 'scraping',
                ]));
                $novos++;
            }

            $duracao = (int) (microtime(true) - $inicio);
            $this->repo->registrarColeta($municipioId, $competencia, $novos, count($registros), $duracao, 'ok');

            return ['novos' => $novos, 'total' => count($registros), 'erros' => 0];
        } catch (\Throwable $e) {
            $this->logger->error('FNS scraping falhou', [
                'municipio_id' => $municipioId,
                'competencia'  => $competencia,
                'erro'         => $e->getMessage(),
            ]);

            $duracao = (int) (microtime(true) - $inicio);
            $this->repo->registrarColeta($municipioId, $competencia, $novos, 0, $duracao, 'erro', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Coleta via API REST apifns.saude.gov.br.
     * @return array{novos: int, total: int}
     */
    public function sincronizarApi(int $municipioId, string $codigoIbge, string $competencia): array
    {
        $registros = $this->apiFns->detalhesPagamento($codigoIbge, $competencia);
        $novos     = 0;

        foreach ($registros as $reg) {
            $chave = $this->gerarChave($municipioId, $reg);

            if ($this->repo->chaveExiste($chave)) {
                // Atualiza detalhe_url se vier da API e ainda não tinha
                continue;
            }

            $this->repo->inserir(array_merge($reg, [
                'municipio_id' => $municipioId,
                'chave_unica'  => $chave,
                'fonte'        => 'api',
            ]));
            $novos++;
        }

        return ['novos' => $novos, 'total' => count($registros)];
    }

    /** Chave determinística idêntica à lógica Python (SHA-256) */
    private function gerarChave(int $municipioId, array $reg): string
    {
        $partes = implode('|', [
            $municipioId,
            $reg['competencia']     ?? '',
            $reg['numero_parcela']  ?? '',
            $reg['numero_banco']    ?? '',
            $reg['agencia']         ?? '',
            $reg['conta_corrente']  ?? '',
            $reg['bloco']           ?? '',
            $reg['componente']      ?? '',
            (string) ($reg['valor_liquido'] ?? '0'),
        ]);

        return hash('sha256', $partes);
    }
}
