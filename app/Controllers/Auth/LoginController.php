<?php

declare(strict_types=1);

namespace Ersus360\Controllers\Auth;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Services\AuthService;
use Ersus360\Exceptions\HttpException;

/**
 * POST /api/auth/login    — autenticação
 * POST /api/auth/logout   — invalidação (stateless: cliente descarta o token)
 * POST /api/auth/renovar  — renova token antes de expirar
 * POST /api/auth/trocar-senha
 */
final class LoginController
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $senha = (string) $request->input('senha', '');

        if ($email === '' || $senha === '') {
            throw new HttpException(422, 'E-mail e senha são obrigatórios.');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $resultado = $this->auth->login($email, $senha, $ip);

        return Response::json([
            'token'   => $resultado['token'],
            'usuario' => $resultado['usuario'],
            'mensagem' => 'Login realizado com sucesso.',
        ]);
    }

    public function logout(Request $request): Response
    {
        // JWT é stateless — o cliente descarta o token.
        // Em produção com blacklist: registrar o jti do token expirado.
        return Response::json(['mensagem' => 'Sessão encerrada.']);
    }

    public function renovar(Request $request): Response
    {
        // O usuário já está autenticado (AuthMiddleware validou o token).
        // Geramos um novo token com os mesmos dados.
        $user = $request->user();

        if (empty($user)) {
            throw new HttpException(401, 'Usuário não autenticado.');
        }

        // Reutiliza AuthService para emitir novo token
        $novoToken = $this->auth->renovar($user);

        return Response::json([
            'token'   => $novoToken,
            'usuario' => $user,
        ]);
    }

    public function trocarSenha(Request $request): Response
    {
        $senhaAtual = (string) $request->input('senha_atual', '');
        $novaSenha  = (string) $request->input('nova_senha', '');

        if ($senhaAtual === '' || $novaSenha === '') {
            throw new HttpException(422, 'senha_atual e nova_senha são obrigatórios.');
        }

        $this->auth->trocarSenha($request->userId(), $senhaAtual, $novaSenha);

        return Response::json(['mensagem' => 'Senha alterada com sucesso.']);
    }
}
