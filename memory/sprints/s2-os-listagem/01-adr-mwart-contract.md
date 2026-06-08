# ADR MWART-0001 — Contrato Module Web App React Transition

**Status:** Proposed
**Date:** 2026-05-06
**Sprint:** 2
**Supersedes:** —

## Contexto

O Oimpresso ERP tem 30 módulos nWidart, ~21 ativos, com >200 rotas Blade legadas (UltimatePOS v6 base + módulos próprios). Migrar tudo de uma vez é inviável e arriscado. Precisamos de um padrão que permita migração **rota a rota**, com rollback instantâneo e zero re-aprendizado pelo cliente.

Restrições não-negociáveis do oimpresso:

1. **Multi-tenant por `business_id`** — toda query precisa scopar (skill `multi-tenant-patterns`).
2. **Permissions Spatie já existentes** — `repair.view`, `repair.view_own`, `repair.create`, etc. Não criar gates paralelos.
3. **Sidebar canônico** vive em `DataController.modifyAdminMenu` + `SIDEBAR_GROUPS` no React (skill `sidebar-menu-arch`). **Não** introduzir `LegacyMenuAdapter` paralelo.
4. **Stack middleware UltimatePOS** obrigatória: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']` (CLAUDE.md §5).
5. **Inertia v3 + React 19 + AppShellV2** já em produção; novas Pages devem ter `Component.layout` (memória `preference_persistent_layouts`).

## Decisão

Adotar o padrão **MWART** (Module Web App React Transition), com três pilares:

### 1. Controller dual-mode com flag por rota

Durante a migração, cada controller responde dois modos baseado em `config/mwart.php`:

```php
// Modules/Repair/Http/Controllers/RepairController.php
public function index(Request $req)
{
    $business_id = $req->session()->get('user.business_id');

    if (! $this->canViewRepair($business_id)) {
        abort(403);
    }

    if ($this->mwartEnabled('repair_index', $business_id)) {
        return Inertia::render('Repair/Index', $this->buildIndexData($req));
    }

    // Caminho Blade legacy preservado
    return $this->renderBladeIndex($req);
}

private function mwartEnabled(string $key, int $business_id): bool
{
    if (! config("mwart.{$key}.enabled")) {
        return false;
    }
    $beta = config("mwart.{$key}.business_ids", []);
    return empty($beta) || in_array($business_id, $beta, true);
}
```

`config/mwart.php`:

```php
return [
    'repair_index' => [
        'enabled' => env('MWART_REPAIR_INDEX', false),
        'business_ids' => array_filter(explode(',', (string) env('MWART_REPAIR_INDEX_BIZ', ''))),
    ],
    // uma entrada por rota migrada
];
```

`.env` exemplos:

```env
# staging — todos os business
MWART_REPAIR_INDEX=true
MWART_REPAIR_INDEX_BIZ=

# prod — só ROTA LIVRE (business_id=4) primeiro
MWART_REPAIR_INDEX=true
MWART_REPAIR_INDEX_BIZ=4
```

Rollout gradual = adicionar mais ids; rollback total = `MWART_REPAIR_INDEX=false`.

### 2. Sidebar permanece como está (sem `LegacyMenuAdapter`)

O menu já é montado em React por `SIDEBAR_GROUPS` consumindo o que `DataController.modifyAdminMenu` adicionou via `ModuleUtil`. **Migrar uma rota MWART não muda nada no menu** — a URL é a mesma (`/repair/repair`), o controller que decide Blade ou Inertia.

Vantagem: zero divergência visual, zero risco de quebrar sidebar de cliente, sem código novo de adapter.

### 3. Props compartilhadas via `HandleInertiaRequests`

Toda Page Inertia recebe shared props canônicas via middleware existente (`app/Http/Middleware/HandleInertiaRequests.php`):

```php
'auth' => fn () => [
    'user' => $req->user()?->only(['id', 'first_name', 'last_name', 'email']),
    'business_id' => $req->session()->get('user.business_id'),
    'permissions' => $this->userPermissions($req->user()),
],
'business' => fn () => $this->currentBusiness($req),
'flash' => fn () => [
    'success' => $req->session()->get('status'),
    'error' => $req->session()->get('error'),
],
'features' => fn () => $this->mwartFlags($req),
```

Pages React leem via `usePage<SharedProps>().props`. Permissions chegam pré-resolvidas (não chamar `can()` no React).

## Consequências

✅ **Pros**
- Migração rota a rota, baixo risco
- Rollback = mudar 1 env var (ou 1 lista de business_ids)
- Cliente final não percebe (URL e menu iguais)
- Padrão replicável em qualquer um dos 21 módulos
- A/B testing por business_id já no contrato (multi-tenant first)
- Zero código novo de menu/sidebar

❌ **Cons**
- Controller mantém código duplicado durante transição (Blade view + Inertia render)
- 2 caminhos de teste por rota durante soak
- Risco de drift se Blade e React divergirem em correções

## Mitigações

- **Drift Blade↔React:** todo bug fix em rota MWART tem que ser aplicado nos 2 caminhos até flag virar 100% e Blade ser deletado. Checklist no PR template.
- **Code duplication:** Controller compartilha `buildIndexData()` privado entre Blade e Inertia (só o `return` muda).
- **Deletar Blade:** após 30 dias com flag 100% on (todos `business_ids`) e zero rollback, deletar `*.blade.php` + cleanup do controller.

## Compliance

- [ ] Toda PR MWART precisa do label `mwart`
- [ ] Toda PR MWART precisa atualizar `memory/migrations.md` com data/rota/PR/business_ids beta
- [ ] CI roda Pest Feature pros 2 caminhos (Blade + Inertia) até a flag ir pra 100%
- [ ] Todo Resource tem teste que valida `business_id` scope (skill `multi-tenant-patterns`)

## Referências

- CLAUDE.md §1, §5 (stack canônica + middlewares obrigatórios)
- DESIGN.md (AppShellV2 + tokens + atalhos)
- Skill `multi-tenant-patterns`
- Skill `sidebar-menu-arch`
- Skill `publication-policy` (decide quem aprova flag flip em prod)
- ADR 0011 (módulos de referência canônica: Jana/Repair/Project)
- Memória `preference_persistent_layouts`
- Memória `cliente_rotalivre` (business_id=4 = beta natural)
- Inertia v3 docs: https://inertiajs.com/
