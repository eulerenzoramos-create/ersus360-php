<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Repositories\PortariasRepository;
use Ersus360\Repositories\MunicipioRepository;
use Ersus360\Services\PortariasService;
use Ersus360\Services\PermissaoService;
use Ersus360\Services\AuditService;
use Ersus360\Exceptions\HttpException;

final class PortariasController
{
    public function __construct(
        private readonly PortariasRepository $repo,
        private readonly MunicipioRepository $municipioRepo,
        private readonly PortariasService    $portariasService,
        private readonly PermissaoService    $permissao,
        private readonly AuditService        $audit,
    ) {}

    public function index(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $pagina      = max(1, (int) $request->query('pagina', 1));
        $porPagina   = min(100, max(10, (int) $request->query('por_pagina', 20)));
        $busca       = trim((string) $request->query('busca', ''));
        $naoLidas    = (bool) $request->query('nao_lidas', false);

        [$total, $portarias] = $this->repo->listar($municipioId, $busca ?: null, $naoLidas, $pagina, $porPagina);

        return Response::paginated($portarias, $total, $pagina, $porPagina);
    }

    public function show(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $id          = $request->paramInt('id');
        $portaria    = $this->repo->buscarPorId($id);

        if (!$portaria || (int) $portaria['municipio_id'] !== $municipioId) {
            throw new HttpException(404, 'Portaria não encontrada.');
        }

        return Response::json($portaria);
    }

    public function sincronizar(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'portarias');

        $municipioId = $request->municipioId();
        $municipio   = $this->municipioRepo->buscarPorId($municipioId);

        if (!$municipio) {
            throw new HttpException(404, 'Município não encontrado.');
        }

        $data = (string) $request->input('data', date('Y-m-d'));

        $resultado = $this->portariasService->sincronizar(
            $municipioId,
            (string) $municipio['nome'],
            $data,
        );

        $this->audit->log($request->userId(), 'SYNC', 'portarias', $municipioId,
            "DOU {$data}: {$resultado['novas']} novas");

        return Response::json([
            'mensagem' => 'Sincronização concluída.',
            'data'     => $data,
            'novas'    => $resultado['novas'],
            'total'    => $resultado['total'],
        ]);
    }

    public function notificar(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'portarias');

        $municipioId = $request->municipioId();
        $portariaId  = (int) $request->input('portaria_id', 0);

        if ($portariaId === 0) {
            throw new HttpException(422, 'portaria_id é obrigatório.');
        }

        $resultado = $this->portariasService->notificar($municipioId, $portariaId);

        $this->audit->log($request->userId(), 'NOTIFY', 'portarias', $portariaId,
            "Notificações: {$resultado['enviados']} enviadas");

        return Response::json([
            'mensagem' => 'Notificação enviada.',
            'enviados' => $resultado['enviados'],
            'erros'    => $resultado['erros'],
        ]);
    }

    public function marcarLida(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $id          = $request->paramInt('id');
        $portaria    = $this->repo->buscarPorId($id);

        if (!$portaria || (int) $portaria['municipio_id'] !== $municipioId) {
            throw new HttpException(404, 'Portaria não encontrada.');
        }

        $this->repo->marcarLida($id);
        return Response::json(['mensagem' => 'Portaria marcada como lida.']);
    }
}
