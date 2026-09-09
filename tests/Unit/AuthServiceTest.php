<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ersus360\Services\AuthService;
use Ersus360\Services\JwtService;
use Ersus360\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

final class AuthServiceTest extends TestCase
{
    private JwtService $jwt;

    protected function setUp(): void
    {
        $_ENV['JWT_SECRET']   = 'test_secret_ersus360_phpunit_32chars_ok';
        $_ENV['ADMIN_EMAIL']  = 'admin@test.local';
        $_ENV['ADMIN_SENHA']  = 'AdminTest123';

        $this->jwt = new JwtService();
    }

    #[Test]
    public function hash_senha_retorna_bcrypt(): void
    {
        // Instanciamos sem o banco para testar só hashSenha
        $auth = $this->makeAuthServiceSemBanco();
        $hash = $auth->hashSenha('MinhaS3nh@');

        self::assertStringStartsWith('$2y$', $hash, 'Deve ser bcrypt');
        self::assertTrue(password_verify('MinhaS3nh@', $hash));
    }

    #[Test]
    public function validar_forca_senha_aceita_senha_forte(): void
    {
        $auth = $this->makeAuthServiceSemBanco();
        // Deve retornar sem exceção
        $auth->validarForcaSenha('Senha123!');
        self::assertTrue(true);
    }

    #[Test]
    public function validar_forca_senha_rejeita_senha_curta(): void
    {
        $this->expectException(HttpException::class);
        $auth = $this->makeAuthServiceSemBanco();
        $auth->validarForcaSenha('abc');
    }

    #[Test]
    public function login_bootstrap_admin_retorna_token(): void
    {
        // Login pelo admin bootstrap (sem banco)
        $dbMock = $this->createMock(\Ersus360\Core\Database::class);
        $dbMock->method('fetchOne')->willReturn(null); // não existe no banco
        $dbMock->method('execute')->willReturn(null);

        $auditMock = $this->createMock(\Ersus360\Services\AuditService::class);
        $auditMock->method('log')->willReturn(null);

        $auth = new AuthService($dbMock, $this->jwt, $auditMock);

        $resultado = $auth->login('admin@test.local', 'AdminTest123', '127.0.0.1');

        self::assertArrayHasKey('token',   $resultado);
        self::assertArrayHasKey('usuario', $resultado);
        self::assertEquals('superadmin', $resultado['usuario']['perfil']);
    }

    #[Test]
    public function login_com_senha_errada_lanca_401(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        $dbMock = $this->createMock(\Ersus360\Core\Database::class);
        $dbMock->method('fetchOne')->willReturn(null);

        $auditMock = $this->createMock(\Ersus360\Services\AuditService::class);

        $auth = new AuthService($dbMock, $this->jwt, $auditMock);
        $auth->login('admin@test.local', 'SenhaErrada', '127.0.0.1');
    }

    private function makeAuthServiceSemBanco(): AuthService
    {
        $dbMock    = $this->createMock(\Ersus360\Core\Database::class);
        $auditMock = $this->createMock(\Ersus360\Services\AuditService::class);
        return new AuthService($dbMock, $this->jwt, $auditMock);
    }
}
