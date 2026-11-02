/**
 * Smoke test de navegador — a rede que faltava.
 *
 * Dois bugs escaparam de PHPUnit + PHPStan porque não são visíveis no código:
 *   1. CSP sem 'unsafe-eval' → Alpine morre (EvalError) e a UI inteira congela;
 *   2. módulo JS com `defer` → executa DEPOIS do Alpine.start(), e o componente
 *      morre com "ReferenceError: driveManager is not defined".
 * Nos dois casos o PHP responde 200 e os testes passam — só o navegador vê.
 *
 * Este script abre as telas num Chromium de verdade, com a CSP real do app, e
 * FALHA se a navegação não devolver 2xx, se terminar em outra URL (redirect
 * para /login), se houver qualquer erro de console ou se um componente Alpine
 * não inicializar.
 *
 * Uso:
 *   npm run test:browser          # sobe app + banco de teste + semente e roda tudo
 *   node tests/browser/smoke.mjs  # app e credenciais já no ambiente
 *
 * Requer o app rodando e um login válido (SMOKE_EMAIL / SMOKE_PASSWORD) mais o
 * PORTAL_TOKEN de um cliente. **Faltando qualquer um, o script falha** (QA-05):
 * antes ele pulava as telas internas e terminava com "✅ nenhum erro" tendo
 * aberto só o /login — suíte verde que não provava nada.
 * `npm run test:browser` resolve isso para você; detalhes em docs/OPERACAO.md.
 *
 * SMOKE_BROWSER_CHANNEL=chrome usa o Chrome instalado na máquina quando o
 * Chromium do Playwright não foi baixado (`npx playwright install chromium`).
 */

import { chromium } from 'playwright';

const BASE = process.env.BASE_URL || 'http://localhost:8000';
const EMAIL = process.env.SMOKE_EMAIL;
const PASSWORD = process.env.SMOKE_PASSWORD;
const PORTAL_TOKEN = process.env.PORTAL_TOKEN;
const CHANNEL = process.env.SMOKE_BROWSER_CHANNEL || undefined;

// Ruído que não indica quebra (favicon ausente, extensão do browser…).
const IGNORE = [/favicon/i, /net::ERR_ABORTED.*favicon/i];

const failures = [];

// Nada de tela pulada: sem credencial, o smoke só veria o /login.
const missing = [
  !EMAIL && 'SMOKE_EMAIL',
  !PASSWORD && 'SMOKE_PASSWORD',
  !PORTAL_TOKEN && 'PORTAL_TOKEN',
].filter(Boolean);

if (missing.length) {
  console.error(`❌ Falta ${missing.join(', ')} — o smoke pularia as telas internas.`);
  console.error('   Rode `npm run test:browser` (sobe app + semente no banco de teste)');
  console.error('   ou semeie à mão: eval "$(php tests/browser/seed.php)"');
  process.exit(1);
}

async function check(page, name, url, expectAlpine = true) {
  const errors = [];
  const onError = (msg) => {
    if (msg.type() === 'error' && !IGNORE.some((re) => re.test(msg.text()))) {
      // A URL do recurso vai junto: "Failed to load resource: 403" sem dizer
      // *qual* recurso não dá para investigar.
      const origem = msg.location()?.url;
      errors.push(origem ? `${msg.text()} [${origem}]` : msg.text());
    }
  };
  const onPageError = (err) => errors.push(String(err));

  page.on('console', onError);
  page.on('pageerror', onPageError);

  const resposta = await page.goto(url, { waitUntil: 'networkidle' });

  // Status HTTP e URL final. O `page.goto` NÃO lança em 4xx/5xx, e as telas de
  // erro (`errors/403.php`, `404.php`, `500.php`) são HTML puro, sem `<script>`
  // e sem `[x-data]` — a sonda de Alpine abaixo devolve `null` nelas e a tela
  // entraria como ✅.
  //   - Status: o Chromium **hoje** loga "Failed to load resource: … 500" para
  //     documento principal com erro, então o 500 já caía no listener de
  //     console. Conferir aqui não é redundância inútil: o gate deixa de
  //     depender de um comportamento de console não documentado (e de a lista
  //     IGNORE nunca engolir essa mensagem) e passa a dizer *qual* status veio.
  //   - URL final: este era o furo de verdade. Redirect não gera erro de
  //     console nenhum — `/dashboard` respondendo 302 → `/login` (sessão caída)
  //     imprimia ✅ tendo validado o formulário de login. Verificado contra um
  //     servidor-dublê: 500/403/404 davam ❌, o redirect dava ✅.
  if (!resposta) {
    errors.push('a navegação não devolveu resposta HTTP');
  } else if (!resposta.ok()) {
    errors.push(`HTTP ${resposta.status()} (esperado 2xx)`);
  }

  const caminhoPedido = new URL(url).pathname;
  const caminhoFinal  = new URL(page.url()).pathname;
  if (caminhoFinal !== caminhoPedido) {
    errors.push(`terminou em ${caminhoFinal} (pedimos ${caminhoPedido}) — redirecionado`);
  }

  // Alpine vivo? Se o CSP ou a ordem de scripts quebrar, isto falha.
  if (expectAlpine) {
    const alpineOk = await page.evaluate(() => {
      // Tela sem componente (ex.: /login, layout guest) não carrega Alpine
      // de propósito — só é erro faltar Alpine onde existe [x-data].
      const roots = document.querySelectorAll('[x-data]');
      if (roots.length === 0) return null;
      if (!window.Alpine) return 'Alpine não carregou';
      // x-data inicializado deixa o elemento com a marca do Alpine.
      const initialized = [...roots].some((el) => el._x_dataStack || el.__x);
      return initialized ? null : 'nenhum componente [x-data] inicializou';
    });
    if (alpineOk) errors.push(alpineOk);
  }

  page.off('console', onError);
  page.off('pageerror', onPageError);

  if (errors.length) {
    failures.push(`❌ ${name} (${url})\n   ` + errors.join('\n   '));
    console.log(`❌ ${name}`);
  } else {
    console.log(`✅ ${name}`);
  }
}

/**
 * Chromium do Playwright quando existir; se ele não foi baixado, cai no Chrome
 * da máquina em vez de abortar pedindo `npx playwright install` — dizendo qual
 * navegador rodou, porque um smoke que não conta o que fez não vale nada.
 */
async function abrirNavegador() {
  if (CHANNEL) {
    return chromium.launch({ channel: CHANNEL });
  }

  try {
    return await chromium.launch();
  } catch (e) {
    if (!/Executable doesn't exist/.test(String(e))) throw e;
    console.log('⚠  Chromium do Playwright ausente (npx playwright install chromium) — usando o Chrome local.');
    return chromium.launch({ channel: 'chrome' });
  }
}

const browser = await abrirNavegador();
const page = await browser.newPage();

// Público
await check(page, 'Login', `${BASE}/login`);

// Painel
await page.goto(`${BASE}/login`);
await page.fill('input[name="email"]', EMAIL);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForLoadState('networkidle');

// Login que não logou faria todas as telas abaixo serem só o /login de novo.
if (new URL(page.url()).pathname === '/login') {
  failures.push(`❌ Login (${BASE}/login)\n   credencial recusada: as telas internas não foram abertas`);
  console.log('❌ Login — credencial recusada');
} else {
  await check(page, 'Dashboard', `${BASE}/dashboard`);
  await check(page, 'Clientes', `${BASE}/clientes`);
  await check(page, 'Conteúdo', `${BASE}/conteudo`);
  await check(page, 'Tarefas', `${BASE}/tarefas`);
  await check(page, 'Financeiro', `${BASE}/financeiro`);
}

// Portal do cliente (a tela dos bugs de defer)
await check(page, 'Portal — início', `${BASE}/portal/${PORTAL_TOKEN}`);
await check(page, 'Portal — envio de arquivos', `${BASE}/portal/${PORTAL_TOKEN}/arquivos`);

await browser.close();

if (failures.length) {
  console.error('\n' + failures.join('\n\n'));
  console.error(`\n${failures.length} tela(s) com erro de JS/CSP.`);
  process.exit(1);
}

console.log('\n✅ Nenhum erro de console; componentes Alpine inicializaram.');
