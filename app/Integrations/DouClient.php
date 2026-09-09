<?php

declare(strict_types=1);

namespace Ersus360\Integrations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Ersus360\Exceptions\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Scraping do Diário Oficial da União — diario.in.gov.br.
 *
 * Env vars:
 *   DOU_URL  (opcional, padrão: https://www.in.gov.br)
 */
final class DouClient
{
    private readonly Client $http;
    private readonly string $baseUrl;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->baseUrl = rtrim($_ENV['DOU_URL'] ?? 'https://www.in.gov.br', '/');

        $this->http = new Client([
            'base_uri'        => $this->baseUrl,
            'timeout'         => 45,
            'connect_timeout' => 15,
            'verify'          => true,
            'headers'         => [
                'User-Agent' => 'ERSUS360/2.0 SMS-Apui-AM',
                'Accept'     => 'application/json, text/html',
            ],
        ]);
    }

    /**
     * Busca portarias do dia que mencionam saúde e o município.
     *
     * @param  string $municipio Nome do município (ex: "Apuí")
     * @param  string $data      Data no formato YYYY-MM-DD
     * @return array<int, array<string, mixed>>
     */
    public function buscarPortarias(string $municipio, string $data): array
    {
        $termos = [
            $municipio,
            'saúde',
            'atenção básica',
            'fundo nacional de saúde',
            'Portaria',
        ];

        $resultados = [];

        foreach ($termos as $termo) {
            try {
                $itens = $this->buscarPorTermo($termo, $data);
                foreach ($itens as $item) {
                    $chave = $item['url_dou'] ?? $item['titulo'];
                    if (!isset($resultados[$chave])) {
                        $resultados[$chave] = $item;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('DOU busca falhou', ['termo' => $termo, 'erro' => $e->getMessage()]);
            }
        }

        return array_values($resultados);
    }

    /**
     * API de busca do DOU.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarPorTermo(string $termo, string $data): array
    {
        $response = $this->http->get('/consulta/api/search', [
            'query' => [
                'q'          => $termo,
                'exactDate'  => $data,
                'sortType'   => 0,
                'delta'      => 20,
                'currentPag' => 1,
                'jornal'     => '1000',   // Seção 1 (portarias GM)
            ],
        ]);

        $data_resp = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $hits      = $data_resp['hits']['hits'] ?? [];

        return array_map(fn(array $hit) => [
            'titulo'          => $hit['_source']['title']        ?? $hit['_source']['identifica'] ?? '',
            'resumo'          => $hit['_source']['abstract']     ?? '',
            'orgao'           => $hit['_source']['orgaoPublicador'] ?? '',
            'secao'           => (int) ($hit['_source']['jornal'] ?? 1),
            'pagina'          => (int) ($hit['_source']['paginaInicial'] ?? 0),
            'url_dou'         => isset($hit['_source']['urlTitle'])
                                 ? $this->baseUrl . '/consulta/publicacoes/' . $hit['_source']['urlTitle']
                                 : null,
            'data_publicacao' => $data,
            'texto_completo'  => null, // Carregado sob demanda
        ], $hits);
    }

    /**
     * Busca texto completo de uma portaria pela URL.
     */
    public function textoCompleto(string $urlDou): string
    {
        try {
            $response = $this->http->get($urlDou, [
                'headers' => ['Accept' => 'text/html'],
            ]);
            // Extrai o texto — implementação mínima, sem DOM parsing completo
            $html  = (string) $response->getBody();
            $texto = strip_tags($html);
            $texto = preg_replace('/\s+/', ' ', $texto);
            return trim($texto ?? '');
        } catch (\Throwable $e) {
            $this->logger->warning('DOU texto completo falhou', ['url' => $urlDou]);
            return '';
        }
    }
}
