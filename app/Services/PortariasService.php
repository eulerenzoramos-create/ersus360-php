<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Repositories\PortariasRepository;
use Ersus360\Repositories\UsuarioRepository;
use Ersus360\Integrations\DouClient;
use Ersus360\Integrations\ResendClient;
use Psr\Log\LoggerInterface;

final class PortariasService
{
    public function __construct(
        private readonly PortariasRepository $repo,
        private readonly UsuarioRepository   $usuarioRepo,
        private readonly DouClient           $dou,
        private readonly ResendClient        $resend,
        private readonly LoggerInterface     $logger,
    ) {}

    /**
     * Scraping do DOU para um município.
     * @return array{novas: int, total: int}
     */
    public function sincronizar(int $municipioId, string $nomeMunicipio, ?string $data = null): array
    {
        $data    = $data ?? date('Y-m-d');
        $portarias = $this->dou->buscarPortarias($nomeMunicipio, $data);
        $novas   = 0;

        foreach ($portarias as $p) {
            if ($this->repo->existePorUrl($municipioId, $p['url_dou'] ?? '')) {
                continue;
            }

            $this->repo->inserir(array_merge($p, ['municipio_id' => $municipioId]));
            $novas++;
        }

        $this->logger->info('Portarias sincronizadas', [
            'municipio_id' => $municipioId,
            'data'         => $data,
            'novas'        => $novas,
        ]);

        return ['novas' => $novas, 'total' => count($portarias)];
    }

    /**
     * Envia e-mail de notificação para os gestores do município.
     * @return array{enviados: int, erros: int}
     */
    public function notificar(int $municipioId, int $portariaId): array
    {
        $portaria = $this->repo->buscarPorId($portariaId);
        if (!$portaria || (int) $portaria['municipio_id'] !== $municipioId) {
            return ['enviados' => 0, 'erros' => 0];
        }

        // Busca gestores e coordenadores do município
        [, $usuarios] = $this->usuarioRepo->listar(
            municipioId: $municipioId,
            busca:       '',
            perfil:      '',
            pagina:      1,
            porPagina:   200,
        );

        $destinatarios = array_column(
            array_filter($usuarios, fn($u) => in_array($u['perfil'], ['gestor', 'coordenador', 'prefeito', 'admin', 'superadmin'], true)),
            'email',
        );

        if (empty($destinatarios)) {
            return ['enviados' => 0, 'erros' => 0];
        }

        $enviados = 0;
        $erros    = 0;

        try {
            $this->resend->notificarPortaria($destinatarios, $portaria);
            $this->repo->marcarNotificada($portariaId);
            $enviados = count($destinatarios);
        } catch (\Throwable $e) {
            $this->logger->error('Notificação portaria falhou', ['erro' => $e->getMessage()]);
            $erros++;
        }

        return ['enviados' => $enviados, 'erros' => $erros];
    }
}
