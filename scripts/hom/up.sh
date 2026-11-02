#!/usr/bin/env bash
#
# Sobe a homologação local: banco, caixa de e-mails, migrations e dados fictícios.
#
#   git worktree add --detach ../yve_agency_hom hom   # --detach: a branch pode seguir aberta na pasta principal
#   cd ../yve_agency_hom && composer install
#   scripts/hom/up.sh          # prepara tudo
#   scripts/hom/serve.sh       # abre o sistema em http://localhost:8010
#   scripts/hom/up.sh --reset  # apaga o banco e recomeça do zero
#
# ⚠ Roda SÓ na pasta separada. O dotenv preenche qualquer variável ausente a partir
# do `.env`, e o `.env` da pasta principal aponta para o Supabase de produção. Por
# isso as travas abaixo recusam a pasta que tiver esse arquivo ou .env.production.
set -euo pipefail

cd "$(dirname "$0")/../.."

if [ -f .env.production ]; then
  echo "ABORTADO: esta pasta tem .env.production. Rode na pasta separada da homologação." >&2
  exit 1
fi
if [ -f .env ] && ! grep -qx 'DB_NAME=yve_hom' .env; then
  echo "ABORTADO: o .env desta pasta não é o da homologação." >&2
  exit 1
fi

mkdir -p .hom
if [ ! -f .hom/hom.crt ]; then
  openssl req -x509 -newkey rsa:2048 -nodes -days 365 \
    -keyout .hom/hom.key -out .hom/hom.crt \
    -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost" 2>/dev/null
  chmod 644 .hom/hom.key
fi

if [ ! -f .env ]; then
  cat > .env <<ENV
APP_NAME="YVE Agency (homologação)"
APP_ENV=development
APP_URL=http://localhost:8010
APP_KEY=$(php -r 'echo base64_encode(random_bytes(32));')

DB_ADAPTER=pgsql
DB_HOST=127.0.0.1
DB_PORT=55433
DB_NAME=yve_hom
DB_USER=postgres
DB_PASS=hom

SESSION_LIFETIME=7200
SESSION_NAME=yve_hom_session

MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=hom
MAIL_PASSWORD=hom
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hom@yve.local
MAIL_FROM_NAME="YVE Agency (homologação)"

QUEUE_SECRET=hom-fila-local
ENV
fi

if [ "${1:-}" = "--reset" ]; then
  docker compose -f docker-compose.hom.yml down -v
fi

docker compose -f docker-compose.hom.yml up -d --wait

vendor/bin/phinx migrate -e development
vendor/bin/phinx seed:run -e development -s InitialDataSeeder
php scripts/hom/seed.php
