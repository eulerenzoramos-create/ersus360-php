<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Repositories\MunicipioRepository;
use Ersus360\Services\AuditService;
use Ersus360\Services\PermissaoService;
use Ersus360\Exceptions\HttpException;

final class MunicipioController
{
    public function __construct(
        private readonly MunicipioRepository $repo,
        private readonly AuditService        $audit,
        private readonly PermissaoService    $permissao,
    ) {}

    public function index(Request $request): Response
    {
        $municipios = $this->repo->listar();

        // Não-assessoria só vê o próprio município
        if (!$this->permissao->isAssessoria($request->perfil())) {
            $municipios = array_values(
                array_filter($municipios, fn($m) => (int) $m['id'] === $request->municipioId())
            );
        }

        return Response::json(['municipios' => $municipios, 'total' => count($municipios)]);
    }

    public function show(Request $request): Response
    {
        $id        = $request->paramInt('id');
        $municipio = $this->repo->buscarPorId($id);

        if ($municipio === null) {
            throw new HttpException(404, 'Município não encontrado.');
        }

        $this->permissao->exigirMunicipio($request->perfil(), $request->municipioId(), $id);

        return Response::json($municipio);
    }

    public function dashboard(Request $request): Response
    {
        $id = $request->paramInt('id');
        $this->permissao->exigirMunicipio($request->perfil(), $request->municipioId(), $id);

        $resumo = $this->repo->resumoDashboard($id);
        return Response::json($resumo);
    }

    public function store(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios'); // Requer admin

        $dados = $request->all();
        $erros = [];

        if (empty($dados['nome']))        $erros[] = 'nome é obrigatório';
        if (empty($dados['codigo_ibge'])) $erros[] = 'codigo_ibge é obrigatório';

        if (!empty($erros)) {
            throw new HttpException(422, implode('; ', $erros));
        }

        $id = $this->repo->criar($dados);
        $this->audit->log($request->userId(), 'CREATE', 'municipios', $id, "Novo município: {$dados['nome']}");

        return Response::created($this->repo->buscarPorId($id), "/api/municipios/{$id}");
    }

    public function update(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios'); // Requer admin

        $id = $request->paramInt('id');
        $dados = $request->all();

        $campos = array_filter([
            'nome'        => $dados['nome']        ?? null,
            'populacao'   => isset($dados['populacao']) ? (int) $dados['populacao'] : null,
            'ativo'       => isset($dados['ativo'])  ? (bool) $dados['ativo'] : null,
            'latitude'    => $dados['latitude']    ?? null,
            'longitude'   => $dados['longitude']   ?? null,
        ], fn($v) => $v !== null);

        if (empty($campos)) {
            throw new HttpException(422, 'Nenhum campo válido para atualizar.');
        }

        $this->repo->atualizar($id, $campos);
        $this->audit->log($request->userId(), 'UPDATE', 'municipios', $id, json_encode($campos));

        return Response::json($this->repo->buscarPorId($id));
    }
}
