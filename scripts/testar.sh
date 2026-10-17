#!/usr/bin/env bash
# Roda a suíte duas vezes: SQLite (rápido) e Postgres descartável (o banco de
# produção — os invariantes 1, 2 e 4 dependem dele). Precisa só de Docker.
set -euo pipefail
cd "$(dirname "$0")/.."
U="$(id -u):$(id -g)"

docker run --rm -u "$U" -e COMPOSER_HOME=/tmp/c -v "$PWD":/app -w /app composer:2 \
  sh -c '[ -d vendor ] || composer install -q --no-interaction; vendor/bin/phpunit'

docker network create votacao-teste >/dev/null
trap 'docker rm -f votacao-pg >/dev/null 2>&1; docker network rm votacao-teste >/dev/null 2>&1' EXIT
docker run -d --rm --name votacao-pg --network votacao-teste \
  -e POSTGRES_PASSWORD=teste -e POSTGRES_DB=votacao postgres:16-alpine >/dev/null
until docker exec votacao-pg pg_isready -U postgres -q; do sleep 1; done

# Imagem com pdo_pgsql e PHP 8.4 (o de produção).
docker run --rm --network votacao-teste -u "$U" -e DB_HOST=votacao-pg -e DB_PASSWORD=teste \
  -v "$PWD":/app -w /app --entrypoint php "${IMAGEM_PHP:-sail-8.4/app:latest}" \
  vendor/bin/phpunit -c tests/phpunit.pgsql.xml
