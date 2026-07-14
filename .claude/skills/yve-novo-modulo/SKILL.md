---
name: yve-novo-modulo
description: Use ao adicionar um módulo/CRUD novo ou uma nova tela ponta a ponta no YVE Agency, seguindo as convenções do projeto (rota pt+en, controller com guardas de permissão, service, repository com escopo agency_id, migration Phinx, view com e()/CSRF, activity log). Aciona em "criar módulo X", "adicionar CRUD de Y", "nova tela/entidade neste projeto", "quero implementar a feature Z aqui".
---

# Receita: adicionar um módulo ao YVE Agency

Siga esta ordem. Ela reproduz o padrão já usado por Clientes, Faturas e Tarefas. Antes de começar, carregue `yve-arquitetura` (invariantes) e `yve-seguranca` (checklist).

## Passo 1 — Migration (Phinx ou SQL cru)

Crie em `database/migrations/`. **Sempre** inclua `agency_id`, FKs com `ON DELETE`, índices em FKs e colunas de filtro, timestamps. Dinheiro é `DECIMAL`, nunca float.

```php
final class CreateExemplos extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS exemplos (
                id          BIGSERIAL PRIMARY KEY,
                agency_id   BIGINT NOT NULL REFERENCES agencies(id) ON DELETE CASCADE,
                client_id   BIGINT REFERENCES clients(id) ON DELETE CASCADE,
                name        VARCHAR(255) NOT NULL,
                status      VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS exemplos_agency_idx ON exemplos (agency_id, status)");
    }
    public function down(): void { $this->execute("DROP TABLE IF EXISTS exemplos"); }
}
```
> Nota: migrations recentes de Drive/ClickUp esqueceram FKs — **não** repita esse erro (ver `yve-roadmap` SCHEMA-01). Rode `composer migrate` para aplicar.

## Passo 2 — Repository

Herde `Core\Repository`. Escope por agência. Prefira `RETURNING id` a `lastInsertId()`.

```php
final class ExemploRepository extends Repository
{
    protected string $table = 'exemplos';

    public function listByAgency(int $agencyId, int $page, int $perPage = 20): array
    {
        return $this->paginate(
            "SELECT * FROM exemplos WHERE agency_id = :a ORDER BY created_at DESC",
            [':a' => $agencyId], $page, $perPage
        );
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first(
            "SELECT * FROM exemplos WHERE id = :id AND agency_id = :a LIMIT 1",
            [':id' => $id, ':a' => $agencyId]
        );
    }

    public function createReturning(array $data): int
    {
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_map(fn($k) => ":$k", array_keys($data)));
        $stmt = $this->pdo->prepare("INSERT INTO exemplos ($cols) VALUES ($ph) RETURNING id");
        $stmt->execute(array_combine(array_map(fn($k) => ":$k", array_keys($data)), array_values($data)));
        return (int) $stmt->fetchColumn();
    }
}
```

## Passo 3 — Service

Regra de negócio + validação. Nunca confie no input; valide tipo/faixa/formato. Grave `activity_logs` em ação sensível.

```php
final class ExemploService
{
    public function __construct(private readonly ExemploRepository $repo) {}

    public function create(array $input, int $agencyId, int $userId): array
    {
        $errors = $this->validate($input);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $id = $this->repo->createReturning([
            'agency_id' => $agencyId,
            'client_id' => (int) ($input['client_id'] ?? 0) ?: null,
            'name'      => trim($input['name']),
            'status'    => 'active',
            'created_at'=> date('Y-m-d H:i:s'),
        ]);

        \App\Support\ActivityLogger::log('exemplo_created', 'exemplos', $userId, $id, ['name' => $input['name']]);
        return ['success' => true, 'id' => $id];
    }

    private function validate(array $in): array
    {
        $e = [];
        if (trim($in['name'] ?? '') === '') $e[] = 'Nome é obrigatório.';
        return $e;
    }
}
```

## Passo 4 — Controller

Guarda de permissão em **todo** método. Se a rota é de um cliente específico, também exija `ClientAccessMiddleware` na rota e/ou `Auth::requireClientAccess($clientId)`.

```php
final class ExemploController extends Controller
{
    public function __construct(private readonly ExemploService $service) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('exemplos.view');
        $page = max(1, (int) $request->query('page', '1'));
        $paginated = $this->service->list((int) Auth::agencyId(), $page);
        return $this->view('exemplos.index', compact('paginated'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('exemplos.create');
        $data = $request->only('name', 'client_id');
        $result = $this->service->create($data, (int) Auth::agencyId(), (int) Auth::id());
        if (!$result['success']) {
            $this->withErrors($result['errors'])->withInput($data);
            return $this->redirect('/exemplos/novo');
        }
        $this->withSuccess('Criado com sucesso.');
        return $this->redirect('/exemplos/' . $result['id']);
    }
}
```

## Passo 5 — Permissões

Adicione os slugs em `config/permissions.php` (`'exemplos.view' => 'Ver exemplos'`, etc.) e associe aos roles no seeder (`database/seeders/InitialDataSeeder.php`). Sem isso, ninguém acessa.

## Passo 6 — Rotas (pt + en)

Em `routes/web.php`, dentro do grupo `[AuthMiddleware::class]`:

```php
$router->get('/exemplos',            [ExemploController::class, 'index']);
$router->get('/exemplos/novo',       [ExemploController::class, 'create']);
$router->post('/exemplos',           [ExemploController::class, 'store'], [CsrfMiddleware::class]);
$router->get('/exemplos/{id}',       [ExemploController::class, 'show']);
$router->put('/exemplos/{id}',       [ExemploController::class, 'update'], [CsrfMiddleware::class]);
$router->delete('/exemplos/{id}',    [ExemploController::class, 'destroy'], [CsrfMiddleware::class]);
// aliases en: /examples ... (mesmos handlers)
```
**CSRF em todo POST/PUT/DELETE.** Rota de cliente leva `[..., ClientAccessMiddleware::class]`.

## Passo 7 — View

`resources/views/exemplos/index.php`. `e()` em toda saída, `csrf_field()` em todo form, os 4 estados (carregando/vazio/erro/sucesso). Reutilize as classes utilitárias do layout (`.card`, `.btn-primary`, `.input-field`, `.label-field`) e **carregue a skill `yve-frontend`** — tokens do design system (nada hardcoded), erros comuns e o checklist de tela vivem lá.

```php
<?php view_layout('app'); view_start('content'); ?>
<h1 class="text-xl font-bold text-white mb-4"><?= e('Exemplos') ?></h1>
<?php if (empty($paginated['items'])): ?>
  <p class="text-gray-500">Nenhum registro ainda.</p>
<?php else: foreach ($paginated['items'] as $row): ?>
  <div class="card p-4"><?= e($row['name']) ?></div>
<?php endforeach; endif; ?>
<?php view_end(); ?>
```

## Passo 8 — Testes + gates

- Teste PHPUnit do Service (regra + validação) e teste de permissão negativa (sem `exemplos.view` → 403).
- `composer test` e `composer analyse` verdes; `composer audit` limpo.
- Se houver mutação de dados no frontend, valide `response.ok` no `fetch` e trate loading/erro.

Para validar a tela rodando de verdade, use a skill global `visual-validation`.
