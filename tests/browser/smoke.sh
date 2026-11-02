#!/usr/bin/env bash
#
# Smoke de navegador de ponta a ponta, sem nada pulado (QA-04/QA-05).
#
# Sobe o servidor embutido apontado para o BANCO DE TESTE, semeia um login e um
# portal descartáveis e roda o tests/browser/smoke.mjs. Ao sair, mata o grupo de
# processos do servidor inteiro — pai e os 4 workers, que senão viram órfãos
# segurando a porta e atendem a rodada seguinte no lugar do servidor novo.
#
#   npm run test:browser
#
# Se você já tem app e credenciais no ambiente (CI, app em outro host), defina
# BASE_URL + SMOKE_EMAIL + SMOKE_PASSWORD + PORTAL_TOKEN e este script só chama
# o smoke.
#
# ⚠ `-d variables_order=EGPCS` NÃO é enfeite. No SAPI web do servidor embutido,
# `$_ENV` nasce vazio: o dotenv (imutável) não vê as variáveis exportadas, grava
# as do `.env` — que aponta para o **Supabase de produção** — e o smoke abre o
# app contra produção sem avisar. Com EGPCS o ambiente exportado entra em `$_ENV`
# e o `.env` só preenche o que faltou. A trava de verdade é o login de
# conferência abaixo: ele só passa se o app estiver lendo o banco semeado.
set -euo pipefail

cd "$(dirname "$0")/../.."

if [ -n "${BASE_URL:-}" ] && [ -n "${SMOKE_EMAIL:-}" ] && [ -n "${SMOKE_PASSWORD:-}" ] && [ -n "${PORTAL_TOKEN:-}" ]; then
  echo "→ Ambiente já pronto ($BASE_URL); rodando só o smoke."
  exec node tests/browser/smoke.mjs
fi

# ── Credenciais descartáveis no banco de teste ───────────────────────────────
# O seed.php aborta se o .env.testing não apontar para host local.
eval "$(php tests/browser/seed.php)"

# ── Ambiente do servidor: só o banco de teste ────────────────────────────────
set -a
# shellcheck disable=SC1091
. ./.env.testing
set +a

PORT="${SMOKE_PORT:-8099}"
export APP_URL="http://127.0.0.1:${PORT}"
export BASE_URL="$APP_URL"

# ── A porta tem de estar livre ANTES de subirmos ─────────────────────────────
# O readiness abaixo só pergunta "alguém responde?". Se um servidor de uma
# rodada anterior ainda estiver na porta, o `php -S` novo morre com "Address
# already in use" e o smoke roda inteiro contra o processo velho — com o DB_* e
# o APP_ENV congelados no que valia naquela hora — devolvendo 8 ✅ sobre um
# servidor que ele não subiu. Era o que acontecia: `kill` do PID do pai deixava
# os 4 workers vivos (reparentados no PID 1) segurando a porta.
if curl -fs -o /dev/null --max-time 2 "${BASE_URL}/login" 2>/dev/null; then
  echo "❌ Alguém já atende em ${BASE_URL}/login antes de subirmos o servidor."
  echo "   Provável servidor órfão de uma rodada anterior do smoke:"
  OCUPANTES=""
  if command -v lsof >/dev/null 2>&1; then
    OCUPANTES="$(lsof -nP -iTCP:"${PORT}" -sTCP:LISTEN -t 2>/dev/null | tr '\n' ' ')"
  fi
  if [ -n "$OCUPANTES" ]; then
    # Sem aspas de propósito: são vários PIDs, cada um um argumento do -p.
    # shellcheck disable=SC2086
    ps -o pid,ppid,command -p ${OCUPANTES} 2>/dev/null || true
    echo "   Mate com: kill ${OCUPANTES}"
  else
    echo "   Mate com: pkill -f \"127.0.0.1:${PORT}\""
  fi
  echo "   Ou use outra porta: SMOKE_PORT=8098 npm run test:browser"
  exit 1
fi

# ── Servidor embutido ────────────────────────────────────────────────────────
LOG="$(mktemp -t yve-smoke-server)"
JAR="$(mktemp -t yve-smoke-cookies)"
# Mais de um worker: o navegador pede CSS/JS em paralelo e o `php -S` atende um
# por vez — com um worker só, a página nunca chega a networkidle.
#
# `set -m` (job control) não é enfeite: ele faz o `php -S` virar líder do próprio
# grupo de processos, para o trap matar o pai **e** os 4 workers de uma vez com
# `kill -- -PID`. Matando só o pai, os workers sobrevivem e vazam a porta.
set -m
PHP_CLI_SERVER_WORKERS=4 php -d variables_order=EGPCS -S "127.0.0.1:${PORT}" router.php >"$LOG" 2>&1 &
SERVER_PID=$!
set +m
trap 'kill -- -"$SERVER_PID" 2>/dev/null || kill "$SERVER_PID" 2>/dev/null || true; rm -f "$JAR"' EXIT

for _ in $(seq 1 40); do
  # O servidor que NÓS subimos morreu? Sem este teste, o readiness aceitaria o
  # 200 de qualquer outro processo na porta e o erro de bind passaria calado.
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    echo "❌ O servidor embutido que este script subiu morreu ao iniciar. Log:"
    tail -20 "$LOG"
    exit 1
  fi
  # Sem -S: a primeira tentativa falha enquanto a porta não abre, e o erro é ruído.
  # `--max-time` é obrigatório: um processo que só segura a porta aceita a
  # conexão e nunca responde, e sem timeout o curl bloqueia para sempre — o
  # `kill -0` acima nunca rodaria de novo para perceber que o nosso morreu.
  curl -fs -o /dev/null --max-time 2 "${BASE_URL}/login" && break
  sleep 0.25
done

if ! curl -fsS -o /dev/null --max-time 5 "${BASE_URL}/login"; then
  echo "❌ O servidor embutido não respondeu em ${BASE_URL}/login. Log:"
  tail -20 "$LOG"
  exit 1
fi

# ── Trava: o app está mesmo no banco semeado? ────────────────────────────────
# Login por HTTP com a credencial do seed. Se o servidor tiver subido com outro
# banco (o `.env` de produção, por exemplo), ele não conhece esse usuário e
# paramos aqui — antes de abrir o navegador.
TOKEN="$(curl -fsS -c "$JAR" "${BASE_URL}/login" | sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' | head -1)"
DESTINO="$(curl -fsS -b "$JAR" -c "$JAR" -o /dev/null -w '%{redirect_url}' \
  --data-urlencode "email=${SMOKE_EMAIL}" \
  --data-urlencode "password=${SMOKE_PASSWORD}" \
  --data-urlencode "_token=${TOKEN}" \
  "${BASE_URL}/login")"

if [ "$DESTINO" != "${BASE_URL}/dashboard" ]; then
  echo "❌ O login de conferência não entrou (destino: ${DESTINO:-vazio})."
  echo "   O servidor provavelmente subiu com outro banco que não o de teste."
  tail -20 "$LOG"
  exit 1
fi

# ── Smoke ────────────────────────────────────────────────────────────────────
set +e
node tests/browser/smoke.mjs
STATUS=$?
set -e

if [ "$STATUS" -ne 0 ]; then
  echo "— últimas linhas do servidor —"
  tail -20 "$LOG"
fi

exit "$STATUS"
