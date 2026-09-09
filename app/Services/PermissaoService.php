<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Exceptions\HttpException;

/**
 * Controle de permissões por perfil e módulo.
 *
 * Espelha fielmente o PERMISSOES dict do sistema Python atual
 * (models/usuario.py). Qualquer mudança aqui deve ser sincronizada
 * com a documentação da matriz de equivalência.
 *
 * Perfis assessoria (superadmin, admin, auditoria) enxergam qualquer município.
 * Perfis municipais só enxergam o próprio município_id do token.
 */
final class PermissaoService
{
    private const ASSESSORIA = ['superadmin', 'admin', 'auditoria'];

    /** Módulos por agrupamento funcional */
    private const FINANCEIRO = [
        'financeiro', 'repasses', 'caf', 'fns', 'siops', 'contratos',
        'ppa_loa', 'execucao', 'emendas', 'portarias',
    ];

    private const APS = [
        'home', 'aps', 'qualidade', 'parametros_ms', 'fichas_tecnicas',
        'producao_sisab', 'relatorio_producao', 'monitor_rt',
        'busca_ativa', 'acs', 'inconsistencias', 'poeps', 'sb360',
        'gestao_aps', 'painel_gestao', 'siaps', 'atencao_domiciliar',
        'sala_vacinas', 'score', 'alertas', 'telessaude',
    ];

    private const VIGILANCIA = [
        'vigilancia', 'epidemiologia', 'notificacoes', 'sim_sinasc',
        'cancer', 'ccih', 'sala_vacinas', 'monitor_rt',
    ];

    private const ADMIN_MODS = ['usuarios', 'rh', 'auditoria_sistema', 'cadastros'];

    /** @var array<string, string[]> */
    private const PERMISSOES = [
        'superadmin'   => ['*'],
        'admin'        => ['*'],
        'gestor'       => [], // calculado dinamicamente
        'coordenador'  => [], // calculado
        'enfermeiro'   => [], // calculado
        'medico'       => [], // calculado
        'tecnico_aps'  => [], // calculado
        'acs'          => ['home', 'acs', 'busca_ativa', 'inconsistencias', 'monitor_rt', 'alertas'],
        'odontologia'  => [], // calculado
        'farmaceutico' => ['home', 'farmacia', 'alertas', 'producao_sisab', 'siaps'],
        'vigilancia'   => ['home', 'alertas', 'score'],
        'financeiro'   => ['home', 'siaps', 'alertas', 'score'],
        'contabilidade'=> ['home', 'financeiro', 'siops', 'contratos', 'ppa_loa', 'alertas'],
        'planejamento' => ['home', 'plano_municipal', 'score_municipal', 'score', 'parametros_ms',
                           'qualidade', 'poeps', 'ppa_loa', 'alertas', 'siaps'],
        'auditoria'    => [], // calculado
        'prefeito'     => ['home', 'score', 'score_municipal', 'financeiro',
                           'portal_gestor', 'alertas', 'siaps', 'qualidade', 'aps'],
        'conselho'     => ['home', 'score', 'qualidade', 'parametros_ms',
                           'conselho_saude', 'alertas', 'sim_sinasc'],
        'consulta'     => ['home', 'alertas'],
    ];

    /**
     * Verifica se o perfil tem acesso ao módulo.
     * Lança HttpException 403 se não tiver.
     */
    public function exigir(string $perfil, string $modulo): void
    {
        if (!$this->pode($perfil, $modulo)) {
            throw new HttpException(403, "Acesso negado ao módulo '{$modulo}' para perfil '{$perfil}'.");
        }
    }

    public function pode(string $perfil, string $modulo): bool
    {
        $permitidos = $this->modulos($perfil);
        return in_array('*', $permitidos, true) || in_array($modulo, $permitidos, true);
    }

    /**
     * Retorna lista de módulos permitidos para o perfil.
     * @return string[]
     */
    public function modulos(string $perfil): array
    {
        return match ($perfil) {
            'superadmin', 'admin'  => ['*'],
            'gestor'               => $this->tudo(),
            'coordenador'          => array_values(array_diff(
                                         array_merge(self::APS, self::VIGILANCIA, ['siaps', 'score', 'home', 'alertas']),
                                         self::FINANCEIRO, self::ADMIN_MODS,
                                     )),
            'enfermeiro'           => array_unique(array_merge(self::APS, self::VIGILANCIA)),
            'medico'               => array_unique(array_merge(self::APS, self::VIGILANCIA, ['farmacia'])),
            'tecnico_aps'          => self::APS,
            'odontologia'          => array_values(array_diff(
                                         self::APS, ['busca_ativa', 'acs'],
                                     )),
            'vigilancia'           => array_unique(array_merge(self::VIGILANCIA, ['home', 'alertas', 'score'])),
            'financeiro'           => array_unique(array_merge(self::FINANCEIRO, ['home', 'siaps', 'alertas', 'score'])),
            'auditoria'            => array_values(array_diff($this->tudo(), self::ADMIN_MODS)),
            default                => self::PERMISSOES[$perfil] ?? [],
        };
    }

    /**
     * Retorna true se o perfil pertence à assessoria
     * (acesso multi-município).
     */
    public function isAssessoria(string $perfil): bool
    {
        return in_array($perfil, self::ASSESSORIA, true);
    }

    /**
     * Verifica isolamento de município.
     * Perfis municipais só podem acessar dados do próprio município.
     *
     * @param int|null $municipioIdToken  municipio_id vindo do JWT
     * @param int      $municipioIdRota   municipio_id do recurso acessado
     */
    public function exigirMunicipio(string $perfil, ?int $municipioIdToken, int $municipioIdRota): void
    {
        if ($this->isAssessoria($perfil)) {
            return; // Assessoria acessa qualquer município
        }

        if ($municipioIdToken !== $municipioIdRota) {
            throw new HttpException(403, 'Acesso negado: município não autorizado.');
        }
    }

    /** @return string[] */
    private function tudo(): array
    {
        return array_unique(array_merge(
            self::FINANCEIRO,
            self::APS,
            self::VIGILANCIA,
            self::ADMIN_MODS,
            [
                'regulacao_mac', 'regulacao', 'farmacia', 'manutencao', 'frota',
                'plano_municipal', 'score_municipal', 'conselho_saude', 'ouvidoria',
                'sadt', 'pgrss', 'gestao_qualidade', 'cme', 'saude_servidor',
                'ia', 'bi', 'portal_gestor', 'portal_cidadao',
                'agenda', 'conformidade', 'ocis', 'patrimonio', 'absenteismo',
            ],
        ));
    }
}
