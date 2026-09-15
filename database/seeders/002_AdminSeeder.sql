-- Seeder 002: usuário admin inicial
-- O acesso do superadmin é controlado pelas variáveis de ambiente:
--   ADMIN_EMAIL=eulerenzoramos@gmail.com
--   ADMIN_SENHA=<senha em texto simples no Railway>
--
-- O hash neste seeder é apenas um fallback; o login via env var tem prioridade.
-- Para gerar um novo hash: password_hash('SuaSenha', PASSWORD_BCRYPT, ['cost' => 12])
INSERT IGNORE INTO usuarios (municipio_id, nome, email, senha_hash, perfil, ativo)
SELECT
    m.id,
    'Euler Ramos',
    'eulerenzoramos@gmail.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.ucrITEDQK',
    'superadmin',
    1
FROM municipios m
WHERE m.codigo_ibge = '1300144'
LIMIT 1;
