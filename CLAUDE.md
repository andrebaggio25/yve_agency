# CLAUDE.md — YVE Agency

Plataforma SaaS multi-tenant de gestão de agência de marketing. **PHP 8.3 puro** (micro-framework MVC próprio, sem Laravel), PostgreSQL (Supabase), Tailwind + Alpine.js, hospedado em Hostinger compartilhada. Idioma do projeto e das respostas: **pt-BR**.

## Comandos

```bash
composer test        # PHPUnit (obrigatório antes de finalizar)
composer analyse     # PHPStan nível 6 — manter em 0 erros
composer audit       # sem advisories
composer migrate     # Phinx (produção também roda por /admin/migrations)
composer cs-fix      # PHP-CS-Fixer
php -S localhost:8000 router.php   # dev server

npm install          # uma vez
npm run build        # CSS purgado + vendor self-hosted → public/ (VERSIONADO)
npm run dev          # watch do Tailwind
npm run test:browser # smoke em Chromium real: falha se houver erro de console/CSP

# Banco de teste (testes de feature; sem ele, eles se auto-skipam)
docker compose -f docker-compose.test.yml up -d
composer db:test     # migrations no banco de teste (NUNCA em produção)
```

Não finalize nenhuma tarefa com teste vermelho, erro novo no PHPStan ou advisory novo no audit.
**Mexeu em `resources/css/app.css` ou `tailwind.config.js`? Rode `npm run build` e commite `public/css/app.css`** — o hosting compartilhado não roda build no deploy.

## Arquitetura (resumo — detalhe na skill `yve-arquitetura`)

```
public/index.php → Router → Pipeline(middlewares) → Controller → Service → Repository → PDO
```

- `routes/web.php` — rotas em **pt e en** (sempre registrar o par)
- `app/Controllers/` — 1 método = 1 rota; `Auth::requirePermission()` no topo; **sem SQL, sem regra**
- `app/Services/` — regra de negócio; DI por construtor (Container auto-wire)
- `app/Repositories/` — SQL isolado, prepared statements, **escopo `agency_id` sempre**
- `resources/views/` — PHP nativo; `e()` em toda saída; `csrf_field()` em todo form; layouts `app|admin|portal|guest|print`
- `resources/css/app.css` + `tailwind.config.js` — **design system único** (tokens, `.card`, `.btn-*`); acento é a var `--accent` (violeta; `[data-theme="admin"]` = vermelho). Zero CDN em runtime.
- `app/Automations/` + tabela `jobs` — regras agendadas e fila (cron HTTP em `/queue/*`)

## Invariantes inegociáveis

1. Query multi-tenant **sempre** filtra por `agency_id` (escopo automático ou `findByIdAndAgency`).
2. RBAC no backend (`Auth::requirePermission`), nunca só escondendo botão. Rota de cliente = `ClientAccessMiddleware`.
3. Prepared statements; identificador dinâmico só por allowlist.
4. `e()` em toda saída de template; `innerHTML` só com DOMPurify; `json_encode` com flags `JSON_HEX_*` em `<script>`.
5. CSRF em todo POST/PUT/DELETE (`CsrfMiddleware` ou header `X-CSRF-Token`).
6. Segredos cifrados (`Core\Secret`); nunca hardcoded; `.env*` fora do git.
7. Ação sensível grava `activity_logs` (`ActivityLogger::log`).
8. Dinheiro é `DECIMAL`; migrations com FKs + `ON DELETE` + índices, reversíveis.

## Skills do projeto (carregar conforme a tarefa)

| Skill | Quando |
|-------|--------|
| `yve-arquitetura` | Qualquer mudança de código — padrões e invariantes |
| `yve-seguranca` | Checklist antes de merge; auth, upload, integração, query |
| `yve-novo-modulo` | Criar módulo/CRUD/tela ponta a ponta |
| `yve-frontend` | Qualquer tela — tokens, estados, JS, anti-genérico |
| `yve-roadmap` | Pegar item do backlog (IDs UP-01, FE-01, …) |
| `yve-analise-produto` | Rodar/atualizar a análise de produto padronizada |

## Documentação (fonte de verdade em `docs/`)

- **[PLANO_MESTRE.md](docs/PLANO_MESTRE.md)** — a verdade absoluta: backlog priorizado com IDs. Item concluído é marcado ✅ lá, com data.
- [ANALISE_PRODUTO.md](docs/ANALISE_PRODUTO.md) — SWOT + nota 0–10 por módulo (ciclo atual).
- [ANALISE_SISTEMA.md](docs/ANALISE_SISTEMA.md) — fotografia técnica (arquitetura, segurança, banco).
- [GUIA_SISTEMA.md](docs/GUIA_SISTEMA.md) — guia funcional de uso.
- [PLANO_FASES.md](docs/PLANO_FASES.md) — visão original das 12 fases do produto.
- **[OPERACAO.md](docs/OPERACAO.md)** — manual de operação: monitoramento (`/api/health`), alertas, backup, deploy, validação de integrações.
- [CRON.md](docs/CRON.md) · [EVOLUTION_API_INTEGRACAO.md](docs/EVOLUTION_API_INTEGRACAO.md) — operação.
- `docs/historico/` — ciclos de análise/plano arquivados (padrão `NOME_AAAA-MM-DD.md`).

## Contexto operacional

- Hostinger compartilhada trava upload em 256M (motivo do item UP-01: upload direto browser→Drive). Existe uma VPS disponível — candidata a rodar o worker de fila (INFRA-01), não a migração total.
- Deploy sem CLI de banco: migrations rodam pelo painel `/admin/migrations`.
- Supabase usa pooler — cuidado com `ATTR_PERSISTENT` (INFRA-02).
- Portal do cliente é público por capability-token na URL (design aceito); toda mutação nova no portal precisa considerar CSRF (SEC-08).
