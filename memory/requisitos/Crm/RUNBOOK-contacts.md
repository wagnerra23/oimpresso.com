---
slug: crm-runbook-contacts
title: "Crm — Runbook da tela Contatos / Clientes (migração MWART)"
type: runbook
module: Crm
status: active
date: 2026-05-14
authors: [W+C]
---

# RUNBOOK — Contatos / Clientes (`/contacts`)

> **Tipo:** runbook de migração Blade → Inertia/React (MWART) — Fase F1 PLAN ADR 0104.
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md), [ADR 0141](../../decisions/0141-skill-migracao-blade-react.md)
> **Estado origem:** Blade legacy `view('contact.index')` em [ContactController@index](../../../app/Http/Controllers/ContactController.php) — DataTables AJAX + 14 views Blade (`index/create/create-page/edit/show/import/ledger/contact_basic_info/contact_more_info/contact_tax_info/contact_payment_info/contact_map`) + 5 partials.
> **Estado alvo:** `Pages/Crm/Contacts/{Index,Create,Edit,Show}.tsx` (Inertia v3 + React 19 + shadcn-style + AppShellV2 + Cockpit Pattern V2 ADR 0110).
> **Persona alvo canary:** **Filha do Martinho Caçambas** (biz=164 LIVE, operação/comercial) + **Dani financeiro** (champion duplo) — não-técnicas, monitor 1280px, ~18.845 contacts ativos. Análogo a Larissa ROTA LIVRE (biz=4) — vestuário. Pain-point #1 reunião: "tempo pra abrir uma venda, ou prospecção" → `/contacts` é onde prospecção começa.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/contacts?type=customer` renderiza Page React | DevTools mostra `data-page` com `"component":"Crm/Contacts/Index"` |
| Bundle Inertia builda | `npm run build:inertia && grep "Pages/Crm/Contacts" public/build-inertia/manifest.json` |
| AppShellV2 envolvendo | DOM tem `<div class="app-shell-v2">` ao redor da Page |
| Multi-tenant Tier 0 scopado | Login biz=164 só vê seus contacts (Pest test cross-tenant biz=1 vs biz=99 OBRIGATÓRIO — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) |
| Permissão `customer.view`/`supplier.view` respeitada | Login sem permissão → 403 + fallback Blade preservado |
| Busca instant (debounce 300ms) responde | digitar "Mar" mostra resultados em < 500ms p95 |
| CPF/CNPJ formatado | "12345678901" → "123.456.789-01" automaticamente |
| Velocidade abrir cadastro: < 1s | clique "Novo cliente" → Create.tsx renderizado em < 1s p95 |
| Cabe em monitor 1280px | sem scroll horizontal (canary monitora resolução baixa) |
| Cutover gradual | Blade legacy preservado como fallback se header NÃO traz `X-Inertia` |

## 1. Objetivo

Migrar a lista de contatos (clientes + fornecedores) e seu CRUD de Blade legacy AdminLTE roxo pra Inertia React Cockpit V2. **Pain-point #1** identificado na reunião com Martinho Caçambas LTDA: tempo perdido pra abrir cadastro / iniciar prospecção. Tela atual: 14 views Blade + DataTables + jQuery + Select2 — lenta, denso visualmente, sem busca instant. Estado-da-arte 2026 (Linear/Notion/Stripe): denso mas legível, atalhos teclado, busca instant, drawer detail sem reload.

Resolve:
- **Velocidade busca** (DataTables full-reload → fetch JSON 50 linhas em <300ms)
- **Cadastro relâmpago** (form atual = scroll vertical 4 telas; novo = 3 campos obrigatórios + restante colapsável)
- **Consistência visual** (acaba o "planeta visual diferente" toda vez que clicar; mesmo padrão Cockpit V2 que `/sells` e `/financeiro/boletos`)

## 2. Pré-condições

- [x] Permissão `customer.view`/`customer.view_own`/`supplier.view`/`supplier.view_own` (Spatie) — já implementadas no ContactController existente
- [x] Modelo `App\Contact` já tem `$guarded = ['id']` + multi-tenant via `where business_id` em todas queries via `ContactUtil` — PRESERVAR
- [x] Skill `multi-tenant-patterns` Tier A — `business_id` global scope obrigatório
- [x] Skill `mwart-quality` Tier A — 9 pré-flight checks
- [x] Skill `migracao-blade-react` Tier B — orquestra 6-step pipeline ([ADR 0141](../../decisions/0141-skill-migracao-blade-react.md))
- [ ] Pest baseline `tests/Feature/Crm/ContactsInertiaTest.php` com cross-tenant (biz=1 vs biz=99) — F2 BACKEND BASELINE obrigatório ANTES de mexer no `index()` controller
- [ ] Feature flag opt-in via dual-mode no controller: `Inertia::render` quando `X-Inertia` header, `view()` legacy caso contrário (cutover gradual sem big bang)

## 3. Passo-a-passo (F1→F5 ADR 0104)

### F1 — PLAN (este RUNBOOK) ✅

- [x] Snapshot paridade: lista, create, edit, show migram; import + ledger + map = NÃO no MVP (Blade preservado)
- [x] Charters por tela: `Index.charter.md` + `Create.charter.md`
- [x] SPEC append (se existir SPEC.md), ou epic US-CRM-CONT-* no MCP

### F2 — BACKEND BASELINE

```php
// app/Http/Controllers/ContactController.php
use Inertia\Inertia;

public function index()
{
    $business_id = request()->session()->get('user.business_id');
    $type = request()->get('type', 'customer'); // default customer (UI Cockpit)
    $types = ['supplier', 'customer'];

    if (! in_array($type, $types)) {
        $type = 'customer';
    }

    // AJAX legacy DataTables preservado para retrocompatibilidade
    if (request()->ajax()) {
        return $type === 'supplier' ? $this->indexSupplier() : $this->indexCustomer();
    }

    // Inertia novo path (Cockpit V2) — dual-mode com header X-Inertia
    if (request()->header('X-Inertia') || request()->wantsJson() === false) {
        // Carrega counts para KPIs (cheap query — apenas COUNT por status)
        $kpis = [
            'total'    => \App\Contact::where('business_id', $business_id)
                            ->whereIn('type', $type === 'supplier' ? ['supplier', 'both'] : ['customer', 'both'])
                            ->count(),
            'active'   => \App\Contact::where('business_id', $business_id)
                            ->whereIn('type', $type === 'supplier' ? ['supplier', 'both'] : ['customer', 'both'])
                            ->where('contact_status', 'active')->count(),
            'inactive' => \App\Contact::where('business_id', $business_id)
                            ->whereIn('type', $type === 'supplier' ? ['supplier', 'both'] : ['customer', 'both'])
                            ->where('contact_status', 'inactive')->count(),
        ];

        return Inertia::render('Crm/Contacts/Index', [
            'type'        => $type,
            'kpis'        => $kpis,
            'permissions' => [
                'create' => auth()->user()->can("{$type}.create"),
                'update' => auth()->user()->can("{$type}.update"),
                'delete' => auth()->user()->can("{$type}.delete"),
                'view'   => auth()->user()->can("{$type}.view") || auth()->user()->can("{$type}.view_own"),
            ],
        ]);
    }

    // Fallback Blade legacy (preservado — Blade legacy não é deletado neste PR)
    $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($type, ['customer'])) ? true : false;
    $users = User::forDropdown($business_id);
    $customer_groups = [];
    if ($type == 'customer') {
        $customer_groups = CustomerGroup::forDropdown($business_id);
    }

    return view('contact.index')
        ->with(compact('type', 'reward_enabled', 'customer_groups', 'users'));
}
```

Endpoints REST JSON para fetch via React:

```
GET  /contacts/list-json?type=customer&q=foo&status=active&page=1&per_page=25&sort=name&dir=asc
GET  /contacts/{id}/sheet-data    (drawer detail JSON: contact + last 10 transactions + financial status)
POST /contacts                    (existente — já retorna JSON success/msg)
PUT  /contacts/{id}               (existente — já retorna JSON)
DEL  /contacts/{id}               (existente — já retorna JSON)
```

> **NÃO criar migration** — coluna `business_id` + scope já existem. Apenas adicionar endpoint `list-json` se ainda não existir (reusa `ContactUtil::getContactQuery` que já scopa por `business_id`).

### F3 — FRONTEND INCREMENTAL

| US | Tela | LOC máx | Audit ≥ |
|----|------|---------|---------|
| US-CRM-CONT-001 | `Pages/Crm/Contacts/Index.tsx` skeleton (KPIs + busca + tabela) | 300 | 70 |
| US-CRM-CONT-002 | `Pages/Crm/Contacts/Create.tsx` form rápido (3 campos obrigatórios + colapsável) | 300 | 70 |
| US-CRM-CONT-003 | `Pages/Crm/Contacts/Edit.tsx` (reuso Create + initial values) | 200 | 70 |
| US-CRM-CONT-004 | `Pages/Crm/Contacts/Show.tsx` (header + tabs + últimas transactions) | 250 | 70 |

### F4 — QA HARDENING

- [ ] Audit cockpit-runbook modo B comprehensive ≥ 80 em cada PR (CRITICAL=0, WARN=0)
- [ ] Smoke biz=1 (Wagner WR2 SC) — NUNCA biz=164 cliente em Pest ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- [ ] Canary 7 dias só com Wagner usando dual-mode em biz=1
- [ ] Backup DB tabela `contacts` antes de habilitar pra Martinho biz=164

### F5 — CUTOVER + SUNSET

1. Aviso prévio Wagner → Filha Martinho + Dani (champions canary)
2. Ativa header `X-Inertia` default pra biz=164 via feature flag
3. Monitora 30 dias `storage/logs/laravel.log` por erros + feedback champions
4. Após 30 dias sem incidente → deletar Blade `contact/index.blade.php` + `contact/show.blade.php` + partials não-usadas + remover branch `view()` fallback no controller

## 4. Visual estado-da-arte 2026 (Cockpit V2)

| Bloco | Componente shared | Notas |
|-------|-------------------|-------|
| Header h1 | `<PageHeader>` ou inline canônico | h1 22-24px `font-semibold tracking-tight`, subtitle `text-sm text-muted-foreground` |
| KPIs cards | inline `<KpiCard>` (padrão Sells) | 3 cards: Total / Ativos / Inativos |
| Filter pills | inline `rounded-full` | 3 pills: Todos / Ativos / Inativos. Pattern Sells §pills canon |
| Busca | `<Input type=search>` debounce 300ms | placeholder "Buscar por nome, CPF/CNPJ, telefone…" |
| Tabela | `<table>` 6 cols | nome+contact_id, CPF/CNPJ, telefone+email, tipo, status, ações |
| Linha hover | `bg-muted/40` + cursor-pointer | abre drawer ao clicar |
| Drawer detail | `<Sheet>` shadcn lateral direito | header + tabs + transactions list |
| Botão "Novo" | `<Button>` Plus icon | anchor `<a href>` pra dual-mode (Inertia falha quando server retorna Blade) |
| Ações por linha | `<DropdownMenu>` MoreVertical | Ver/Editar/Status toggle/Excluir |

## 5. Routes

| Método | Rota | Ação Controller |
|--------|------|-----------------|
| GET | `/contacts?type=customer` | `index()` — Inertia v2 (X-Inertia) OU Blade legacy fallback |
| GET | `/contacts/create` | `create()` — Inertia v2 OU Blade legacy fallback |
| GET | `/contacts/{id}` | `show($id)` — Inertia v2 OU Blade legacy fallback |
| GET | `/contacts/{id}/edit` | `edit($id)` — Inertia v2 (não-ajax) OU Blade legacy (ajax) |
| POST | `/contacts` | `store()` — JSON (preservado) |
| PUT | `/contacts/{id}` | `update($id)` — JSON (preservado) |
| DELETE | `/contacts/{id}` | `destroy($id)` — JSON (preservado) |
| GET | `/contacts/list-json` | (NOVO) lista paginada para tabela Inertia |

## 6. Controller actions (delta mínimo)

- `index()` — adiciona branch Inertia ANTES de fallback Blade. Branch JSON (`request()->ajax()`) preservado.
- `create()` — adiciona branch Inertia. Preserva pre-fill `prefill_name` (PR #694).
- `edit($id)` — adiciona branch Inertia para não-ajax. Branch ajax JSON preservado (modal contact_quick_edit ainda usa).
- `show($id)` — adiciona branch Inertia. Preserva `view_type` (ledger/sales/purchase) como query param.
- `store/update/destroy` — INTOCADOS (já JSON-based, Inertia consome via fetch POST/PUT/DELETE).

## 7. Eloquent queries (multi-tenant Tier 0 IRREVOGÁVEL)

```php
// Sempre escopado por business_id — preservado do legacy
$query = $this->contactUtil->getContactQuery($business_id, $type);

// NUNCA fazer:
// $query = Contact::all();   ❌  (vaza cross-tenant)
// $query = DB::table('contacts')->...   ❌  (bypass scope)
```

Filtros para `list-json`:
- `q` → search nome+supplier_business_name+mobile+email+tax_number (LIKE %q%)
- `status` → contact_status active/inactive
- `type` → customer | supplier | both
- `sort` → name | mobile | tax_number | created_at
- `dir` → asc | desc
- `page` / `per_page` → paginação Laravel padrão

## 8. Pest cases (cross-tenant biz=1 vs biz=99 OBRIGATÓRIO)

`tests/Feature/Crm/ContactsInertiaTest.php`:

1. `index Inertia renderiza Crm/Contacts/Index component (X-Inertia header)`
2. `index Inertia bloqueado sem permissão customer.view` (403)
3. `index Inertia escopa por business_id (cross-tenant biz=1 vs biz=99)` — biz=99 NÃO vê contacts de biz=1
4. `create Inertia renderiza Crm/Contacts/Create component`
5. `store persiste com business_id correto vindo da session`
6. `update funciona via PUT JSON e mantém business_id`
7. `destroy soft delete (deleted_at preenche, deletedScope esconde)`
8. `list-json paginação responde estrutura correta`
9. `list-json busca por nome/cpf/telefone retorna match`
10. `Blade legacy fallback preservado quando SEM X-Inertia header`

PII redacted (`[REDACTED]`) — nada de CPF real em fixtures.

## 9. Charters

- [Crm/Contacts/Index.charter.md](../../../resources/js/Pages/Crm/Contacts/Index.charter.md) — Mission / Goals / Non-Goals / UX targets / Anti-hooks
- [Crm/Contacts/Create.charter.md](../../../resources/js/Pages/Crm/Contacts/Create.charter.md) — Mission / Goals / Non-Goals / UX targets / Anti-hooks

## 10. KPIs UX

- p95 first-paint < 1500ms (50 contacts + KPIs)
- p95 busca debounced < 500ms (resposta JSON pra digitar 3 chars)
- p95 click "Novo cliente" → Create rendered < 1000ms
- Drawer abre em < 300ms (sheet-data JSON)
- 0 erros JS console em smoke biz=1
- Cabe em monitor 1280px — sem scroll horizontal (canary verifica)
- CPF/CNPJ formatado automaticamente no display (123.456.789-01 / 12.345.678/0001-99)

## 11. Lições aprendidas (catalogadas para próxima migração CRM)

### Pegadinhas conhecidas

- **Contact tem campos `prefix/first_name/middle_name/last_name`** concatenados em `name` no `store/update`. Frontend novo simplifica: 1 campo `nome` (para customer pessoa física) ou `supplier_business_name` (jurídica/fornecedor). Backend continua aceitando legacy split para back-compat ajax.
- **`contact_type_radio`** (individual/business) NÃO confundir com `type` (customer/supplier/both). UI Cockpit V2 usa `contact_type` enum simplificado.
- **CPF/CNPJ no campo `tax_number`** — varchar nullable. PII LGPD: NUNCA em logs (skill `commit-discipline` Tier A + `PiiRedactor`).
- **`contacts.is_default=1`** existe — "Walk-In Customer" não-deletável. Frontend bloqueia botão delete.
- **`ContactUtil::getContactQuery`** já scopa por `business_id` + JOIN com transactions/sells aggregates (caro — usa só onde precisa, não no autocomplete).
- **Dual-mode controller** — Pattern Sells (US-SELL-008): branch `request()->header('X-Inertia')` PRIMEIRO, fallback view() Blade preservado para canary gradual. Cliente liga flag → migra. Bug? Desliga em 30s sem deploy.

### Anti-patterns evitar

- ❌ `format_date($created_at)` em "agora" (shift +3h ROTA LIVRE — [ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)). Para data agora local, use `now()->format()` direto.
- ❌ Modal/Dialog para detail (canon = Sheet lateral — Cockpit V2 ADR 0110)
- ❌ Tabs `border-b-2 border-primary` (canon = pills `rounded-full`)
- ❌ Cor crua `bg-red-500` (canon = `bg-rose-50 text-rose-700`)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.crm.contacts.*`)
- ❌ Edit/Write em `Pages/Crm/Contacts/*.tsx` SEM este RUNBOOK existir (hook `block-mwart-violation.ps1` bloqueia)

## Refs

- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0107 — Visual comparison gate F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0141 — Skill migracao-blade-react](../../decisions/0141-skill-migracao-blade-react.md)
- Padrão referência: [Pages/Sells/Index.tsx](../../../resources/js/Pages/Sells/Index.tsx) + [Index.charter.md](../../../resources/js/Pages/Sells/Index.charter.md)
- Padrão referência: [Pages/Financeiro/Boletos/Index.tsx](../../../resources/js/Pages/Financeiro/Boletos/Index.tsx) (PR #845 Cockpit V2)
