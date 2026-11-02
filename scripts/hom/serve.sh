#!/usr/bin/env bash
#
# Abre a homologação em http://localhost:8010. E-mails em http://localhost:8025.
# Cron não roda sozinho: dispare à mão com
#   curl "http://localhost:8010/queue/scheduler?token=hom-fila-local"
#   curl "http://localhost:8010/queue/work?token=hom-fila-local"
set -euo pipefail

cd "$(dirname "$0")/../.."

if [ -f .env.production ] || ! grep -qx 'DB_NAME=yve_hom' .env 2>/dev/null; then
  echo "ABORTADO: rode na pasta da homologação, depois de scripts/hom/up.sh." >&2
  exit 1
fi

# openssl.cafile: confia só no certificado local da caixa de e-mails.
exec php -d openssl.cafile="$PWD/.hom/hom.crt" -S localhost:8010 router.php
