/**
 * Cliente HTTP padrão do YVE Agency (FE-03).
 *
 * Todo `fetch` do app passa por aqui. Antes, cada view escrevia o seu — e o
 * padrão era `try { ... } catch {}`: erro de rede ou 500 do servidor sumia em
 * silêncio e a tela ficava parada, sem explicar nada ao usuário (foi assim que
 * o upload travou em 0% e ninguém viu por quê).
 *
 * O que este módulo garante:
 *   - CSRF: injeta X-CSRF-Token (da <meta>) em POST/PUT/PATCH/DELETE — inclusive
 *     no portal, que agora valida CSRF nas mutações (SEC-08);
 *   - erro nunca é silencioso: status != 2xx vira ApiError com a mensagem do
 *     servidor (campo `error` do JSON) ou uma mensagem legível;
 *   - timeout: requisição pendurada falha em vez de esperar para sempre;
 *   - resposta não-JSON (ex.: HTML de erro 500) vira erro claro, não crash de
 *     parse.
 *
 * Uso:
 *   const data = await api.post('/rota', { campo: 1 });
 *   try { ... } catch (e) { if (e instanceof ApiError) mostrar(e.message); }
 */

class ApiError extends Error {
  constructor(message, status, payload) {
    super(message);
    this.name = 'ApiError';
    this.status = status;   // 0 = falha de rede/timeout
    this.payload = payload; // corpo JSON do erro, quando houver
  }

  /** Erro de rede/timeout (vale sugerir "tente de novo"), não erro de regra. */
  get isNetwork() {
    return this.status === 0;
  }
}

const DEFAULT_TIMEOUT = 30000;

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function request(method, url, body = null, options = {}) {
  const timeout = options.timeout ?? DEFAULT_TIMEOUT;
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  };

  if (method !== 'GET') {
    headers['X-CSRF-Token'] = csrfToken();
  }

  const init = { method, headers, signal: AbortSignal.timeout(timeout) };

  if (body instanceof FormData) {
    init.body = body; // o browser define o Content-Type com o boundary
  } else if (body !== null) {
    headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(url, init);
  } catch (e) {
    const msg = e?.name === 'TimeoutError'
      ? 'A operação demorou demais. Verifique sua conexão e tente de novo.'
      : 'Falha de conexão. Verifique sua internet e tente de novo.';
    throw new ApiError(msg, 0, null);
  }

  // 204/205: sucesso sem corpo.
  if (response.status === 204 || response.status === 205) {
    return null;
  }

  let data = null;
  const isJson = (response.headers.get('content-type') || '').includes('application/json');
  if (isJson) {
    try {
      data = await response.json();
    } catch {
      data = null;
    }
  }

  if (!response.ok) {
    // 419 = token expirado: a sessão morreu; recarregar é a única saída sã.
    const fallback = response.status === 419
      ? 'Sua sessão expirou. Atualize a página e tente de novo.'
      : `Não foi possível concluir (erro ${response.status}).`;

    throw new ApiError(data?.error || fallback, response.status, data);
  }

  // 2xx sem JSON quando se esperava JSON: normalmente é HTML de erro/redirect.
  if (!isJson) {
    throw new ApiError('Resposta inesperada do servidor.', response.status, null);
  }

  return data;
}

const api = {
  get:    (url, options)       => request('GET', url, null, options),
  post:   (url, body, options) => request('POST', url, body ?? {}, options),
  put:    (url, body, options) => request('PUT', url, body ?? {}, options),
  patch:  (url, body, options) => request('PATCH', url, body ?? {}, options),
  delete: (url, body, options) => request('DELETE', url, body ?? {}, options),
};

// Escopo global de propósito: as views carregam <script> clássico (sem bundler),
// então nada de `export` aqui — quebraria com SyntaxError.
window.api = api;
window.ApiError = ApiError;
