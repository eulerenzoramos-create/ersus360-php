<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Core\Database;

final class CnesController
{
    public function __construct(private readonly Database $db) {}

    public function estabelecimentos(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $busca       = trim((string) $request->query('busca', ''));

        $where  = ['municipio_id = :mid', 'ativo = 1'];
        $params = ['mid' => $municipioId];

        if ($busca !== '') {
            $where[]       = '(nome LIKE :b OR cnes LIKE :b OR tipo_unidade LIKE :b)';
            $params['b']   = '%' . $busca . '%';
        }

        $cond = implode(' AND ', $where);
        $rows = $this->db->fetchAll(
            "SELECT id, cnes, nome, nome_fantasia, tipo_unidade, esfera,
                    logradouro, numero, bairro, cep, telefone, latitude, longitude, sincronizado_em
             FROM cnes_estabelecimentos
             WHERE {$cond}
             ORDER BY nome",
            $params,
        );

        return Response::json(['total' => count($rows), 'estabelecimentos' => $rows]);
    }

    public function equipes(Request $request): Response
    {
        // CNES não tem tabela de equipes localmente ainda — retorna placeholder
        return Response::json([
            'mensagem' => 'Equipes disponíveis via API CNES/DATASUS.',
            'equipes'  => [],
        ]);
    }

    public function profissionais(Request $request): Response
    {
        return Response::json([
            'mensagem'      => 'Profissionais disponíveis via API CNES/DATASUS.',
            'profissionais' => [],
        ]);
    }

    public function sincronizar(Request $request): Response
    {
        return Response::json(['mensagem' => 'Sincronização CNES agendada.']);
    }
}
