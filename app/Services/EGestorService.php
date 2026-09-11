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
    private const EMULTI_COMPONENTES = [
        'C'  => ['label' => 'Custeio / Implantação',         'valor_ref' => 12000.00, 'obrigatorio' => true],
        'Q'  => ['label' => 'Qualidade',                     'valor_ref' => null,     'obrigatorio' => false],
        'AR' => ['label' => 'Atendimento Remoto (Telessaúde)','valor_ref' => 5000.00, 'obrigatorio' => false],
        'V'  => ['label' => 'Vínculo',                       'valor_ref' => null,     'obrigatorio' => false],
    ];

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

        // AR ausente
        if (!isset($recebidos['AR'])) {
            $issues[] = [
                'codigo'     => 'EMULTI_AR_AUSENTE',
                'severidade' => 'atencao',
                'titulo'     => 'Componente AR (Atendimento Remoto) não recebido',
                'descricao'  => 'O e-Gestor não registra produção de telessaúde para a equipe eMulti deste município na competência analisada.',
                'impacto'    => 'Perda potencial de R$ 5.000,00/mês por modalidade habilitada.',
                'requisitos' => self::REQUISITOS_AR,
                'acao_url'   => 'https://egestorab.saude.gov.br',
                'acao_label' => 'Verificar no e-Gestor APS',
            ];
        }

        // eAP com teto > 0 mas sem equipes pagas
        $eapTeto  = (int) ($repasse['grupos']['C']['teto']    ?? $repasse['eap']['teto']    ?? 0);
        $eapPagas = (int) ($repasse['grupos']['C']['qtdPagas'] ?? $repasse['eap']['qtdPagas'] ?? 0);
        if ($eapTeto > 0 && $eapPagas === 0) {
            $issues[] = [
                'codigo'     => 'EAP_SEM_EQUIPES_PAGAS',
                'severidade' => 'critico',
                'titulo'     => "eAP: teto de {$eapTeto} equipes mas 0 pagas",
                'descricao'  => 'O município tem teto para equipes de Atenção Primária Ampliada (eAP) mas nenhuma está sendo financiada.',
                'impacto'    => "Possível perda de R$ " . number_format($eapTeto * 16000, 2, ',', '.') . "/mês.",
                'requisitos' => [
                    'Verificar cadastro das equipes eAP no SCNES',
                    'Confirmar vínculo das equipes com a UBS no e-Gestor',
                    'Validar CBO e carga horária dos profissionais',
                ],
                'acao_url'   => 'https://cnes.datasus.gov.br',
                'acao_label' => 'Verificar no CNES',
            ];
        }

        // eMulti com teto muito acima das equipes pagas (capacidade ociosa)
        $mTeto  = (int) ($repasse['grupos']['M']['teto']    ?? $repasse['emulti']['teto']    ?? 0);
        $mPagas = (int) ($repasse['grupos']['M']['qtdPagas'] ?? $repasse['emulti']['qtdPagas'] ?? 0);
        if ($mTeto > 0 && $mPagas > 0 && ($mTeto - $mPagas) >= 3) {
            $issues[] = [
                'codigo'     => 'EMULTI_CAPACIDADE_OCIOSA',
                'severidade' => 'atencao',
                'titulo'     => "eMulti: {$mPagas} de {$mTeto} vagas utilizadas",
                'descricao'  => "O município tem teto para {$mTeto} equipes eMulti mas somente {$mPagas} está(ão) recebendo custeio.",
                'impacto'    => "Capacidade ociosa: " . ($mTeto - $mPagas) . " equipes não credenciadas.",
                'requisitos' => [
                    'Avaliar ampliação das equipes eMulti junto à gestão municipal',
                    'Verificar disponibilidade orçamentária para novos profissionais',
                    'Solicitar credenciamento ao DAB via CONASS/COSEMS',
                ],
                'acao_url'   => 'https://egestorab.saude.gov.br',
                'acao_label' => 'Ver teto no e-Gestor',
            ];
        }

        return $issues;
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
