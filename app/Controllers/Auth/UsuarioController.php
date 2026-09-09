<?php

declare(strict_types=1);

namespace Ersus360\Controllers\Auth;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Repositories\UsuarioRepository;
use Ersus360\Services\AuthService;
use Ersus360\Services\AuditService;
use Ersus360\Services\PermissaoService;
use Ersus360\Exceptions\HttpException;

/**
 * CRUD de usuários. Restrito a admin/superadmin.
 *
 * GET    /api/usuarios          — lista paginada
 * GET    /api/usuarios/{id}     — detalhe
 * POST   /api/usuarios          — criar
 * PUT    /api/usuarios/{id}     — atualizar
 * DELETE /api/usuarios/{id}     — inativar (exclusão lógica)
 */
final class UsuarioController
{
    /** 18 perfis válidos conforme models/usuario.py */
    private const PERFIS_VALIDOS = [
        'superadmin', 'admin', 'gestor', 'coordenador', 'enfermeiro', 'medico',
        'tecnico_aps', 'acs', 'odontologia', 'farmaceutico', 'vigilancia',
        'financeiro', 'contabilidade', 'planejamento', 'auditoria',
        'prefeito', 'conselho', 'consulta',
    ];

    public function __construct(
        private readonly UsuarioRepository $repo,
        private readonly AuthService       $auth,
        private readonly AuditService      $audit,
        private readonly PermissaoService  $permissao,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios');

        $pagina    = max(1, (int) $request->query('pagina', 1));
        $porPagina = min(100, max(10, (int) $request->query('por_pagina', 25)));
        $busca     = trim((string) $request->query('busca', ''));
        $perfil    = (string) $request->query('perfil', '');

        // Assessoria vê todos; perfil municipal vê só os do próprio município
        $municipioId = $this->permissao->isAssessoria($request->perfil())
            ? null
            : $request->municipioId();

        [$total, $usuarios] = $this->repo->listar(
            municipioId: $municipioId,
            busca:       $busca,
            perfil:      $perfil,
            pagina:      $pagina,
            porPagina:   $porPagina,
        );

        return Response::paginated($usuarios, $total, $pagina, $porPagina);
    }

    public function show(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios');
        $id      = $request->paramInt('id');
        $usuario = $this->repo->buscarPorId($id);

        if ($usuario === null) {
            throw new HttpException(404, 'Usuário não encontrado.');
        }

        $this->permissao->exigirMunicipio(
            $request->perfil(),
            $request->municipioId(),
            (int) $usuario['municipio_id'],
        );

        return Response::json($usuario);
    }

    public function store(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios');

        $dados = $this->validar($request);
        $hash  = $this->auth->hashSenha($dados['senha']);

        $id = $this->repo->criar([
            'municipio_id' => $dados['municipio_id'],
            'nome'         => $dados['nome'],
            'email'        => mb_strtolower($dados['email']),
            'senha_hash'   => $hash,
            'perfil'       => $dados['perfil'],
            'ativo'        => true,
        ]);

        $this->audit->log(
            usuarioId:  $request->userId(),
            acao:       'CREATE',
            tabela:     'usuarios',
            registroId: $id,
            detalhe:    "Criou usuário: {$dados['email']} perfil={$dados['perfil']}",
        );

        $novo = $this->repo->buscarPorId($id);
        return Response::created($novo, "/api/usuarios/{$id}");
    }

    public function update(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios');

        $id      = $request->paramInt('id');
        $usuario = $this->repo->buscarPorId($id);

        if ($usuario === null) {
            throw new HttpException(404, 'Usuário não encontrado.');
        }

        $dados = $request->all();

        // Não permite alterar perfis mais altos (proteção)
        if (isset($dados['perfil']) && !in_array($dados['perfil'], self::PERFIS_VALIDOS, true)) {
            throw new HttpException(422, 'Perfil inválido.', );
        }

        $atualizar = array_filter([
            'nome'   => isset($dados['nome']) ? trim($dados['nome']) : null,
            'perfil' => $dados['perfil'] ?? null,
            'ativo'  => isset($dados['ativo']) ? (bool) $dados['ativo'] : null,
        ], fn($v) => $v !== null);

        if (isset($dados['nova_senha'])) {
            $atualizar['senha_hash'] = $this->auth->hashSenha($dados['nova_senha']);
        }

        if (empty($atualizar)) {
            throw new HttpException(422, 'Nenhum campo válido para atualizar.');
        }

        $this->repo->atualizar($id, $atualizar);
        $this->audit->log($request->userId(), 'UPDATE', 'usuarios', $id, json_encode($atualizar));

        return Response::json($this->repo->buscarPorId($id));
    }

    public function destroy(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'usuarios');

        $id = $request->paramInt('id');

        if ($id === $request->userId()) {
            throw new HttpException(422, 'Você não pode inativar sua própria conta.');
        }

        $this->repo->inativar($id);
        $this->audit->log($request->userId(), 'DELETE', 'usuarios', $id, 'Inativação lógica');

        return Response::noContent();
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $dados = $request->all();
        $erros = [];

        if (empty($dados['nome']))         $erros[] = 'nome é obrigatório';
        if (empty($dados['email']))        $erros[] = 'email é obrigatório';
        if (!filter_var($dados['email'] ?? '', FILTER_VALIDATE_EMAIL)) $erros[] = 'email inválido';
        if (empty($dados['senha']))        $erros[] = 'senha é obrigatória';
        if (empty($dados['municipio_id'])) $erros[] = 'municipio_id é obrigatório';
        if (!in_array($dados['perfil'] ?? '', self::PERFIS_VALIDOS, true)) $erros[] = 'perfil inválido';

        if (!empty($erros)) {
            throw new HttpException(422, implode('; ', $erros));
        }

        return $dados;
    }
}
