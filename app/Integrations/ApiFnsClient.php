<?php

declare(strict_types=1);

namespace Ersus360\Integrations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Ersus360\Exceptions\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Cliente para apifns.saude.gov.br — API REST oficial do FNS.
 *
 * Env vars:
 *   APIFNS_URL   (ex: https://apifns.saude.gov.br)
 *   APIFNS_TOKEN (Bearer token — Railway env var, se exigido)
 */
final class ApiFnsClient
{
    private readonly Client $http;
    private readonly string $baseUrl;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->baseUrl = rtrim($_ENV['APIFNS_URL'] ?? 'https://apifns.saude.gov.br', '/');

        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'ERSUS360/2.0 SMS-Apui-AM',
        ];

        // Token opcional — alguns endpoints são públicos
        if (!empty($_ENV['APIFNS_TOKEN'])) {
            $headers['Authorization'] = 'Bearer ' . $_ENV['APIFNS_TOKEN'];
        }

        $this->http = new Client([
            'base_uri'        => $this->baseUrl,
            'timeout'         => 30,
            'connect_timeout' => 10,
            'verify'          => true,
            'headers'         => $headers,
        ]);
    }

    /**
     * Detalhes de pagamentos via API REST.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detalhesPagamento(string $codigoIbge, string $competencia): array
    {
        [$ano, $mes] = explode('-', $competencia);

        try {
            $response = $this->http->get('/fns/api/pagamento/detalhes', [
                'query' => [
                    'coIbge' => $codigoIbge,
                    'anoMes' => $ano . str_pad($mes, 2, '0', STR_PAD_LEFT),
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return $this->normalizar($data ?? [], $competencia);
        } catch (GuzzleException $e) {
            $this->logger->error('ApiFNS erro HTTP', ['erro' => $e->getMessage()]);
            throw new HttpException(502, 'Erro ao consultar API FNS: ' . $e->getMessage());
        } catch (\JsonException) {
            throw new HttpException(502, 'Resposta inválida da API FNS.');
        }
    }

    /**
     * Descobre o nome exato do endpoint de detalhe (o campo muda entre versões).
     * Retorna os campos disponíveis para debug.
     *
     * @return array<string, mixed>
     */
    public function debugEndpoint(string $codigoIbge): array
    {
        try {
            $response = $this->http->get('/fns/api/pagamento/detalhes', [
                'query' => ['coIbge' => $codigoIbge, 'anoMes' => date('Ym')],
            ]);
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return ['status' => 'ok', 'campos' => array_keys(is_array($data[0] ?? null) ? $data[0] : ($data ?? []))];
        } catch (\Throwable $e) {
            return ['status' => 'erro', 'mensagem' => $e->getMessage()];
        }
    }

    /** @param array<mixed> $raw */
    private function normalizar(array $raw, string $competencia): array
    {
        $lista = isset($raw[0]) ? $raw : ($raw['lista'] ?? $raw['registros'] ?? []);

        return array_map(fn(array $item) => [
            'competencia'    => $competencia . '-01',
            'numero_parcela' => $item['nuParcela']       ?? null,
            'numero_banco'   => $item['nrBanco']         ?? null,
            'agencia'        => $item['nuAgencia']       ?? null,
            'conta_corrente' => $item['nuConta']         ?? null,
            'acao'           => $item['dsAcao']          ?? null,
            'programa'       => $item['dsPrograma']      ?? null,
            'bloco'          => $item['dsBloco']         ?? null,
            'subprograma'    => $item['dsSubPrograma']   ?? null,
            'componente'     => $item['dsComponente']    ?? null,
            'detalhe_url'    => null,
            'valor_bruto'    => (float) ($item['vlBruto']    ?? $item['vlTotal'] ?? 0),
            'valor_desconto' => (float) ($item['vlDesconto'] ?? 0),
            'valor_liquido'  => (float) ($item['vlLiquido']  ?? $item['vlTotal'] ?? 0),
            'tipo_operacao'  => $item['dsTipoOperacao']  ?? null,
            'situacao'       => $item['dsSituacao']      ?? null,
            'data_credito'   => isset($item['dtCredito'])
                                ? date('Y-m-d', strtotime($item['dtCredito']))
                                : null,
        ], $lista);
    }
}
