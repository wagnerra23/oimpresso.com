---
slug: sells-runbook-edit
title: "Sells — Runbook da tela Editar venda (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-14
---

# RUNBOOK — Editar venda (`/sells/{id}/edit`)

> **Tipo:** runbook de migração Blade → Inertia/React (MWART) — irmão do [RUNBOOK-create.md](RUNBOOK-create.md)
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md), [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md), [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
> **Estado origem:** Blade legacy renderiza `view('sell.edit')` via [SellController@edit](../../../app/Http/Controllers/SellController.php#L1698) — 898 LOC Blade + reusa `public/js/pos.js` (3.178 LOC jQuery) — formulário PUT para SellPosController@update
> **Estado alvo:** [`Pages/Sells/Edit.tsx`](../../../resources/js/Pages/Sells/Edit.tsx) (Inertia v3 + React 19 + shadcn-style Cockpit V2) — criado nesta migração
> **Persona alvo:** **Lara** (filha do Martinho, estoque) + **Dani** (financeiro · DANIELLI id=297) no canary Martinho Caçambas LTDA biz=164. Não-técnicas, monitor 1280px, vêm de mundo Word/Excel/WhatsApp. Pain #1 reunião 14/maio: "edita venda em 1 clique e fecha rápido".

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/sells/{id}/edit` renderiza Page React quando biz canary | `curl -s -H "X-Inertia: true" /sells/<id>/edit` mostra `"component":"Sells/Edit"` |
| Rota mantém Blade pra biz fora canary (DUAL-MODE) | curl idem com biz=1 sem flag → HTML legacy `sell.edit` |
| Rota bloqueia biz=4 ROTA LIVRE em Inertia (guard `$business_id !== 4`) | Wagner reportou bugs hotfix 2026-05-13 — preservar Blade |
| Bundle Inertia builda | `npm run build:inertia` + entrada `Pages/Sells/Edit` em `public/build-inertia/manifest.json` |
| AppShellV2 envolvendo | Inspetor: `<div class="app-shell-v2">` ao redor da Page |
| Permissão `direct_sell.update` respeitada | Login sem perm → 403 (preserva guard linha 1700 SellController) |
| Multi-tenant `business_id` Tier 0 IRREVOGÁVEL | `Transaction::where('business_id', $business_id)->findorfail($id)` (linha 1723) — Tier 0 (ADR 0093) |
| Pre-fill exato dos campos da transação | Cliente, data, status, invoice_no, items[], payments[], shipping populados |
| Cancelar venda funciona (canon FSM `cancelar_venda`) | Botão rose com confirm modal → PUT `/pos/{id}` com `status: 'cancelled'` |
| Atalhos `Ctrl/Cmd+S` e `Cmd+Enter` salvam | Lara/Dani vêm de Word/Excel — pattern aprendido |
| Read-only mode em vendas canceladas | `transaction.status === 'cancelled'` → inputs disabled + badge rose |
| Auditoria preservada via SellPosController@update existente | Campo `updated_at`/`updated_by` em `transactions` table |

## 1. Objetivo

Migrar a tela "Editar venda" de Blade legacy (`sell.edit` + 898 LOC + jQuery) pra Inertia/React seguindo padrão MWART. Cliente piloto: **Martinho Caçambas LTDA biz=164** (canary semana 19/maio). Personas: Lara (estoque) edita venda quando produto trocou/quantidade ajustou, e Dani (financeiro · DANIELLI id=297) edita venda quando parcela de pagamento muda. Persona não-técnica · monitor 1280px · pain #1: "edita venda em ≤10s do clique edit na lista".

Resolve fricção do legacy: scroll 3 telas, dois forms separados (UltimatePOS dual `sell.edit` vs `sale_pos.edit`), atualizar item exige 4 cliques + reload dropdown jQuery.

## 2. Pré-condições

- [x] Charter `Sells/Edit.charter.md` criado com status `proposed` (Wagner reviewa Non-Goals + Anti-patterns)
- [x] Page Inertia `Pages/Sells/Edit.tsx` criada usando pattern Create.tsx canônico
- [x] Branch Inertia no `SellController@edit()` (ANTES do `return view('sell.edit')` legacy)
- [x] Whitelist canary biz=164 hardcoded (espelha hotfix create)
- [x] Guard biz=4 ROTA LIVRE preservado (hotfix create 2026-05-13)
- [x] Feature flag `useV2SellsEdit` reconhecida pelo FeatureFlagService
- [x] Pest test 25+ casos cobrindo dual response + pre-fill + Tier 0 cross-tenant + cancelar venda
- [ ] Permissão `direct_sell.update` atribuída aos usuários Lara e Dani na biz=164 (W aprova manual antes canary)
- [ ] Skill irmã carregada: [`multi-tenant-patterns`](../../../.claude/skills/multi-tenant-patterns/SKILL.md)
- [ ] Skill irmã carregada: [`mwart-quality`](../../../.claude/skills/mwart-quality/SKILL.md)

## 3. Passo-a-passo

### 1. Adicionar branch Inertia `Sells/Edit` no controller

```php
// app/Http/Controllers/SellController.php @ edit() linha 1996 (ANTES do return view('sell.edit'))

$ffs = app(\App\Services\FeatureFlagService::class);
$canaryBusinesses = [164]; // Martinho Caçambas LTDA
$useV2 = $business_id !== 4
    && (
        in_array($business_id, $canaryBusinesses, true)
        || $ffs->isOn('useV2SellsEdit', ['business_id' => $business_id])
    );

if ($useV2) {
    return Inertia::render('Sells/Edit', [
        'transaction'    => [...],  // ID, business_id, location_id, contact, status, etc
        'sellDetails'    => $sell_details->map(...)->values()->all(),
        'paymentLines'   => collect($payment_lines)->map(...)->values()->all(),
        // Dropdowns: paymentTypes, invoiceSchemes, taxes, priceGroups,
        //            shippingStatuses, commissionAgents, customerGroups, accounts, statuses, users
        'walkInCustomer' => [...],
        'permissions'    => [
            'editDiscount' => $edit_discount,
            'editPrice'    => $edit_price,
            'canCancel'    => $canCancel,
            'maxDiscount'  => auth()->user()->max_sales_discount_percent,
        ],
        'posSettings'    => $pos_settings,
        'customerDue'    => $customer_due,
    ]);
}

return view('sell.edit')->with(compact(/* legacy props PRESERVADAS */));
```

**Validação:** `curl -s -H "X-Inertia: true" -H "X-Inertia-Version: $(grep version manifest)" /sells/<id>/edit` retorna JSON `{"component":"Sells/Edit",...}` se biz canary.

### 2. Criar a Page Inertia espelhando Create.tsx

Reusa toda a estrutura canônica:
- Header sticky com filter pills (scroll-spy IntersectionObserver)
- 4 KPI cards grandes (Itens / Total / Pago / Status pgto)
- 5 seções: Dados / Produtos / Pagamento / Resumo / Mais opções (`<details>`)
- Footer sticky com Voltar / Cancelar venda / Salvar e imprimir / Salvar alterações
- Atalhos `Ctrl/Cmd+S` + `Cmd+Enter` + `Esc`
- ProductSearchAutocomplete + PaymentRow + dropdownEntries reusados

**Diferenças vs Create.tsx:**
- `useForm.put(`/pos/${id}`)` em vez de `post('/pos')` (mesmo controller `SellPosController@update`)
- Pre-fill `transaction.*` em todos os campos
- Items reusam `transaction_sell_lines_id` original (UPDATE vs INSERT)
- Sem auto-save draft localStorage (DB é source of truth em UPDATE)
- Botão "Cancelar venda" destacado rose (canon FSM `cancelar_venda` ADR 0143)
- Read-only mode quando `status === 'cancelled'` (inputs disabled + badge)

### 3. Mapear campos legacy `sell.edit.blade.php` → alvo

| Campo legacy Blade | Alvo React (state) | Visível por padrão? | Notas |
|---|---|---|---|
| `location_id` | `data.location_id` | Hidden | Não editável Edit (legacy também não permite) |
| `customer_id` / `contact_id` | `data.contact_id` | ✅ | CustomerSearchAutocomplete |
| `transaction_date` | `data.transaction_date` | ✅ | datetime-local input |
| `status` (final/draft/quotation/proforma) | `data.status` | ✅ | Select shadcn-style |
| `invoice_no` | `data.invoice_no` | ✅ | Required por `@can('edit_invoice_number')` |
| `invoice_scheme_id` | `data.invoice_scheme_id` | 🟡 `<details>` | Só draft |
| `pay_term_number + pay_term_type` | `data.pay_term_number` + `data.pay_term_type` | 🟡 `<details>` | |
| `price_group` | `data.price_group_id` | 🟡 `<details>` | Só se `priceGroups.length > 1` |
| `commission_agent` | `data.commission_agent_id` | 🟡 `<details>` | Só se `commissionAgents` não vazio |
| `tax_rate_id` | `data.tax_rate_id` | 🟡 `<details>` | Imposto do pedido |
| Tabela `sell_details` | `data.products[]` | ✅ | Editável (qty, unit_price, discount) |
| `discount_type + discount_amount` | `data.discount_*` | ✅ | Validação max_sales_discount_percent |
| `sale_note` (additional_notes) | `data.notes` | ✅ | textarea 3 rows |
| Bloco `shipping_*` | `data.shipping.*` | 🟡 `<details>` | 5 campos |
| `is_export + export_custom_field_*` | — | ❌ | Não migrado nesta US (legado raro) |
| `prefer_payment_method/account` | — | ❌ | Não migrado (PDF only) |
| `payment[$idx]` lines | `data.payments[]` | ✅ | PaymentRow split |
| `change_return` | — | ❌ | Calculado client-side (saldo) |
| `rp_redeemed*` | — | ❌ | biz=164 não usa Reward Points |
| `custom_field_1..4` | — | ❌ | Não migrado nesta US (sem user request) |
| `sales_order_ids` | — | ❌ | biz=164 não usa Sales Order |
| `delivery_person` | — | 🟡 `<details>` | Pode adicionar quando pedido |
| Hidden `is_direct_sale` | `transform` | — | Sempre 1 (preserva legacy) |

> **Ganho concreto:** ~25 campos sempre visíveis no legacy → 8 sempre visíveis + 10 colapsáveis. Edição em ≤10s do clique edit (pain #1 Lara/Dani).

### 4. Reusar componentes shared + _components Sells/

Os componentes do diretório `Pages/Sells/_components/` (criados na migração Create) são reusados 1:1:

```tsx
import ProductSearchAutocomplete from './_components/ProductSearchAutocomplete';
import CustomerSearchAutocomplete from './_components/CustomerSearchAutocomplete';
import PaymentRow, { type Payment } from './_components/PaymentRow';
import { dropdownEntries } from './_components/dropdownEntries';
import EmptyState from '@/Components/shared/EmptyState';
```

Sem necessidade de novos `_components` nesta US — pattern Edit reusa Create. Promoção a `Components/shared/` só quando 3ª tela usar (R-DS-001).

### 5. Atalhos teclado

```tsx
useEffect(() => {
  const onKey = (e: KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) {
      e.preventDefault();
      if (canSubmit) handleSubmit();
    }
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && canSubmit) {
      e.preventDefault();
      handleSubmit();
    }
  };
  window.addEventListener('keydown', onKey);
  return () => window.removeEventListener('keydown', onKey);
}, [canSubmit]);
```

**Diferença vs Create:** `Cmd/Ctrl+S` adicionado pra atender Lara/Dani (vêm de Word/Excel). `Cmd+Enter` preservado pra consistência canônica.

### 6. Cancelar venda — botão destacado

```tsx
const handleCancelSale = () => {
  if (!props.permissions.canCancel) return;
  router.put(`/sells/${transaction.id}`, {
    ...data,
    status: 'cancelled',
    cancel_reason: 'Cancelado via tela de edição',
  }, {
    preserveScroll: true,
    onSuccess: () => router.visit('/sells'),
  });
};
```

UI: botão rose-600 no footer sticky + confirm modal (Card simples, não Radix Dialog) com 3 frases:
1. "A venda #X será marcada como cancelada"
2. "Estoque reservado é liberado, NFe precisa cancelamento fiscal separado"
3. "Ação fica registrada no histórico de auditoria"

Backend: SellPosController@update já reconhece `status='cancelled'` via state machine canônica (ADR 0143). Quando FSM Pipeline migrar pra biz=164, transitar via `ExecuteStageActionService::execute('cancelar_venda')`.

### 7. Validar build + smoke

```bash
npm run build:inertia
grep "Pages/Sells/Edit" public/build-inertia/manifest.json

# deploy biz=164 (canary)
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && git pull && composer install && npm run build:inertia'

# smoke biz=164: editar venda existente
curl -s -H "Cookie: $SESSION_BIZ_164" \
  https://oimpresso.com/sells/12345/edit \
  | grep -o '"component":"[^"]*"'
# → "component":"Sells/Edit"

# smoke biz=1 sem flag: confirma Blade legacy
curl -s -H "Cookie: $SESSION_BIZ_1" \
  https://oimpresso.com/sells/67890/edit \
  | grep -c "sell-edit-form"
# → 1 (HTML Blade preservado)
```

## 4. Tokens CSS

| Token | Onde aplica nesta tela | Esta tela usa? |
|---|---|---|
| `--bg`, `--bg-2` | Fundo do `<section>` envolvente | ✅ |
| `--panel`, `--panel-2` | Cards Dados/Produtos/Pagamento/Resumo | ✅ |
| `--border`, `--border-2` | Divider entre seções + `<details>` | ✅ |
| `--text`, `--text-mute` | Labels + placeholder + helper text | ✅ |
| `--accent`, `--accent-soft` | Botão "Salvar alterações" + foco | ✅ |
| `--origin-FIN-{bg,fg}` | Bloco pagamento (origem Financeiro) | ✅ |
| `rose-{50,600,700}` | Botão "Cancelar venda" + badge cancelada | ✅ |
| `--row-h`, `--card-pad`, `--card-gap` | Densidade dos blocos | ✅ |

```tsx
// ✅ correto — tokens semânticos
<Button className="text-rose-600 border-rose-300 hover:bg-rose-50">
  Cancelar venda
</Button>

// ❌ errado — cor crua sem tom
<div className="bg-red-500 text-white">Cancelar</div>
```

## 5. Estados visuais

| Estado | Trigger | Tokens / classes | Notas |
|---|---|---|---|
| `default` | — | `bg-card border-border` | Base de cada card |
| `cancelled` | `transaction.status === 'cancelled'` | inputs `disabled` + badge rose no header | Read-only |
| `focus` | tab/click | `focus-visible:ring-2 focus-visible:ring-accent` | Obrigatório R-DS-006 |
| `disabled` (sem perm) | `permissions.editPrice === false` | `opacity-50 pointer-events-none` | unit_price readonly |
| `loading` | submit em curso | `<Loader2/>` no botão + processing flag | useForm.processing |
| `empty` | tabela produtos vazia | `<EmptyState icon="package"/>` | Caso raro Edit; ainda assim suportado |
| `error` | validação backend | `<FieldError>` inline + auto-open `<details>` | Não toast genérico |
| `confirm-cancel` | clique "Cancelar venda" | Modal `role="dialog"` Card centralizado | Confirma intent destrutivo |

## 6. Responsividade

Lara/Dani usam monitor 1280px (canary Martinho biz=164). Otimizar pra essa largura primeiro — pattern Larissa ROTA LIVRE (biz=4).

| Breakpoint | Largura | Comportamento |
|---|---|---|
| default | <640px | 1 col, blocos empilhados |
| sm | ≥640px | 1 col, label+input lado-a-lado |
| md | ≥768px | 2 col em Dados + 2 col em Mais opções |
| lg | ≥1024px | Tabela produtos full-width, 3 col em "Mais opções" |
| xl | ≥1280px | **Alvo Lara/Dani** — KPI cards 4-up, sticky footer canto direito |

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `Ctrl/Cmd+S` | Salvar (Lara/Dani vêm de Word/Excel) | Tela inteira | `useEffect` |
| `Cmd+Enter` | Salvar (consistência Create) | Tela inteira | `useEffect` |
| `Esc` | Blur input ativo | Tela inteira | `useEffect` |
| `/` | — (busca produto não tem atalho dedicado em Edit; foco manual) | — | — |
| `⌘K` | Busca global | Shell | AppShellV2 |

## 8. Component contract

```ts
interface SellsEditPageProps {
  transaction: TransactionForEdit;       // pre-fill source
  sellDetails: SellEditLine[];           // items existentes
  paymentLines: SellEditPaymentLine[];   // payments existentes
  paymentTypes: Record<string, string>;
  invoiceSchemes: OptionMap;
  taxes: Record<number, string>;
  priceGroups: OptionMap;
  shippingStatuses: Record<string, string>;
  commissionAgents: OptionMap;
  customerGroups: OptionMap;
  accounts: OptionMap;
  statuses: Record<string, string>;
  users: OptionMap | [];
  walkInCustomer: { id: number; name: string };
  permissions: {
    editDiscount: boolean;
    editPrice: boolean;
    canCancel: boolean;
    maxDiscount?: number | null;
  };
  posSettings: Record<string, unknown>;
  customerDue?: string;
  isOrderRequestEnabled?: boolean;
}
```

**Componentes shared usados:**

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — Persistent Layout
- [`@/Components/shared/EmptyState`](../../../resources/js/Components/shared/EmptyState.tsx) — tabela produtos vazia
- shadcn: `Button`, `Input`, `Label`, `Textarea`, `Card`, `Select`
- `./_components/*` — Sells canon (reusa Create)

## 9. DoD checklist

- [ ] Page vive em `AppShellV2` via `SellsEdit.layout = (page) => <AppShellV2>{page}</AppShellV2>`
- [ ] Tokens semânticos (rose/emerald/amber/blue OK; gray/indigo/purple/pink/yellow ❌)
- [ ] Sem `window.location.reload()` — usa `router.put/visit`
- [ ] `transaction_date` pre-filled via `format_date` do backend (consistência Blade legacy)
- [ ] Multi-tenant: `Transaction::where('business_id', $business_id)` no SellController@edit (Tier 0)
- [ ] Bundle Inertia builda: `npm run build:inertia` + entrada `Pages/Sells/Edit` em manifest
- [ ] Atalhos `Ctrl/Cmd+S`, `Cmd+Enter`, `Esc` registrados com cleanup
- [ ] Estados cobertos: default/cancelled/focus/disabled/loading/empty/error/confirm-cancel
- [ ] PT-BR em todo label/copy
- [ ] Dark mode validado (contraste ≥ 4.5:1)
- [ ] Validado em 1280px (Lara/Dani Martinho) + 1920px + 768px
- [ ] Smoke biz=1 (Wagner WR2 SC) — NÃO biz=4 nem biz=164 em teste automatizado (ADR 0101)
- [ ] Smoke prod biz=164: editar 1 venda real após canary cutover semana 19/maio (com aviso prévio Jair/Lara)
- [ ] DUAL-MODE: biz=4 (ROTA LIVRE) bate Blade legacy; biz=164 bate Inertia
- [ ] Charter `Sells/Edit.charter.md` revisado por Wagner

## 10. Pegadinhas

- ❌ NÃO mudar `business_id` da venda em UPDATE — **Tier 0 IRREVOGÁVEL** (ADR 0093). Frontend não tem campo, backend `SellPosController@update` não aceita.
- ❌ NÃO usar `route()` antes de confirmar Ziggy global está disponível — desde PR #180 está OK; verificar `resources/js/global.d.ts`.
- ❌ NÃO esquecer de preservar `transaction_sell_lines_id` em items existentes ao adicionar item novo — sem isso UPDATE vira INSERT duplicado.
- ❌ NÃO permitir DELETE direto de venda — só `status='cancelled'` (audit trail).
- ❌ NÃO testar UPDATE em `business_id=4` (Larissa) nem `business_id=164` (Martinho) — ADR 0101: smoke `business_id=1` (Wagner WR2 SC).
- ❌ NÃO confiar em `transaction->transaction_date` formato cru — `SellController@edit` linha 1910 já chama `transactionUtil->format_date` que retorna "DD/MM/YYYY HH:mm". Componente converte client-side com `toDatetimeLocal`/`fromDatetimeLocal`.
- ❌ NÃO migrar `is_export + export_custom_field_*` nesta US — não há user request (Lara/Dani não usam export). Adicionar quando Wagner pedir.
- ❌ NÃO duplicar feature flag — `useV2SellsEdit` é flag separada de `useV2SellsCreate`. Permite canary independente por tela.
- ❌ NÃO ativar Inertia pra biz=4 (ROTA LIVRE Larissa) — guard `$business_id !== 4` preserva Blade legacy (hotfix 2026-05-13). Removeremos quando bugs corrigidos + canary biz=4 re-ativado.
- ❌ NÃO permitir edit em venda com NFe autorizada **sem warning explícito** — quando schema disponibilizar `nfe_status` na transaction, adicionar badge "Esta venda tem NFe autorizada — edição não afeta a NFe" (próximo PR).
- ❌ NÃO ativar canary Lara/Dani biz=164 sem co-design presencial — ADR 0104 §F4 exige canary 7 dias + smoke real. Wagner agendou semana 19/maio com Jair (dono).

## 11. ADR de origem

- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — 5 fases obrigatórias
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md) — `business_id` global scope
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — pattern canônico
- [ADR 0143 — FSM Pipeline LIVE prod biz=1](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — `cancelar_venda` é stage action canônica
- [ADR 0101 — Tests biz_id=1 nunca cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — smoke biz=1 sempre
- [ADR 0121 — Modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — biz=164 Martinho candidato `Modules/OficinaAuto`
- [_DS ADR 0008 — Cockpit layout-mãe](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [RUNBOOK Sells/create](RUNBOOK-create.md) — pattern irmão (canon)

> Esta tela **espelha 100% Sells/Create.tsx** estrutura visual + comportamento. Wrapper finíssimo (vs Create) não foi possível porque Edit tem 3 features exclusivas: pre-fill, cancelar venda, read-only mode. Trade-off documentado.

---

**Última atualização:** 2026-05-14
