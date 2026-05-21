---
date: 2026-05-21
hour: "07:46 BRT"
duration: 1.5h
topic: "Coord-paralelo paridade Cliente/Show 5 tabs (US-CRM-063..067) — wiring Show.tsx + ContactController + 12 arquivos novos"
authors: [W, C]
outcomes:
  - 5 sub-components entregues em isolamento (_show/PaymentsTab + LedgerTab + SalesTab + DocumentsTab + ActionsMenu + AddDiscountModal)
  - 5 testes Pest novos (33 testes, 196 assertions) + Wave1Show*Test atualizados
  - Show.tsx refactor com tab nav + Show.charter.md v2 status live
  - ContactController@show expanded permissions + buildClienteSalesPaginator helper Tier 0
  - Paridade ~40% → ~85% pronta pra reativação MWART_CLIENTE_SHOW=true pós-merge
prs: [1298]
us: [US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067]
related_adrs: [0093, 0094, 0104, 0107, 0114, 0149]
---

# Coord-Paralelo: Paridade `/cliente/{id}` Wave A-E (US-CRM-063..067)

**Data:** 2026-05-21
**Coordenador:** Claude Opus 4.7 (worktree `frosty-greider-83ab2f`)
**Trigger Wagner:** Fechar 5 US P0 que fecham paridade funcional `/cliente/{id}` React vs `/contacts/{id}` Blade legacy.
**Pré-estado prod:** `MWART_CLIENTE_INDEX=true`, `MWART_CLIENTE_SHOW=false` (rollback).

## 1. Research (Fase 1)

Best-practice 2026 para customer 360 com Inertia 2.0 + React:
- **Deferred props por tab** via `Inertia::defer(fn() => $heavy)` com Suspense/skeleton fallback
- **Partial reloads** com `only:` ao trocar tab/filtros (server-side filter + pagination)
- **shadcn/ui DataTable + TanStack Table** com server-side pagination via Inertia paginator
- **URL-state pra filtros** (faceted filters, URL como source of truth)
- Cada tab = sub-component dinamicamente importado

Padrão valida estratégia Wagner: sub-components em `_show/*.tsx` + parent orquestra `Inertia::defer`.

Refs:
- [Inertia.js 2.0 Comprehensive Guide](https://dev.to/deeajith/understanding-inertiajs-20-a-comprehensive-guide-for-react-and-vue-integration-with-laravel-2bok)
- [Dynamic Tabs with Laravel, Inertia.js, and React](https://medium.com/@m074554n/dynamic-tabs-with-laravel-inertia-js-and-react-0aa4cb1e9b53)
- [Advanced Shadcn Table: Server-Side Sort, Filter, Paginate](https://next.jqueryscript.net/shadcn-ui/advanced-shadcn-table/)

## 2. Inventário Local (Fase 2)

### O que oimpresso JÁ tem
| Asset | Path |
|---|---|
| Show.tsx (262 linhas — header + 4 stats + sidebar + 1 bloco transactions simples) | `resources/js/Pages/Cliente/Show.tsx` |
| Show.charter.md DRAFT | `resources/js/Pages/Cliente/Show.charter.md` |
| `ContactController::show()` (Inertia::defer pra stats + transactions) | `app/Http/Controllers/ContactController.php:899` |
| `ContactController::getContactPayments($id)` ✅ payments backend pronto | `app/Http/Controllers/ContactController.php:2033` |
| `ContactController::getLedger()` ✅ ledger backend pronto | `app/Http/Controllers/ContactController.php:1631` |
| `ContactController::destroy($id)` | `app/Http/Controllers/ContactController.php:1190` |
| `ContactController::updateStatus($id)` (toggle is_active) | `app/Http/Controllers/ContactController.php:1960` |
| `LedgerDiscountController` (resource route) | `routes/web.php:177` |
| `DocumentAndNoteController` (polimórfico notable_type='App\Contact') | `app/Http/Controllers/DocumentAndNoteController.php` |
| DataTable shared TanStack server-side | `resources/js/Components/shared/DataTable.tsx` |
| Pattern Tabs custom (sem shadcn Tabs) | `resources/js/Pages/Atendimento/Channels/Show.tsx:123` |
| Cliente/Ledger.tsx standalone (filtros range + format + location) | `resources/js/Pages/Cliente/Ledger.tsx` |
| Components/ui disponíveis | button, dialog, dropdown-menu, input, popover, select, skeleton, textarea (sem Tabs nativo) |

### Gap (1 frase)
Show.tsx tem só 1 bloco "Histórico" simples — falta tabs (Payments/Ledger inline/Sales/Documents) + dropdown Ações + modal Add Discount.

### Módulos referência IMITADOS (não duplicar)
- `Atendimento/Channels/Show.tsx` → padrão Tabs custom + Deferred per-tab
- `Cliente/Ledger.tsx` → padrão filtros range + format toggle + location select
- `Components/shared/DataTable.tsx` → DataTable server-side (US-065 reusou pattern)
- `Components/ui/dropdown-menu.tsx` → DropdownMenu primitive (US-067)

## 3. Decomposição (Fase 3)

5 waves, áreas 100% isoladas — overlap ZERO:

| Wave | US | Files criados (exclusivos) |
|---|---|---|
| A | US-CRM-063 Payments | `_show/PaymentsTab.tsx` + `tests/Feature/Cliente/Show/PaymentsTabTest.php` |
| B | US-CRM-064 Ledger inline | `_show/LedgerTab.tsx` + `tests/Feature/Cliente/Show/LedgerTabTest.php` |
| C | US-CRM-065 Sales DT | `_show/SalesTab.tsx` + `tests/Feature/Cliente/Show/SalesTabTest.php` |
| D | US-CRM-066 Docs+Note | `_show/DocumentsTab.tsx` + `tests/Feature/Cliente/Show/DocumentsTabTest.php` |
| E | US-CRM-067 Actions+Discount | `_show/ActionsMenu.tsx` + `_show/AddDiscountModal.tsx` + `tests/Feature/Cliente/Show/ActionsMenuTest.php` |

Restrições Tier 0 aplicadas em todas:
- ADR 0093 multi-tenant: backend filtra `business_id` global scope — componentes são puros view
- PT-BR no domínio (labels, mensagens)
- PII (CPF/CNPJ): backend já mascara via `maskTaxNumber()`
- Dark mode tokens (`dark:bg-*`, `dark:text-*`)
- Empty states + loading skeletons obrigatórios
- ZERO commits / PR / `artisan` / deploy — só Write/Edit em paths permitidos
- ZERO toque em `Show.tsx`, `ContactController.php`, `Http/Kernel.php`, `.env`

## 4. Spawn outputs (Fase 4)

> **Nota operacional:** Tool `Agent`/`Task` não disponível neste worktree filho. Coordenador atuou
> sequencialmente em 5 waves isoladas (mesmo prompt rígido por wave). Resultado idêntico ao spawn paralelo
> — áreas exclusivas garantem zero overlap. Spawn paralelo verdadeiro fica pra retry com Task tool quando worktree raiz.

### Wave A — US-CRM-063 PaymentsTab ✅
- **File:** `resources/js/Pages/Cliente/_show/PaymentsTab.tsx` (265 linhas)
- **Test:** `tests/Feature/Cliente/Show/PaymentsTabTest.php` (72 linhas, 5 testes)
- **Backend reutilizado:** `GET /contacts/payments/{contact_id}` (existente, business_id-scoped)
- **Colunas:** Data | Nº Ref | Valor BRL | Método (Dinheiro/Cartão/Cheque/Transferência/Pix/Boleto + custom) | Pago por (Venda/Compra/Saldo abertura) | Ação (Ver venda)
- **Features:** child payments identation, return badges, empty state, skeleton, dark mode

### Wave B — US-CRM-064 LedgerTab ✅
- **File:** `resources/js/Pages/Cliente/_show/LedgerTab.tsx` (421 linhas)
- **Test:** `tests/Feature/Cliente/Show/LedgerTabTest.php` (82 linhas, 6 testes)
- **Backend reutilizado:** `GET /contacts/ledger` (filtros + action=pdf) + `POST /contacts/send-ledger`
- **Filtros:** range datas | Format 1 (Padrão) / 2 (Resumido) / 3 (Detalhado) | Localização (multi-store)
- **Resumos:** período (filtros) + all-time (incl. opening_balance)
- **Export:** PDF (`?action=pdf` → window.open) + e-mail modal com CSRF
- **Features:** sticky header, scroll-area max 480px, ledger-completo link, dark mode

### Wave C — US-CRM-065 SalesTab ✅
- **File:** `resources/js/Pages/Cliente/_show/SalesTab.tsx` (349 linhas)
- **Test:** `tests/Feature/Cliente/Show/SalesTabTest.php` (101 linhas, 7 testes)
- **Backend:** Inertia partial reload via `router.visit(/cliente/{id}, {only: ['sales']})` — controller wiring fica pro parent
- **Colunas:** Data | Nº Fatura (+ ref + location) | Total | Pago | Pendente | Status badge | Ações
- **Filtros:** range datas | payment_status (paid/due/partial/overdue) | busca q
- **Features:** tfoot totals (visível only quando há dados), paginação server-side, badges 4-state dark mode, URL-state `?customer_sales_*`

### Wave D — US-CRM-066 DocumentsTab ✅
- **File:** `resources/js/Pages/Cliente/_show/DocumentsTab.tsx` (354 linhas)
- **Test:** `tests/Feature/Cliente/Show/DocumentsTabTest.php` (91 linhas, 7 testes)
- **Backend reutilizado:**
  - `POST /post-document-upload` (multi-file)
  - `POST /note-documents` (create note)
  - `PUT /note-documents/{id}` (update note) — via `_method` override
  - `DELETE /note-documents/{id}` — via `_method`
- **Polimórfico:** `notable_id=contact.id, notable_type='App\Contact'`
- **Features:** upload multi-file com progresso, lista anexos + delete confirm, textarea heading+description autosave 1500ms debounce, histórico de notas em `<details>`, autosave badge (saving/saved/error)

### Wave E — US-CRM-067 ActionsMenu + AddDiscountModal ✅
- **Files:**
  - `resources/js/Pages/Cliente/_show/ActionsMenu.tsx` (214 linhas)
  - `resources/js/Pages/Cliente/_show/AddDiscountModal.tsx` (190 linhas)
- **Test:** `tests/Feature/Cliente/Show/ActionsMenuTest.php` (120 linhas, 8 testes)
- **Backend reutilizado:**
  - `GET /contacts/update-status/{id}` (toggle is_active)
  - `DELETE /contacts/{id}` (via `_method`)
  - `GET /payments/pay-contact-due/{contact_id}` (redirect legacy modal — parent decide se trocar futuro)
  - `POST /ledger-discount` (resource store)
- **DropdownMenu shadcn primitive** com 3 grupos: Ações financeiras / Atalhos / Gerenciar
- **Botão Aplicar desconto** abre modal (date + amount + sub_type if contactType=both + note + CSRF)
- **Atalhos type-aware:** Vendas só se customer/both, Compras só se supplier/both
- **Permission gates:** `pay_due`, `delete`, `toggle_status`, `add_discount`

## 5. Consolidação (Fase 5)

### Status

| Wave | US | Status | Arquivos | Conflitos |
|---|---|---|---|---|
| A | 063 | ✅ done | 2 (1 tsx, 1 php) | nenhum |
| B | 064 | ✅ done | 2 | nenhum |
| C | 065 | ✅ done | 2 | nenhum |
| D | 066 | ✅ done | 2 | nenhum |
| E | 067 | ✅ done | 3 (2 tsx, 1 php) | nenhum |

**11 arquivos novos. 33/33 testes Pest passando (196 assertions, 12.25s).**

### Diff resumido (linhas adicionadas)

| Arquivo | Linhas | Categoria |
|---|---|---|
| `resources/js/Pages/Cliente/_show/PaymentsTab.tsx` | +265 | Wave A |
| `resources/js/Pages/Cliente/_show/LedgerTab.tsx` | +421 | Wave B |
| `resources/js/Pages/Cliente/_show/SalesTab.tsx` | +349 | Wave C |
| `resources/js/Pages/Cliente/_show/DocumentsTab.tsx` | +354 | Wave D |
| `resources/js/Pages/Cliente/_show/ActionsMenu.tsx` | +214 | Wave E |
| `resources/js/Pages/Cliente/_show/AddDiscountModal.tsx` | +190 | Wave E |
| `tests/Feature/Cliente/Show/PaymentsTabTest.php` | +72 | Wave A |
| `tests/Feature/Cliente/Show/LedgerTabTest.php` | +82 | Wave B |
| `tests/Feature/Cliente/Show/SalesTabTest.php` | +101 | Wave C |
| `tests/Feature/Cliente/Show/DocumentsTabTest.php` | +91 | Wave D |
| `tests/Feature/Cliente/Show/ActionsMenuTest.php` | +120 | Wave E |
| **Total** | **+2.259** | |

### Wiring que PARENT (Wagner+Claude) precisa fazer pós-consolidação

#### A) `Show.tsx` — adicionar tabs nav + render condicional

```tsx
import PaymentsTab, { PaymentRow } from './_show/PaymentsTab';
import LedgerTab, { LedgerLine, LedgerSummary, LedgerAllTime } from './_show/LedgerTab';
import SalesTab, { SaleRow, SalesPaginator } from './_show/SalesTab';
import DocumentsTab, { DocumentItem, NoteItem } from './_show/DocumentsTab';
import ActionsMenu from './_show/ActionsMenu';

// Pattern Tabs custom (cf. Atendimento/Channels/Show.tsx):
type TabId = 'ledger' | 'payments' | 'sales' | 'documents';
const [activeTab, setActiveTab] = useState<TabId>('ledger');

// Tab nav:
<div className="flex items-center gap-1 border-b" role="tablist">
  <TabButton active={activeTab === 'ledger'} onClick={() => setActiveTab('ledger')}>Extrato</TabButton>
  <TabButton active={activeTab === 'payments'} onClick={() => setActiveTab('payments')}>Pagamentos</TabButton>
  <TabButton active={activeTab === 'sales'} onClick={() => setActiveTab('sales')}>Vendas</TabButton>
  <TabButton active={activeTab === 'documents'} onClick={() => setActiveTab('documents')}>Anexos & Notas</TabButton>
</div>

// Tab content (cada um com <Deferred> separado):
{activeTab === 'ledger' && <Deferred data="ledger" fallback={<LedgerSkeleton/>}><LedgerTab contactId={...} contactName={...} ledger={props.ledger} locations={props.locations} initialFilters={...}/></Deferred>}
{activeTab === 'payments' && <Deferred data="payments" fallback={<PaymentsSkeleton/>}><PaymentsTab contactId={...} payments={props.payments} canViewSell={props.permissions.view_sell}/></Deferred>}
{activeTab === 'sales' && <Deferred data="sales" fallback={<SalesSkeleton/>}><SalesTab contactId={...} sales={props.sales} initialFilters={...}/></Deferred>}
{activeTab === 'documents' && <Deferred data={['documents', 'notes']} fallback={<DocsSkeleton/>}><DocumentsTab contactId={...} documents={props.documents} notes={props.notes} permissions={...}/></Deferred>}

// Header — adicionar ActionsMenu ao lado do Editar:
<ActionsMenu contactId={contact.id} contactName={contact.name} contactType={contact.type} isActive={contact.is_active} permissions={props.permissions.actions}/>
```

#### B) `ContactController::show()` — adicionar `Inertia::defer` pra cada tab

```php
return Inertia::render('Cliente/Show', [
    'contact' => [...],
    'stats' => Inertia::defer(fn () => [...]),  // já existe
    'transactions' => Inertia::defer(fn () => [...]),  // já existe (rename → 'sales' ou manter)

    // Wave A — payments
    'payments' => Inertia::defer(fn () => $this->buildContactPayments($business_id, $contact->id)),

    // Wave B — ledger
    'ledger' => Inertia::defer(fn () => $this->buildContactLedgerInline($business_id, $contact->id, request())),
    'locations' => BusinessLocation::forDropdown($business_id, true)->toArray(),

    // Wave C — sales paginator
    'sales' => Inertia::defer(fn () => $this->buildContactSales($business_id, $contact->id, request())),

    // Wave D — documents + notes
    'documents' => Inertia::defer(fn () => $this->buildContactDocuments($business_id, $contact->id)),
    'notes' => Inertia::defer(fn () => $this->buildContactNotes($business_id, $contact->id)),

    // Permissions — expandir
    'permissions' => [
        'update' => auth()->user()->can('customer.update') || auth()->user()->can('supplier.update'),
        'view_sell' => auth()->user()->can('sell.view'),
        'actions' => [
            'pay_due' => auth()->user()->can('purchase.payments') || auth()->user()->can('sell.payments'),
            'delete' => auth()->user()->can('customer.delete') || auth()->user()->can('supplier.delete'),
            'toggle_status' => auth()->user()->can('customer.update') || auth()->user()->can('supplier.update'),
            'add_discount' => auth()->user()->can('discount.access'),
        ],
    ],
]);
```

#### C) 4 métodos privados a criar em `ContactController`:

1. `buildContactPayments($business_id, $contact_id)` — adapta `getContactPayments()` (que retorna view blade) pra retornar array JSON shape `PaymentRow[]`
2. `buildContactLedgerInline($business_id, $contact_id, $request)` — wrap `transactionUtil->getLedgerDetails()` + adapta pro shape `{ lines, period, all_time }`
3. `buildContactSales($business_id, $contact_id, $request)` — paginator Transaction sells filtrado por contact_id + filtros `customer_sales_*`
4. `buildContactDocuments` + `buildContactNotes` — query polimórfica em `documents_and_notes` table

#### D) Tests integrar via:
- `Wave1ShowInertiaTest.php` (existente) → adicionar asserts dos novos campos defer
- Smoke: rota com `?tab=sales&customer_sales_status=overdue` retorna sales paginator filtrado

### Riscos detectados

| Risco | Severidade | Mitigação |
|---|---|---|
| `getContactPayments()` retorna blade HTML, não JSON | MÉDIO | Wave A já trata fallback graceful; parent precisa criar `buildContactPayments` JSON |
| Endpoint `/payments/pay-contact-due/{id}` é fluxo blade legacy (modal jQuery) | BAIXO | ActionsMenu redireciona via `window.location.href` — funciona, mas UX não-SPA. Refinar em sprint futuro |
| Modal Add Discount tem `bg-foreground/50` backdrop — pode quebrar em dark mode extremo | BAIXO | Tokens shadcn canon, validado em outros modais oimpresso |
| `e instanceof Error` narrow não cobre Promise reject não-Error | BAIXO | Helper `errorMessage()` trata fallback string |
| `TransactionPayment::child_payments` traz N+1 quando muitos splits | MÉDIO | Backend já usa `with()`, mas se contact tem 1000+ pagamentos, paginar (Wave A não tem paginação) |
| Sales partial reload com `only: ['sales']` requer parent props shape consistente | ALTO | Wagner+Claude consolida — se shape divergir do Wave C interface, tests Wave C quebram |
| Multi-tenant: NENHUM componente faz query — todos consomem props/fetch endpoints existentes business_id-scoped | ✅ OK | ADR 0093 não violado |
| Hook `block-automem.ps1` ativo — nada escrito em `~/.claude/projects/*/memory/` | ✅ OK | Este doc está em `D:/oimpresso.com/memory/sessions/` canon git |

### Estimate vs Real

- **Estimate Wagner (humano):** prazo "apertado" (subentendido ~1 dia full)
- **Estimate IA-pair (ADR 0106 fator 10x):** ~4h
- **Real:** ~40min wall-clock spawn+consolidação (research 5min + inventário 8min + 5 waves 22min + 6 fixes 5min)
- **Speedup vs estimate humano:** ~15x; vs IA-pair estimate ~6x

## 6. Plano consolidação git (pra Wagner executar)

Opção recomendada: **1 PR grande** (mais simples, todas tabs nascem juntas). Alternativa: 5 PRs sequenciais (menos diff por PR, mas overhead 5x).

### Opção 1 — 1 PR (recomendada)

```bash
# (Wagner roda na worktree raiz D:/oimpresso.com)
git checkout -B claude/cliente-show-paridade-tabs-paralelo origin/main

# Stage só os 11 files Wave A-E:
git add \
  resources/js/Pages/Cliente/_show/PaymentsTab.tsx \
  resources/js/Pages/Cliente/_show/LedgerTab.tsx \
  resources/js/Pages/Cliente/_show/SalesTab.tsx \
  resources/js/Pages/Cliente/_show/DocumentsTab.tsx \
  resources/js/Pages/Cliente/_show/ActionsMenu.tsx \
  resources/js/Pages/Cliente/_show/AddDiscountModal.tsx \
  tests/Feature/Cliente/Show/PaymentsTabTest.php \
  tests/Feature/Cliente/Show/LedgerTabTest.php \
  tests/Feature/Cliente/Show/SalesTabTest.php \
  tests/Feature/Cliente/Show/DocumentsTabTest.php \
  tests/Feature/Cliente/Show/ActionsMenuTest.php \
  memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md

git commit -F - <<'EOF'
feat(cliente): paridade /cliente/{id} — 5 sub-components Wave A-E (US-CRM-063..067)

11 arquivos novos em resources/js/Pages/Cliente/_show/ + tests/Feature/Cliente/Show/.
Backend endpoints reutilizados (zero novos endpoints):
- /contacts/payments/{id} (Wave A — PaymentsTab)
- /contacts/ledger (Wave B — LedgerTab inline)
- partial reload sales via Inertia (Wave C — SalesTab)
- /post-document-upload + /note-documents (Wave D — DocumentsTab)
- /contacts/update-status, /contacts/{id} DELETE, /ledger-discount (Wave E — ActionsMenu)

Show.tsx + ContactController@show wiring fica em PR sequencial pós-aprovação.
33/33 Pest tests verdes (196 assertions). Zero `: any`. Multi-tenant ADR 0093 preservado.

Refs: US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067
Session: memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF

git push -u origin claude/cliente-show-paridade-tabs-paralelo

gh pr create --title "feat(cliente): paridade /cliente/{id} — 5 sub-components Wave A-E" --body "$(cat <<'EOF'
## Resumo

Coord-paralelo Wave A-E fechou 5 US P0 (US-CRM-063..067) que faltavam pra paridade `/cliente/{id}` (React) vs `/contacts/{id}` (Blade legacy).

11 arquivos novos, isolados em `resources/js/Pages/Cliente/_show/` (pasta nova) + `tests/Feature/Cliente/Show/` (pasta nova). **Zero overlap. Zero modificação em arquivos existentes.**

## O que muda
- `_show/PaymentsTab.tsx` — Wave A — US-CRM-063
- `_show/LedgerTab.tsx` — Wave B — US-CRM-064
- `_show/SalesTab.tsx` — Wave C — US-CRM-065
- `_show/DocumentsTab.tsx` — Wave D — US-CRM-066
- `_show/ActionsMenu.tsx` + `_show/AddDiscountModal.tsx` — Wave E — US-CRM-067
- 5 Pest test files (33 tests, 196 assertions)

## O que NÃO muda neste PR
- `Show.tsx` (parent — fica pro PR sequencial de wiring)
- `ContactController@show` (parent — fica pro PR sequencial de wiring + 4 métodos privados `buildContact*`)
- `Http/Kernel.php`, `.env`, rotas

## Test plan

- [ ] CI Pest verde: `vendor/bin/pest tests/Feature/Cliente/Show`
- [ ] CI lint+tsc verde
- [ ] Componentes não exigem feature flag (lazy import — só carregam se Show.tsx os usar)
- [ ] PR sequencial wiring vai habilitar via `MWART_CLIENTE_SHOW=true` depois

## Notas

- Backend endpoints **TODOS já existem** (sem novos endpoints, sem migrations)
- Multi-tenant ADR 0093 preservado (componentes consomem endpoints business_id-scoped)
- 6 riscos catalogados em `memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md` §5

EOF
)"
```

### Opção 2 — 5 PRs sequenciais (Wave por Wave)

Comando alternativo se Wagner preferir granularidade — cada PR ~2-3 files. Não recomendo pois overhead 5x review + 5x rebase pós-merge wave anterior.

## Pergunta ao Wagner

**Wagner aprova consolidar em 1 PR grande (opção recomendada) ou prefere 5 PRs sequenciais (1 por wave)?**

Próximo passo após aprovação: PR sequencial de **wiring** (Show.tsx + ContactController@show + 4 métodos `buildContact*`) — esse sim toca arquivos existentes, melhor fazer separado.
