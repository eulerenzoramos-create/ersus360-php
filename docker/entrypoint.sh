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

echo "==> Migrações concluídas. Iniciando supervisor..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
