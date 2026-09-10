-- Seeder 002: usuário admin inicial
-- Senha: Ersus@2026 (bcrypt cost=12)
INSERT IGNORE INTO usuarios (municipio_id, nome, email, senha_hash, perfil, ativo)
SELECT
    m.id,
    'Administrador',
    'eulerenzoramos@gmail.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.ucrITEDQK',
    'superadmin',
    1
FROM municipios m
WHERE m.codigo_ibge = '1300144'
LIMIT 1;
