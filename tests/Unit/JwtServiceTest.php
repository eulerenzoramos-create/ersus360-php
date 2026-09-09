<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ersus360\Services\JwtService;
use Ersus360\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class JwtServiceTest extends TestCase
{
    private JwtService $jwt;

    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test_secret_ersus360_phpunit_32chars_ok';
        $this->jwt = new JwtService();
    }

    #[Test]
    public function gera_token_e_valida_com_sucesso(): void
    {
        $payload = [
            'id'           => 1,
            'nome'         => 'Admin Teste',
            'email'        => 'admin@test.local',
            'perfil'       => 'superadmin',
            'municipio_id' => 1,
        ];

        $token = $this->jwt->gerar($payload);

        self::assertNotEmpty($token);
        self::assertStringContainsString('.', $token);

        $validado = $this->jwt->validar($token);

        self::assertEquals(1,            $validado['id']);
        self::assertEquals('superadmin', $validado['perfil']);
        self::assertEquals('ersus360',   $validado['iss']);
        self::assertArrayHasKey('exp',   $validado);
        self::assertArrayHasKey('iat',   $validado);
    }

    #[Test]
    public function token_invalido_lanca_http_exception_401(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        $this->jwt->validar('token.invalido.aqui');
    }

    #[Test]
    public function token_adulterado_lanca_http_exception_401(): void
    {
        $token = $this->jwt->gerar(['id' => 1, 'perfil' => 'consulta']);
        // Adulterar o payload (segunda parte)
        $partes        = explode('.', $token);
        $partes[1]     = base64_encode(json_encode(['id' => 99, 'perfil' => 'superadmin']));
        $tokenAdulterado = implode('.', $partes);

        $this->expectException(HttpException::class);
        $this->jwt->validar($tokenAdulterado);
    }
}
