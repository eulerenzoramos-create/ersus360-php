#!/bin/sh
# Entrypoint Railway: roda migrações e sobe os serviços.
set -e

echo "==> ERSUS360 starting up..."
echo "==> PHP $(php -r 'echo PHP_VERSION;')"

# Espera o MySQL estar pronto (Railway pode demorar alguns segundos)
MAX=30
i=0
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASS}');" 2>/dev/null; do
  i=$((i+1))
  if [ $i -ge $MAX ]; then
    echo "ERRO: MySQL não ficou disponível após ${MAX}s"
    exit 1
  fi
  echo "==> Aguardando MySQL... (${i}/${MAX})"
  sleep 1
done

echo "==> MySQL conectado. Rodando migrações..."
php /var/www/ersus360/artisan db:migrate --seed

echo "==> Criando admin inicial (se não existir)..."
php -r "
require '/var/www/ersus360/vendor/autoload.php';
\$pdo = new PDO(
    'mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306').';dbname='.getenv('DB_NAME').';charset=utf8mb4',
    getenv('DB_USER'), getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
\$email = 'eulerenzoramos@gmail.com';
\$existe = \$pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
\$existe->execute([\$email]);
if (!\$existe->fetch()) {
    \$muni = \$pdo->query('SELECT id FROM municipios WHERE ibge = \'1300144\' LIMIT 1')->fetchColumn();
    \$hash = password_hash('Ersus@2026', PASSWORD_BCRYPT, ['cost' => 12]);
    \$stmt = \$pdo->prepare('INSERT INTO usuarios (municipio_id, nome, email, senha_hash, perfil, ativo) VALUES (?,?,?,?,?,1)');
    \$stmt->execute([\$muni, 'Administrador', \$email, \$hash, 'superadmin']);
    echo 'Admin criado: '.\$email.PHP_EOL;
} else {
    echo 'Admin ja existe.'.PHP_EOL;
}
"

echo "==> Migrações concluídas. Iniciando supervisor..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
