---
name: yve-arquitetura
description: Use ao trabalhar em QUALQUER parte do código do YVE Agency — para entender o micro-framework próprio (Router, Container, Pipeline, Repository com escopo agency_id, Services, Views PHP) e respeitar as invariantes da arquitetura. Aciona ao adicionar/alterar rota, controller, service, repository, middleware ou view neste projeto, ou ao responder "como funciona o X aqui", "onde fica a lógica de Y", "por que a query não filtra por agência".
---

# Arquitetura do YVE Agency (referência para agentes)

PHP 8.3 puro, sem Laravel. Micro-framework MVC próprio em `app/Core`. PostgreSQL (Supabase). Frontend Tailwind + Alpine.js. **Multi-tenant por `agency_id`.** Leia isto antes de mexer no código; o objetivo é você seguir os padrões que já existem, não inventar novos.

## Fluxo de uma requisição

```
public/index.php  → .env + sessão segura + Container + Router::dispatch(Request)
Router            → casa rota por regex, monta Pipeline de middlewares
Pipeline          → Csrf → RateLimit → Auth → Permission → ClientAccess → Controller
Controller        → orquestra; SEM SQL, SEM regra de negócio
Service           → regra de negócio
Repository        → SQL isolado, prepared statements, escopo agency_id
Response          → view | json | redirect (headers de segurança no send())
```

## Camadas e onde cada coisa mora

| Preciso de… | Vá para | Regra |
|-------------|---------|-------|
| Nova URL | `routes/web.php` (ou `api.php`) | Registrar em **pt e en** (o projeto mantém os dois — ver nota abaixo) |
| Entrada/saída HTTP | `app/Controllers/` | 1 método público = 1 rota. Chama Service, devolve `Response`. Nada de SQL aqui. |
| Regra de negócio | `app/Services/` | Recebe repositórios por injeção de construtor. |
| SQL | `app/Repositories/` | Herda `Core\Repository`. **Sempre** prepared statements. |
| Autorização | `App\Support\Auth` + `Middlewares/` | Ver `yve-seguranca`. |
| Template | `resources/views/` | PHP nativo + `e()` obrigatório. Layout via `view_layout()`. |
| Integração externa | `app/Services/` (ex.: `MetaAdsService`) | Guzzle; segredos via `Core\Secret`. |
| Job assíncrono | `app/Jobs/` + tabela `jobs` | `handle(array $payload)`, idempotente. |

## Invariantes que você NÃO pode quebrar

1. **Controller não tem SQL nem regra.** Só orquestra Service e devolve `Response`.
2. **Toda query via Repository com prepared statement.** Zero concatenação de input em SQL. Identificador dinâmico (coluna/ORDER BY) só via allowlist.
3. **Escopo `agency_id` sempre.** Ou pelo `Core\Repository::agencyScope()` automático, ou passando `$agencyId` explícito (`findByIdAndAgency`). Nunca uma query multi-tenant sem filtro de agência.
4. **Toda saída de template com `e()`.** Nunca `echo` cru de dado do usuário. Ver `yve-seguranca`.
5. **Permissão validada no backend** (`Auth::requirePermission('modulo.acao')` no início do método do controller), não só escondendo botão na view.
6. **Ação sensível grava `activity_logs`** via `App\Support\ActivityLogger::log(...)`.
7. **Segredo só via `.env`/`Core\Crypto`/`Core\Secret`.** Nunca hardcoded, nunca em texto puro no banco (`platform_settings` também cifra as chaves sensíveis desde SEC-05).

> Violação conhecida e mapeada: `DashboardController` tem SQL direto (item ARCH-01 do roadmap). Não use como referência — e não crie outra.

## Como o Container resolve dependências

`Container` faz auto-wiring por `ReflectionClass`: type-hint uma dependência no construtor e ela é injetada. Não use `new` para Services/Repositories dentro de controllers — deixe o Container resolver.

```php
final class ExemploController extends Controller
{
    public function __construct(
        private readonly ExemploService $service,   // injetado automaticamente
    ) {}
}
```

## Repository — o que a base já te dá

`Core\Repository` (herde dela, defina `protected string $table`):
- `query/first/all($sql, $params)` — prepared statements.
- `insert($data)` — mas retorna `lastInsertId()` (frágil no PG; prefira `RETURNING id` como fazem `ContentPlanRepository`/`ClientRepository`).
- `update($data, $where)`, `delete($where)`, `findById($id)` (com escopo de agência).
- `paginate($sql, $params, $page, $perPage)` — COUNT + LIMIT/OFFSET, retorna metadados.
- `agencyScope()` / `bindAgency()` — filtro multi-tenant automático.

## Views — layout e sections

```php
<?php view_layout('app'); ?>            // layouts/app.php
<?php view_start('title'); ?>Título<?php view_end(); ?>
<?php view_start('content'); ?>
  <h1><?= e($algumDado) ?></h1>          // SEMPRE e()
  <?= csrf_field() ?>                    // em todo <form>
<?php view_end(); ?>
```
Layouts disponíveis: `app` (painel tenant), `admin` (platform), `portal` (cliente), `guest` (login), `print` (PDF).

## Notas de contexto importantes

- **Rotas pt+en:** hoje cada endpoint existe nos dois idiomas (`/clientes` e `/clients`). Ao adicionar rota, mantenha o par para não quebrar links. (Há proposta de unificar — ver `yve-roadmap`.)
- **Dois níveis de admin:** `PlatformAdminMiddleware` protege `/admin/*` (dono do SaaS); `AuthMiddleware` protege o painel da agência. Platform admin **não** tem `agency_id` e bypassa RBAC de tenant.
- **Portal é público por token:** `PortalMiddleware` resolve o cliente por `portal_token` na URL; usa `PortalAuth` (não `Auth`). Fala o idioma do cliente, não o da sessão.
- **Qualidade:** rode `composer test` (PHPUnit) e `composer analyse` (PHPStan nível 6, hoje **zerado** — mantenha assim) antes de finalizar.

Para segurança, veja `yve-seguranca`. Para telas (tokens, estados, JS), veja `yve-frontend`. Para adicionar um módulo completo, veja `yve-novo-modulo`. Para o backlog priorizado, veja `yve-roadmap` (fonte: `docs/PLANO_MESTRE.md`).
