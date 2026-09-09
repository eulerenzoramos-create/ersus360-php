<?php

declare(strict_types=1);

namespace Ersus360\Integrations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Ersus360\Exceptions\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Scraping de consultafns.saude.gov.br.
 *
 * A URL base e credenciais NUNCA são hardcoded.
 * Lidas exclusivamente de variáveis de ambiente.
 *
 * Env vars necessárias:
 *   CONSULTAFNS_URL  (ex: https://consultafns.saude.gov.br)
 *   ESUS_USUARIO     (CPF do operador — Railway env var)
 *   ESUS_SENHA       (Senha — Railway env var)
 */
final class ConsultaFnsClient
{
    private readonly Client $http;
    private readonly string $baseUrl;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->baseUrl = rtrim($_ENV['CONSULTAFNS_URL'] ?? 'https://consultafns.saude.gov.br', '/');

        $this->http = new Client([
            'base_uri'        => $this->baseUrl,
            'timeout'         => 30,
            'connect_timeout' => 10,
            'verify'          => true,
            'headers'         => [
                'User-Agent' => 'ERSUS360/2.0 SMS-Apui-AM',
                'Accept'     => 'application/json, text/html, */*',
            ],
        ]);
    }

    /**
     * Lista transferências de um município em uma competência.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTransferencias(string $codigoIbge, string $competencia): array
    {
        // competencia formato: 'YYYY-MM' → converte para formato da API
        [$ano, $mes] = explode('-', $competencia);

        try {
            $response = $this->http->get('/fns/api/transferenciaFns/listarTransferenciaFns', [
                'query' => [
                    'coIbge'       => $codigoIbge,
                    'nuAno'        => $ano,
                    'nuMes'        => ltrim($mes, '0'),
                    'tpFiltro'     => 'IBGE',
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $this->normalizar($data, $competencia);
        } catch (GuzzleException $e) {
            $this->logger->error('ConsultaFNS HTTP error', ['erro' => $e->getMessage()]);
            throw new HttpException(502, 'Erro ao consultar FNS: ' . $e->getMessage());
        } catch (\JsonException $e) {
            $this->logger->error('ConsultaFNS JSON parse error', ['erro' => $e->getMessage()]);
            throw new HttpException(502, 'Resposta inválida do FNS.');
        }
    }

    /**
     * Busca detalhe de uma transferência específica pelo link scraped.
     *
     * @return array<string, mixed>
     */
    public function detalheTransferencia(string $url): array
    {
        try {
            $response = $this->http->get($url);
            $body     = (string) $response->getBody();
            $data     = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return $data ?? [];
        } catch (\Throwable $e) {
            $this->logger->warning('ConsultaFNS detalhe falhou', ['url' => $url, 'erro' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normaliza o array de resposta para o formato do banco.
     *
     * @param mixed  $raw
     * @return array<int, array<string, mixed>>
     */
    private function normalizar(mixed $raw, string $competencia): array
    {
        if (!is_array($raw)) {
            return [];
        }

        // A API pode retornar diretamente o array ou dentro de uma chave
        $lista = isset($raw[0]) ? $raw : ($raw['lista'] ?? $raw['transferencias'] ?? []);

        return array_map(fn(array $item) => [
            'competencia'    => $competencia . '-01',
            'numero_parcela' => $item['nuParcela']       ?? null,
            'numero_banco'   => $item['noBanco']         ?? $item['nrBanco'] ?? null,
            'agencia'        => $item['nuAgencia']       ?? null,
            'conta_corrente' => $item['nuContaCorrente'] ?? null,
            'acao'           => $item['dsAcao']          ?? $item['noAcao'] ?? null,
            'programa'       => $item['dsPrograma']      ?? $item['noPrograma'] ?? null,
            'bloco'          => $item['dsBloco']         ?? $item['noBloco'] ?? null,
            'subprograma'    => $item['dsSubPrograma']   ?? null,
            'componente'     => $item['dsComponente']    ?? $item['noComponente'] ?? null,
            'detalhe_url'    => $item['linkDetalhe']     ?? $item['urlDetalhe'] ?? null,
            'valor_bruto'    => (float) ($item['vlBruto']    ?? $item['vlTransferencia'] ?? 0),
            'valor_desconto' => (float) ($item['vlDesconto'] ?? 0),
            'valor_liquido'  => (float) ($item['vlLiquido']  ?? $item['vlTransferencia'] ?? 0),
            'tipo_operacao'  => $item['dsTipoOperacao']  ?? null,
            'situacao'       => $item['dsSituacao']      ?? $item['noSituacao'] ?? null,
            'data_credito'   => isset($item['dtCredito'])
                                ? date('Y-m-d', strtotime($item['dtCredito']))
                                : null,
        ], $lista);
    }
}
