<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ersus360\Services\PermissaoService;
use Ersus360\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class PermissaoServiceTest extends TestCase
{
    private PermissaoService $svc;

    protected function setUp(): void
    {
        $this->svc = new PermissaoService();
    }

    #[Test]
    public function superadmin_pode_acessar_qualquer_modulo(): void
    {
        foreach (['fns', 'aps', 'usuarios', 'emendas', 'folha', 'portarias', 'cnes'] as $modulo) {
            self::assertTrue($this->svc->pode('superadmin', $modulo), "superadmin deve ter acesso a {$modulo}");
        }
    }

    #[Test]
    public function consulta_nao_pode_acessar_usuarios(): void
    {
        self::assertFalse($this->svc->pode('consulta', 'usuarios'));
    }

    #[Test]
    public function exigir_lanca_403_quando_sem_permissao(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->svc->exigir('consulta', 'usuarios');
    }

    #[Test]
    #[DataProvider('perfisAssessoria')]
    public function perfis_assessoria_veem_todos_municipios(string $perfil): void
    {
        self::assertTrue($this->svc->isAssessoria($perfil));
    }

    /** @return array<array{string}> */
    public static function perfisAssessoria(): array
    {
        return [
            ['superadmin'],
            ['admin'],
            ['auditoria'],
        ];
    }

    #[Test]
    #[DataProvider('perfisMunicipais')]
    public function perfis_municipais_nao_sao_assessoria(string $perfil): void
    {
        self::assertFalse($this->svc->isAssessoria($perfil));
    }

    /** @return array<array{string}> */
    public static function perfisMunicipais(): array
    {
        return [
            ['gestor'],
            ['coordenador'],
            ['enfermeiro'],
            ['medico'],
            ['acs'],
            ['financeiro'],
            ['prefeito'],
            ['conselho'],
            ['consulta'],
        ];
    }

    #[Test]
    public function isolamento_municipio_bloqueia_acesso_cruzado(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        // Usuário do município 1 tentando acessar dados do município 2
        $this->svc->exigirMunicipio('gestor', municipioIdToken: 1, municipioIdRota: 2);
    }

    #[Test]
    public function isolamento_municipio_permite_acesso_proprio(): void
    {
        // Não deve lançar exceção
        $this->svc->exigirMunicipio('gestor', municipioIdToken: 1, municipioIdRota: 1);
        self::assertTrue(true);
    }

    #[Test]
    public function superadmin_ignora_isolamento_municipio(): void
    {
        // Não deve lançar exceção
        $this->svc->exigirMunicipio('superadmin', municipioIdToken: 1, municipioIdRota: 99);
        self::assertTrue(true);
    }
}
