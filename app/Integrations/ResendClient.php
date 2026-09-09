<?php

declare(strict_types=1);

namespace Ersus360\Integrations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Ersus360\Exceptions\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Cliente para api.resend.com — envio de e-mails transacionais.
 *
 * Env vars obrigatórias (NUNCA hardcoded):
 *   RESEND_API_KEY  — chave de API (Railway env var)
 *   EMAIL_FROM      — remetente padrão (ex: onboarding@resend.dev)
 */
final class ResendClient
{
    private readonly Client $http;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $apiKey = $_ENV['RESEND_API_KEY'] ?? '';

        if ($apiKey === '') {
            $this->logger->error('RESEND_API_KEY não configurada.');
        }

        $this->http = new Client([
            'base_uri' => 'https://api.resend.com',
            'timeout'  => 15,
            'headers'  => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * Envia e-mail simples.
     *
     * @param  string|array<string> $para  Destinatário(s)
     * @param  string               $assunto
     * @param  string               $html
     * @param  string|null          $texto  Fallback plain text
     * @return array<string, mixed> Resposta da API (id do e-mail)
     */
    public function enviar(
        string|array $para,
        string       $assunto,
        string       $html,
        ?string      $texto = null,
    ): array {
        $from = $_ENV['EMAIL_FROM'] ?? 'onboarding@resend.dev';

        $payload = array_filter([
            'from'    => $from,
            'to'      => (array) $para,
            'subject' => $assunto,
            'html'    => $html,
            'text'    => $texto,
        ]);

        try {
            $response = $this->http->post('/emails', ['json' => $payload]);
            $body     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $this->logger->info('E-mail enviado via Resend', [
                'id'      => $body['id'] ?? null,
                'assunto' => $assunto,
                'para'    => $para,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            $this->logger->error('Resend HTTP error', ['erro' => $e->getMessage()]);
            throw new HttpException(502, 'Erro ao enviar e-mail: ' . $e->getMessage());
        }
    }

    /**
     * Envia notificação de nova portaria.
     *
     * @param  array<string> $destinatarios
     * @param  array<string, mixed> $portaria
     */
    public function notificarPortaria(array $destinatarios, array $portaria): void
    {
        $titulo = htmlspecialchars((string) ($portaria['titulo'] ?? 'Nova Portaria'), ENT_QUOTES);
        $data   = (string) ($portaria['data_publicacao'] ?? date('Y-m-d'));
        $url    = (string) ($portaria['url_dou'] ?? '');

        $html = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #1a56db;">ERSUS360 — Nova Portaria Publicada</h2>
            <p><strong>Data:</strong> {$data}</p>
            <p><strong>Portaria:</strong> {$titulo}</p>
            <p><strong>Órgão:</strong> {$portaria['orgao']}</p>
            <hr>
            <p>{$portaria['resumo']}</p>
            HTML;

        if ($url) {
            $html .= "<p><a href=\"{$url}\">Ver no Diário Oficial</a></p>";
        }

        $html .= '</div>';

        $this->enviar(
            $destinatarios,
            "📋 Nova Portaria — {$titulo}",
            $html,
            "Nova portaria publicada: {$titulo}\nData: {$data}\nLink: {$url}",
        );
    }
}
