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
 * FALHA se houver qualquer erro de console ou se um componente Alpine não
 * inicializar.
 *
 * Uso:
 *   npm run test:browser                      # usa http://localhost:8000
 *   BASE_URL=... PORTAL_TOKEN=... npm run test:browser
 *
 * Requer o app rodando e um login válido (SMOKE_EMAIL / SMOKE_PASSWORD).
 */

import { chromium } from 'playwright';

const BASE = process.env.BASE_URL || 'http://localhost:8000';
const EMAIL = process.env.SMOKE_EMAIL;
const PASSWORD = process.env.SMOKE_PASSWORD;
const PORTAL_TOKEN = process.env.PORTAL_TOKEN;

// Ruído que não indica quebra (favicon ausente, extensão do browser…).
const IGNORE = [/favicon/i, /net::ERR_ABORTED.*favicon/i];

const failures = [];

async function check(page, name, url, expectAlpine = true) {
  const errors = [];
  const onError = (msg) => {
    if (msg.type() === 'error' && !IGNORE.some((re) => re.test(msg.text()))) {
      errors.push(msg.text());
    }
  };
  const onPageError = (err) => errors.push(String(err));

  page.on('console', onError);
  page.on('pageerror', onPageError);

  await page.goto(url, { waitUntil: 'networkidle' });

  // Alpine vivo? Se o CSP ou a ordem de scripts quebrar, isto falha.
  if (expectAlpine) {
    const alpineOk = await page.evaluate(() => {
      if (!window.Alpine) return 'Alpine não carregou';
      // x-data inicializado deixa o elemento com a marca do Alpine.
      const roots = document.querySelectorAll('[x-data]');
      if (roots.length === 0) return null; // tela sem componente: ok
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

const browser = await chromium.launch();
const page = await browser.newPage();

// Público
await check(page, 'Login', `${BASE}/login`);

// Painel (precisa de credenciais)
if (EMAIL && PASSWORD) {
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  await check(page, 'Dashboard', `${BASE}/dashboard`);
  await check(page, 'Clientes', `${BASE}/clientes`);
  await check(page, 'Conteúdo', `${BASE}/conteudo`);
  await check(page, 'Tarefas', `${BASE}/tarefas`);
  await check(page, 'Financeiro', `${BASE}/financeiro`);
} else {
  console.log('⏭  Painel pulado (defina SMOKE_EMAIL e SMOKE_PASSWORD)');
}

// Portal do cliente (a tela dos bugs de defer)
if (PORTAL_TOKEN) {
  await check(page, 'Portal — início', `${BASE}/portal/${PORTAL_TOKEN}`);
  await check(page, 'Portal — envio de arquivos', `${BASE}/portal/${PORTAL_TOKEN}/arquivos`);
} else {
  console.log('⏭  Portal pulado (defina PORTAL_TOKEN)');
}

await browser.close();

if (failures.length) {
  console.error('\n' + failures.join('\n\n'));
  console.error(`\n${failures.length} tela(s) com erro de JS/CSP.`);
  process.exit(1);
}

console.log('\n✅ Nenhum erro de console; componentes Alpine inicializaram.');
