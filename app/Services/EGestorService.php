<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Exceptions\HttpException;

/**
 * Integração com a API do e-Gestor APS (egestorab.saude.gov.br).
 * Busca repasses, analisa componentes eMulti e retorna diagnóstico.
 */
final class EGestorService
{
    private const BASE_URL  = 'https://egestorab.saude.gov.br';
    private const TIMEOUT   = 30;

    // Componentes esperados do eMulti pela Portaria 3.493/2024
    // Valores de referência confirmados pelo e-Gestor APS (dados reais Apuí/AM JUL/2026)
    private const EMULTI_COMPONENTES = [
        'C'  => ['label' => 'Custeio / Implantação',          'valor_ref' => 12000.00, 'obrigatorio' => true],
        'Q'  => ['label' => 'Qualidade',                      'valor_ref' => 2250.00,  'obrigatorio' => false],
        'AR' => ['label' => 'Atendimento Remoto (Telessaúde)','valor_ref' => 5000.00,  'obrigatorio' => false],
        'V'  => ['label' => 'Vínculo',                        'valor_ref' => null,     'obrigatorio' => false],
    ];

    // Valor fixo por equipe eSF (referência real: 9 equipes = R$ 270.000 → R$ 30.000/equipe incluindo F+V+Q)
    private const ESF_VALOR_EQUIPE   = 30000.00;
    // Valor de referência eAP (estimativa Portaria — varia por componente habilitado)
    private const EAP_VALOR_EQUIPE   = 16000.00;

    // Requisitos para cada componente ausente
    private const REQUISITOS_AR = [
        'A equipe eMulti deve estar cadastrada com modalidade habilitada para teleassistência no e-Gestor',
        'Registrar atividades de teleconsulta ou telediagnóstico no e-SUS PEC (ficha de atendimento com tipo "Telessaúde")',
        'Produção mínima de 20 teleconsultas/mês deve constar na RNDS',
        'Verificar habilitação da modalidade junto ao DAB/MS — pode ser necessário enviar ofício ao COSEMS',
    ];

    public function __construct(
        private readonly string $ibge,
        private readonly string $token   = '',
        private readonly string $usuario = '',
        private readonly string $senha   = '',
    ) {}

    /**
     * Instancia o serviço a partir das variáveis de ambiente disponíveis.
     * Prioridade: EGESTOR_TOKEN > ESUS_USUARIO+ESUS_SENHA
     */
    public static function fromEnv(string $ibge): self
    {
        $token   = $_ENV['EGESTOR_TOKEN']  ?? '';
        $usuario = $_ENV['ESUS_USUARIO']   ?? '';
        $senha   = $_ENV['ESUS_SENHA']     ?? '';

        if (!$token && (!$usuario || !$senha)) {
            throw new HttpException(503, implode(' ', [
                'Credenciais e-Gestor não configuradas.',
                'Configure EGESTOR_TOKEN (ou ESUS_USUARIO + ESUS_SENHA) nas variáveis do Railway.',
            ]));
        }

        return new self($ibge, $token, $usuario, $senha);
    }

    /**
     * Busca repasse COMPLETO do e-Gestor para a competência e retorna diagnóstico eMulti.
     */
    public function diagnosticoEmulti(string $competencia): array
    {
        $token   = $this->resolverToken();
        $repasse = $this->buscarRepasse($token, $competencia);
        return $this->analisarEmulti($repasse, $competencia);
    }

    /**
     * Busca o repasse APS completo do e-Gestor.
     */
    public function buscarRepasseCompleto(string $competencia): array
    {
        $token   = $this->resolverToken();
        $repasse = $this->buscarRepasse($token, $competencia);
        return $repasse;
    }

    // ── Privado ──────────────────────────────────────────────

    /** Retorna token já configurado ou autentica via user/senha. */
    private function resolverToken(): string
    {
        if ($this->token) {
            return $this->token;
        }
        return $this->autenticar();
    }

    private function autenticar(): string
    {
        $resp = $this->http('POST', '/api/seguranca/v0/autenticar', [
            'login' => $this->usuario,
            'senha' => $this->senha,
        ]);

        $token = $resp['token'] ?? $resp['access_token'] ?? null;
        if (!$token) {
            throw new HttpException(502, 'e-Gestor: falha na autenticação — verifique ESUS_USUARIO e ESUS_SENHA.');
        }
        return $token;
    }

    private function buscarRepasse(string $token, string $competencia): array
    {
        // Formata competência para YYYYMM (aceita YYYY-MM ou YYYYMM)
        $comp = str_replace('-', '', $competencia);

        $resp = $this->http(
            'GET',
            "/api/v1/repasses/cofinanciamento?ibge={$this->ibge}&competencia={$comp}&tipoRelatorio=COMPLETO",
            [],
            $token,
        );

        return $resp;
    }

    private function analisarEmulti(array $repasse, string $competencia): array
    {
        // Localiza os grupos do eMulti na resposta do e-Gestor
        $emultiDados = $this->extrairEmulti($repasse);

        $componentesRecebidos = [];
        $totalEmulti          = 0.0;

        foreach ($emultiDados as $item) {
            $sigla = mb_strtoupper($item['sigla'] ?? $item['componente'] ?? '');
            $valor = (float) ($item['valor'] ?? $item['valorTotal'] ?? 0);
            $componentesRecebidos[$sigla] = $valor;
            $totalEmulti += $valor;
        }

        // Analisa cada componente esperado
        $analise = [];
        foreach (self::EMULTI_COMPONENTES as $sigla => $info) {
            $recebido = isset($componentesRecebidos[$sigla]);
            $valor    = $componentesRecebidos[$sigla] ?? 0.0;

            $status = match(true) {
                $recebido && $valor > 0 => 'recebendo',
                $info['obrigatorio']    => 'ausente_critico',
                default                 => 'ausente',
            };

            $analise[$sigla] = [
                'sigla'      => $sigla,
                'label'      => $info['label'],
                'status'     => $status,
                'valor'      => $valor,
                'valor_ref'  => $info['valor_ref'],
                'obrigatorio'=> $info['obrigatorio'],
            ];
        }

        // Identifica inconsistências
        $inconsistencias = $this->identificarInconsistencias($componentesRecebidos, $emultiDados, $repasse);

        return [
            'competencia'          => $competencia,
            'ibge'                 => $this->ibge,
            'total_emulti'         => $totalEmulti,
            'componentes'          => $analise,
            'componentes_recebidos'=> array_keys($componentesRecebidos),
            'inconsistencias'      => $inconsistencias,
            'coletado_em'          => date('c'),
            'dados_brutos_emulti'  => $emultiDados,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function extrairEmulti(array $repasse): array
    {
        // A API pode retornar em diferentes formatos — tenta vários caminhos
        $caminhos = [
            $repasse['grupoM']                                ?? null,
            $repasse['grupos']['M']                           ?? null,
            $repasse['emulti']                                ?? null,
            $repasse['multiProfissional']                     ?? null,
        ];

        // Busca no array de componentes por tipo/grupo
        if (isset($repasse['componentes']) && is_array($repasse['componentes'])) {
            foreach ($repasse['componentes'] as $comp) {
                $grupo = mb_strtoupper($comp['grupo'] ?? $comp['bloco'] ?? '');
                if (str_contains($grupo, 'M') || str_contains($grupo, 'MULTI') || str_contains($grupo, 'EMULTI')) {
                    $caminhos[] = [$comp];
                }
            }
        }

        foreach ($caminhos as $c) {
            if (is_array($c) && !empty($c)) {
                // Normaliza: se for objeto único, encapsula em array
                return isset($c[0]) ? $c : [$c];
            }
        }

        return [];
    }

    /** @param array<string, float> $recebidos */
    private function identificarInconsistencias(array $recebidos, array $emultiDados, array $repasse): array
    {
        $issues = [];

        // ── 1. AR ausente no eMulti ──────────────────────────────────
        if (!isset($recebidos['AR']) || ($recebidos['AR'] ?? 0) == 0) {
            $issues[] = [
                'codigo'     => 'EMULTI_AR_AUSENTE',
                'severidade' => 'atencao',
                'titulo'     => 'eMulti: Atendimento Remoto (AR) não recebido',
                'descricao'  => 'A equipe eMulti recebe Custeio (C) e Qualidade (Q), mas o componente AR — Atendimento Remoto / Telessaúde — não está sendo pago pelo e-Gestor. '
                              . 'Isso indica ausência de produção de teleconsultas registrada na RNDS ou modalidade não habilitada no e-Gestor.',
                'impacto'    => 'Perda de R$ 5.000,00/mês por modalidade habilitada (podendo ser mais de uma).',
                'requisitos' => self::REQUISITOS_AR,
                'acao_url'   => 'https://egestorab.saude.gov.br',
                'acao_label' => 'Verificar no e-Gestor APS',
            ];
        }

        // ── 2. Vínculo ausente no eMulti ─────────────────────────────
        if (!isset($recebidos['V']) || ($recebidos['V'] ?? 0) == 0) {
            $issues[] = [
                'codigo'     => 'EMULTI_V_AUSENTE',
                'severidade' => 'atencao',
                'titulo'     => 'eMulti: Componente Vínculo (V) não recebido',
                'descricao'  => 'O componente Vínculo do eMulti não está sendo pago. Este componente exige cobertura territorial com as equipes eSF vinculadas.',
                'impacto'    => 'Perda variável conforme avaliação de vínculo territorial.',
                'requisitos' => [
                    'Verificar se a equipe eMulti está formalmente vinculada às equipes eSF no e-Gestor',
                    'Confirmar cadastro de responsabilidade territorial no CNES',
                    'Registrar ações intersetoriais e matriciamento no e-SUS PEC',
                ],
                'acao_url'   => 'https://egestorab.saude.gov.br',
                'acao_label' => 'Ver vinculação no e-Gestor',
            ];
        }

        // ── 3. eAP com teto alto mas 0 equipes pagas ─────────────────
        $eapTeto  = (int) $this->buscarCampo($repasse, ['eap.teto', 'grupos.C.teto', 'grupoC.teto']);
        $eapPagas = (int) $this->buscarCampo($repasse, ['eap.qtdPagas', 'grupos.C.qtdPagas', 'grupoC.qtdPagas']);
        if ($eapTeto > 0 && $eapPagas === 0) {
            $perdaMensal = number_format($eapTeto * self::EAP_VALOR_EQUIPE, 2, ',', '.');
            $issues[] = [
                'codigo'     => 'EAP_SEM_EQUIPES_PAGAS',
                'severidade' => 'critico',
                'titulo'     => "eAP — Atenção Primária Ampliada: 0 equipes pagas de {$eapTeto} no teto",
                'descricao'  => "O município tem teto para {$eapTeto} equipes eAP no e-Gestor, mas NENHUMA está sendo financiada. "
                              . "Esta é a maior inconsistência financeira identificada.",
                'impacto'    => "Perda estimada de R$ {$perdaMensal}/mês — equivalente a R$ "
                              . number_format($eapTeto * self::EAP_VALOR_EQUIPE * 12, 2, ',', '.') . "/ano.",
                'requisitos' => [
                    "Verificar se as {$eapTeto} equipes eAP estão cadastradas e ativas no SCNES com CBO correto",
                    'Confirmar vínculo das equipes com estabelecimento de saúde no e-Gestor APS',
                    'Validar carga horária mínima dos profissionais (médico/enfermeiro) no CNES',
                    'Se as equipes não existirem: avaliar credenciamento junto ao DAB/MS via COSEMS',
                    'Consultar Nota Técnica DAB sobre requisitos para pagamento do eAP',
                ],
                'acao_url'   => 'https://cnes.datasus.gov.br',
                'acao_label' => 'Verificar equipes no CNES',
            ];
        }

        // ── 4. eMulti com grande capacidade ociosa ───────────────────
        $mTeto  = (int) $this->buscarCampo($repasse, ['emulti.teto', 'grupos.M.teto', 'grupoM.teto']);
        $mPagas = (int) $this->buscarCampo($repasse, ['emulti.qtdPagas', 'grupos.M.qtdPagas', 'grupoM.qtdPagas']);
        if ($mTeto > 0 && $mPagas > 0 && ($mTeto - $mPagas) >= 2) {
            $ociosas     = $mTeto - $mPagas;
            $perdaMensal = number_format($ociosas * 14250, 2, ',', '.');
            $issues[] = [
                'codigo'     => 'EMULTI_CAPACIDADE_OCIOSA',
                'severidade' => 'atencao',
                'titulo'     => "eMulti: {$mPagas} equipe(s) paga(s) de {$mTeto} no teto — {$ociosas} vaga(s) ociosa(s)",
                'descricao'  => "O município tem teto para {$mTeto} equipes eMulti mas somente {$mPagas} está(ão) credenciada(s) e recebendo custeio.",
                'impacto'    => "Capacidade ociosa de {$ociosas} equipe(s) — potencial adicional de R$ {$perdaMensal}/mês.",
                'requisitos' => [
                    'Avaliar ampliação das equipes eMulti junto à Secretaria Municipal de Saúde',
                    'Verificar disponibilidade orçamentária para contratação de novos profissionais',
                    'Solicitar credenciamento das equipes adicionais ao DAB/MS via COSEMS/AM',
                    'Profissionais elegíveis: psicólogo, fisioterapeuta, fonoaudiólogo, assistente social, entre outros',
                ],
                'acao_url'   => 'https://egestorab.saude.gov.br',
                'acao_label' => 'Ver teto no e-Gestor',
            ];
        }

        // ── 5. eSF com teto acima das equipes pagas ──────────────────
        $sfTeto  = (int) $this->buscarCampo($repasse, ['esf.teto', 'grupos.SF.teto']);
        $sfPagas = (int) $this->buscarCampo($repasse, ['esf.qtdPagas', 'grupos.SF.qtdPagas']);
        if ($sfTeto > 0 && $sfPagas > 0 && ($sfTeto - $sfPagas) >= 2) {
            $ociosas = $sfTeto - $sfPagas;
            $issues[] = [
                'codigo'     => 'ESF_CAPACIDADE_OCIOSA',
                'severidade' => 'atencao',
                'titulo'     => "eSF: {$sfPagas} de {$sfTeto} equipes pagas — {$ociosas} vaga(s) ociosa(s)",
                'descricao'  => "O município tem teto para {$sfTeto} equipes eSF mas somente {$sfPagas} estão sendo financiadas.",
                'impacto'    => "Perda de " . number_format($ociosas * self::ESF_VALOR_EQUIPE, 2, ',', '.') . "/mês por equipe não credenciada.",
                'requisitos' => [
                    'Verificar cadastro das equipes eSF no SCNES com composição mínima obrigatória',
                    'Confirmar implantação das equipes nas UBS correspondentes',
                    'Avaliar contratação de médico/enfermeiro para equipes incompletas',
                ],
                'acao_url'   => 'https://cnes.datasus.gov.br',
                'acao_label' => 'Verificar no CNES',
            ];
        }

        return $issues;
    }

    /**
     * Busca um valor em caminhos alternativos no array (dot notation).
     * Ex: buscarCampo($data, ['eap.teto', 'grupos.C.teto']) retorna o primeiro encontrado.
     */
    private function buscarCampo(array $data, array $caminhos): mixed
    {
        foreach ($caminhos as $caminho) {
            $parts = explode('.', $caminho);
            $val   = $data;
            foreach ($parts as $part) {
                if (!is_array($val) || !array_key_exists($part, $val)) {
                    $val = null;
                    break;
                }
                $val = $val[$part];
            }
            if ($val !== null) {
                return $val;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function http(string $method, string $path, array $body = [], string $token = ''): array
    {
        $url  = self::BASE_URL . $path;
        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", array_filter([
                    'Content-Type: application/json',
                    'Accept: application/json',
                    $token ? "Authorization: Bearer {$token}" : '',
                ])),
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ];

        if ($method === 'POST' && !empty($body)) {
            $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $ctx      = stream_context_create($opts);
        $raw      = @file_get_contents($url, false, $ctx);
        $headers  = $http_response_header ?? [];
        $status   = 0;

        foreach ($headers as $h) {
            if (preg_match('/HTTP\/\S+ (\d+)/', $h, $m)) {
                $status = (int) $m[1];
            }
        }

        if ($raw === false || $status === 0) {
            throw new HttpException(502, "e-Gestor inacessível: não foi possível conectar em {$url}");
        }

        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(502, "e-Gestor retornou resposta inválida (não-JSON): {$raw}");
        }

        if ($status >= 400) {
            $msg = $data['message'] ?? $data['erro'] ?? $data['error'] ?? "HTTP {$status}";
            throw new HttpException($status >= 500 ? 502 : $status, "e-Gestor: {$msg}");
        }

        return $data ?? [];
    }
}
