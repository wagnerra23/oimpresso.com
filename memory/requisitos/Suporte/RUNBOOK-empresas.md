---
title: "Suporte — Runbook da tela Empresas (escolher empresa-cliente)"
module: Suporte
tela: Suporte/Empresas
owner: W
status: rascunho
last_validated: "2026-06-23"
preconditions:
  - "Backend fase B no main (SupportAccessService + EnsureSupportAccess + SupportClientViewService)"
  - "Usuário com capability de suporte ativa (support_agents) e NÃO superadmin"
steps:
  - "Controller SupportController@index resolve empresas acessíveis (exceto operador)"
  - "Rota GET /suporte/empresas com middleware support.access"
  - "Page Suporte/Empresas.tsx (PT-01 Lista, variante read-only lean) lista + 'Entrar (suporte)'"
related_adrs:
  - 0305-modo-suporte-cross-tenant-exceto-operador
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
---

# RUNBOOK — Suporte / Empresas (escolher empresa-cliente)

> **Tipo:** runbook reproduzível (F1 do MWART — ADR 0104).
> **Refs:** [ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md), [UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md).
> **Validado:** _pendente_ (F3 ainda não implementada — RUNBOOK escrito na F1).

Tela onde o **agente de suporte do operador** escolhe **qual empresa-cliente** vai atender. Lista somente as empresas acessíveis (**todas exceto a operadora biz=1** — `SupportAccessService::accessibleBusinessIds`), com ação **"Entrar (suporte)"** que leva à visão read-only do cliente (`Suporte/Visao`). Operador-interno; somente leitura. Layout: AppShellV2 + PT-01 Lista (variante read-only lean — sem BulkBar/Drawer/sub-tabs).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/suporte/empresas` | Login como agente de suporte → URL → lista aparece |
| AppShellV2 envolvendo | Inspetor: shell ao redor da Page |
| Operadora ausente | A empresa biz=1 **nunca** aparece na lista |
| Não-agente bloqueado | Usuário sem capability → 403 (middleware `support.access`) |
| Acesso auditado | Entrar numa empresa grava `support_access_logs` (na tela Visao, via middleware) |

## 1. Objetivo

O agente de suporte precisa de um ponto de entrada pra escolher **qual cliente** atender, sem nunca alcançar a empresa do operador. Esta tela é a porta: lista read-only das empresas-cliente acessíveis + ação de entrar. A autorização e a auditoria vivem no middleware `EnsureSupportAccess` (já no main); a tela só consome a resolução.

## 2. Pré-condições

- [ ] Backend fase B no `main`: `App\Services\Support\SupportAccessService`, `App\Http\Middleware\EnsureSupportAccess` (+ alias `support.access`), `App\SupportAgent`, `App\SupportAccessLog`.
- [ ] Config `constants.operator_business_id` (default 1).
- [ ] Usuário com `support_agents` ativo (`revoked_at IS NULL`) e **não** superadmin.
- [ ] Skill irmã: `multi-tenant-patterns` (Tier A) — leituras cross-tenant **explícitas** por `business_id`.
- [ ] Seed/fixture: `$this->seededTenant()` (biz=1 operador) + `Business::firstOrCreate(['id'=>99])` (cliente). NUNCA biz=4.

## 3. Passo-a-passo

### 1. Controller `SupportController@index` (app/, não módulo nWidart)

```php
// app/Http/Controllers/Support/SupportController.php
public function index(): \Inertia\Response
{
    $ids = $this->access->accessibleBusinessIds(); // exceto operador
    // SUPORTE: leitura cross-tenant explícita (ADR 0305) — nomes das empresas-cliente.
    $empresas = \App\Business::query()->whereIn('id', $ids->all())->orderBy('name')
        ->get(['id', 'name'])->map(fn ($b) => ['id' => (int) $b->id, 'name' => (string) $b->name])->values();

    return \Inertia\Inertia::render('Suporte/Empresas', ['empresas' => $empresas]);
}
```

**Validação:** `Pest` — agente vê a lista (200 Inertia, componente `Suporte/Empresas`), operadora ausente.

### 2. Rota com middleware `support.access`

```php
// routes/web.php
Route::middleware(['auth', 'SetSessionData', 'language', 'timezone', 'support.access'])
    ->prefix('suporte')->group(function () {
        Route::get('empresas', [\App\Http\Controllers\Support\SupportController::class, 'index'])->name('suporte.empresas');
        // empresas/{business} → Visao (RUNBOOK próprio)
    });
```

**Validação:** `php artisan route:list --path=suporte` → 2 rotas; `support.access` na stack.

### 3. Page `Suporte/Empresas.tsx` (PT-01 Lista, lean read-only)

```tsx
// resources/js/Pages/Suporte/Empresas.tsx
export default function Empresas({ empresas }: { empresas: Array<{id:number; name:string}> }) {
  // Slot 1 PageHeader + Slot 5 DataTable + EmptyState. Sem BulkBar/Drawer/sub-tabs.
}
Empresas.layout = (page) => <AppShellV2>{page}</AppShellV2>;
```

**Validação:** `npm run build:inertia` + `grep -i "Pages/Suporte/Empresas" public/build-inertia/manifest.json`.

## 4. Tokens CSS

| Token | Esta tela usa? |
|---|---|
| `--bg`, `--panel`, `--border`, `--text`, `--text-mute` | ✅ |
| `--accent`, `--accent-soft` (primary roxo 295) | ✅ (botão "Entrar (suporte)") |
| `--origin-*` | ❌ (sem origem-badge) |
| `--row-h`, `--card-pad` | ✅ |

Tokens shadcn semânticos (R-DS-002) — **sem cor crua**. Banner de Modo Suporte (na tela Visao) usa cor de **alerta** de propósito.

## 5. Estados visuais

| Estado | Trigger | Notas |
|---|---|---|
| `default` | lista cheia | `bg-panel border-border` |
| `hover` | mouse na linha | `hover:bg-panel-2` |
| `empty` | nenhuma empresa-cliente acessível | `<EmptyState>` "Nenhuma empresa-cliente acessível" |
| `loading` | `Inertia::defer` | skeleton |
| `error` | 403 / falha | middleware bloqueia não-agente (403) |

## 6. Responsividade

Lista PT-01 padrão. <768px: tabela vira lista de cards single-column; "Entrar" full-width.

## 7. Atalhos

| Tecla | Ação | Escopo |
|---|---|---|
| `/` | foco na busca local | tela |
| `⌘K` | palette global | shell |
| `Enter` | entrar na empresa focada | linha |

Read-only → sem `N`/BulkBar. `removeEventListener` no cleanup; bloqueio em `<input>`.

## 8. Component contract

```tsx
interface EmpresasPageProps {
  empresas: Array<{ id: number; name: string }>; // já exceto operador (SupportAccessService)
}
```

Componentes shared: [`PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx) · [`DataTable`](../../../resources/js/Components/shared/DataTable.tsx) · [`EmptyState`](../../../resources/js/Components/shared/EmptyState.tsx).

## 9. DoD checklist

- [ ] Dentro de `AppShellV2` (Persistent Layout)
- [ ] Tokens shadcn semânticos (sem cor crua)
- [ ] Estado em `localStorage` prefixo `oimpresso.suporte.*` (se houver)
- [ ] PT-BR em todo label/copy
- [ ] Dark mode (contraste ≥ 4.5:1)
- [ ] Estados: default/hover/empty/loading/error
- [ ] `npm run build:inertia` + manifest tem `Pages/Suporte/Empresas`
- [ ] Charter `Empresas.charter.md` ao lado (na F3)
- [ ] Pest: agente vê lista · operadora ausente · não-agente 403
- [ ] **Smoke biz=1** (ADR 0101) pós-deploy

## 10. Pegadinhas

- ❌ NÃO usar `route('suporte.empresas')` na Page — Ziggy indisponível. Use `` href={`/suporte/empresas/${id}`} ``.
- ❌ NÃO envolver em `<AppShell>` interno — Persistent Layout `Empresas.layout = AppShellV2`.
- ❌ NÃO reusar Services session/auth-bound (`CashRegisterUtil`, `payContact`) — a Visao usa `SupportClientViewService` com `business_id` **explícito** (auditoria de scoping 2026-06-23, ver SPEC §Desenho seguro).
- ❌ NÃO dar superadmin ao agente — os global scopes dão bypass cross-tenant pra superadmin; suporte precisa ficar **confinado**.
- ❌ NÃO `npm run build` — use `npm run build:inertia`.

## 11. ADR de origem

- [ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) — Modo Suporte (decisão-mãe desta tela).
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (e exceções).
- [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) · [UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md).

---

**Última atualização:** 2026-06-23 (F1 — pré-implementação; valida no smoke da F3).
