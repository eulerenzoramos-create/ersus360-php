<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Core\Database;
use Ersus360\Services\PermissaoService;
use Ersus360\Services\AuditService;
use Ersus360\Exceptions\HttpException;

final class InvestSusController
{
    private const TIPOS_VALIDOS = ['individual', 'bancada', 'comissao', 'relator'];
    private const FASES_VALIDAS = ['proposta', 'aprovada', 'empenhada', 'liquidada', 'paga', 'cancelada'];

    public function __construct(
        private readonly Database         $db,
        private readonly PermissaoService $permissao,
        private readonly AuditService     $audit,
    ) {}

    public function index(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $fase        = (string) $request->query('fase', '');
        $tipo        = (string) $request->query('tipo', '');
        $ano         = (int)    $request->query('ano',  0);
        $pagina      = max(1, (int) $request->query('pagina', 1));
        $porPagina   = min(100, max(10, (int) $request->query('por_pagina', 25)));

        $where  = ['municipio_id = :mid'];
        $params = ['mid' => $municipioId];

        if ($fase !== '') { $where[] = 'fase = :fase'; $params['fase'] = $fase; }
        if ($tipo !== '') { $where[] = 'tipo = :tipo'; $params['tipo'] = $tipo; }
        if ($ano  !== 0)  { $where[] = 'ano_orcamentario = :ano'; $params['ano'] = $ano; }

        $cond  = implode(' AND ', $where);
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM emendas WHERE {$cond}", $params);

        $params['limit']  = $porPagina;
        $params['offset'] = ($pagina - 1) * $porPagina;

        $emendas = $this->db->fetchAll(
            "SELECT id, numero_emenda, autor, parlamentar, partido, tipo, objeto,
                    programa, valor_autorizado, valor_empenhado, valor_liquidado, valor_pago,
                    fase, ano_orcamentario, data_empenho, data_pagamento, prioridade
             FROM emendas
             WHERE {$cond}
             ORDER BY prioridade DESC, valor_empenhado DESC
             LIMIT :limit OFFSET :offset",
            $params,
        );

        return Response::paginated($emendas, $total, $pagina, $porPagina);
    }

    public function resumo(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $ano         = (int) $request->query('ano', (int) date('Y'));

        $totais = $this->db->fetchOne(
            'SELECT
                COUNT(*) AS total_emendas,
                COALESCE(SUM(valor_autorizado), 0) AS autorizado,
                COALESCE(SUM(valor_empenhado),  0) AS empenhado,
                COALESCE(SUM(valor_liquidado),  0) AS liquidado,
                COALESCE(SUM(valor_pago),       0) AS pago
             FROM emendas
             WHERE municipio_id = :mid AND ano_orcamentario = :ano',
            ['mid' => $municipioId, 'ano' => $ano],
        );

        $porFase = $this->db->fetchAll(
            'SELECT fase, COUNT(*) AS qtd, COALESCE(SUM(valor_empenhado), 0) AS valor
             FROM emendas
             WHERE municipio_id = :mid AND ano_orcamentario = :ano
             GROUP BY fase',
            ['mid' => $municipioId, 'ano' => $ano],
        );

        return Response::json(['ano' => $ano, 'totais' => $totais, 'por_fase' => $porFase]);
    }

    public function show(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $id          = $request->paramInt('id');

        $emenda = $this->db->fetchOne(
            'SELECT * FROM emendas WHERE id = :id AND municipio_id = :mid',
            ['id' => $id, 'mid' => $municipioId],
        );

        if (!$emenda) throw new HttpException(404, 'Emenda não encontrada.');

        return Response::json($emenda);
    }

    public function store(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'emendas');
        $municipioId = $request->municipioId();
        $dados = $this->validar($request->all());

        $id = $this->db->insert(
            'INSERT INTO emendas
                (municipio_id, numero_emenda, autor, parlamentar, partido, estado_parlamentar,
                 tipo, objeto, programa, acao, funcao, subfuncao,
                 valor_autorizado, valor_empenhado, valor_liquidado, valor_pago,
                 fase, ano_orcamentario, data_empenho, data_pagamento, prioridade, observacoes)
             VALUES
                (:mid, :ne, :autor, :parl, :part, :ep,
                 :tipo, :obj, :prog, :acao, :func, :subfunc,
                 :vaut, :vemp, :vliq, :vpago,
                 :fase, :ano, :demp, :dpag, :prio, :obs)',
            [
                'mid'    => $municipioId,
                'ne'     => $dados['numero_emenda']      ?? null,
                'autor'  => $dados['autor']              ?? null,
                'parl'   => $dados['parlamentar']        ?? null,
                'part'   => $dados['partido']            ?? null,
                'ep'     => $dados['estado_parlamentar'] ?? null,
                'tipo'   => $dados['tipo'],
                'obj'    => $dados['objeto']             ?? null,
                'prog'   => $dados['programa']           ?? null,
                'acao'   => $dados['acao']               ?? null,
                'func'   => $dados['funcao']             ?? null,
                'subfunc' => $dados['subfuncao']         ?? null,
                'vaut'   => (float) ($dados['valor_autorizado'] ?? 0),
                'vemp'   => (float) ($dados['valor_empenhado']  ?? 0),
                'vliq'   => (float) ($dados['valor_liquidado']  ?? 0),
                'vpago'  => (float) ($dados['valor_pago']       ?? 0),
                'fase'   => $dados['fase'],
                'ano'    => $dados['ano_orcamentario'] ?? (int) date('Y'),
                'demp'   => $dados['data_empenho']    ?? null,
                'dpag'   => $dados['data_pagamento']  ?? null,
                'prio'   => (bool) ($dados['prioridade'] ?? false),
                'obs'    => $dados['observacoes']     ?? null,
            ],
        );

        $this->audit->log($request->userId(), 'CREATE', 'emendas', $id,
            "Nova emenda: {$dados['numero_emenda']}");

        $nova = $this->db->fetchOne('SELECT * FROM emendas WHERE id = :id', ['id' => $id]);
        return Response::created($nova, "/api/emendas/{$id}");
    }

    public function update(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'emendas');
        $municipioId = $request->municipioId();
        $id          = $request->paramInt('id');

        $emenda = $this->db->fetchOne(
            'SELECT id FROM emendas WHERE id = :id AND municipio_id = :mid',
            ['id' => $id, 'mid' => $municipioId],
        );

        if (!$emenda) throw new HttpException(404, 'Emenda não encontrada.');

        $dados  = $request->all();
        $campos = [];
        $params = ['id' => $id];

        $permitidos = [
            'fase','valor_autorizado','valor_empenhado','valor_liquidado','valor_pago',
            'data_empenho','data_pagamento','prioridade','observacoes','objeto',
        ];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $campos[]       = "{$campo} = :{$campo}";
                $params[$campo] = $dados[$campo];
            }
        }

        if (empty($campos)) throw new HttpException(422, 'Nenhum campo válido para atualizar.');

        $campos[] = 'atualizado_em = NOW()';
        $this->db->execute('UPDATE emendas SET ' . implode(', ', $campos) . ' WHERE id = :id', $params);
        $this->audit->log($request->userId(), 'UPDATE', 'emendas', $id, json_encode($dados));

        return Response::json($this->db->fetchOne('SELECT * FROM emendas WHERE id = :id', ['id' => $id]));
    }

    public function destroy(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'emendas');
        $municipioId = $request->municipioId();
        $id          = $request->paramInt('id');

        $this->db->execute(
            'DELETE FROM emendas WHERE id = :id AND municipio_id = :mid',
            ['id' => $id, 'mid' => $municipioId],
        );

        $this->audit->log($request->userId(), 'DELETE', 'emendas', $id, 'Exclusão de emenda');
        return Response::noContent();
    }

    private function validar(array $dados): array
    {
        $erros = [];
        if (!in_array($dados['tipo'] ?? '', self::TIPOS_VALIDOS, true)) $erros[] = 'tipo inválido';
        if (!in_array($dados['fase'] ?? 'proposta', self::FASES_VALIDAS, true)) $erros[] = 'fase inválida';
        if (!empty($erros)) throw new HttpException(422, implode('; ', $erros));
        return $dados;
    }
}
