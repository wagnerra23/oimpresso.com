---
slug: sells-runbook-create
title: "Sells — Runbook da tela Adicionar venda (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-08
---

# RUNBOOK — Adicionar venda (`/sells/create`)

> **Tipo:** runbook de migração Blade → Inertia/React (MWART)
> **Refs:** [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md), [ADR 0023](../../decisions/0023-inertia-v3-upgrade.md), [_DS ADR 0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
> **Estado origem:** Blade legacy renderiza `view('sale_pos.create')` via [SellPosController@create](../../../app/Http/Controllers/SellPosController.php#L158) — 996 LOC Blade + 60+ partials + `public/js/pos.js` (3.178 LOC jQuery)
> **Estado alvo:** `Pages/Sells/Create.tsx` (Inertia v3 + React 19 + shadcn) — não existe ainda
> **Persona alvo:** Larissa (ROTA LIVRE, biz=4) — loja de roupa em Gravatal/SC (vestuário, CNAE 4781-4/00), monitor 1280px, faz orçamento → venda → produção → entrega → cobrança. Persona estende-se a outros verticais: gráfica (Modules/ComunicacaoVisual), oficina (Modules/OficinaAuto) — ver [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/sells/create` renderiza Page React | `curl -s /sells/create \| grep 'data-page'` mostra `"component":"Sells/Create"` |
| Bundle Inertia builda | `npm run build:inertia && grep "Pages/Sells/Create" public/build-inertia/manifest.json` |
| AppShellV2 envolvendo | Inspetor: `<div class="app-shell-v2">` ao redor da Page |
| Permissão `sell.create` respeitada | Login com role sem `sell.create` → 403 |
| Multi-tenant `business_id` scopado | Larissa (biz=4) só vê seus customers/products no autocomplete |
| Atalhos `/` e `Esc` respondem | `/` foca busca de produto; `Esc` fecha modais |
| Default `Status=Final` aplicado | Form abre com Status preenchido (ROTA LIVRE 99% das vezes é Final) |
| `format_now_local` no `tx_date` (NÃO `format_date`) | Auto-mem `feedback_format_now_local_e_default_datetime`: campo "Data da venda" mostra hora real, sem shift +3h |

## 1. Objetivo

Migrar a tela "Adicionar venda" de Blade legacy (`sale_pos.create` + 27 props compactados + jQuery) pra Inertia/React seguindo padrão MWART. **A tela vive dentro do AppShellV2** mas é **modo "form" full-width**, NÃO o layout 3-colunas Cockpit (não há "conversa em foco" nem "apps vinculados a entidade" — é criação de venda nova). Persona alvo é Larissa: gráfica que digita ~5 vendas/dia + orçamentos retroativos. Resolve fricção de hoje (scroll vertical 3 telas até salvar, 12 campos opcionais misturados com obrigatórios, lag de jQuery + Select2 + DataTables).

## 2. Pré-condições

- [ ] Permissão `sell.create` (ou `superadmin`, ou `repair.create` + `repair_module`) atribuída ao usuário
- [ ] Caixa registradora aberta — se não houver, redirect pra [`CashRegisterController@create`](../../../app/Http/Controllers/CashRegisterController.php) (mesmo comportamento legacy)
- [ ] Quota `invoices` disponível (subscription ativa)
- [ ] Default location escolhível: vem de `cash_register.location_id` ou primeira de `BusinessLocation::forDropdown`
- [ ] Page Inertia em [`resources/js/Pages/Sells/Create.tsx`](../../../resources/js/Pages/Sells/Create.tsx) (criar nesta migração)
- [ ] Skill irmã carregada: [`multi-tenant-patterns`](../../../.claude/skills/multi-tenant-patterns/SKILL.md) (Tier A — `business_id` global scope obrigatório em toda query)
- [ ] Skill irmã carregada: [`mwart-quality`](../../../.claude/skills/mwart-quality/SKILL.md) (9 pré-flight checks antes de codar)
- [ ] Auto-mem lida: `cliente_rotalivre`, `feedback_format_now_local_e_default_datetime`, `feedback_form_shim_bool_attrs`, `preference_persistent_layouts`, `preference_cache_estado_preservado`

## 3. Passo-a-passo

### 1. Adicionar action `Sells/Create` ao controller

```php
// app/Http/Controllers/SellPosController.php
public function create()
{
    // ... toda a lógica atual de carregamento dos 27 props (linhas 158-264) ...

    if (request()->wantsJson() || request()->header('X-Inertia')) {
        return Inertia::render('Sells/Create', [
            'businessLocations'      => $business_locations,
            'defaultLocation'        => $default_location,
            'walkInCustomer'         => $walk_in_customer,
            'paymentTypes'           => $payment_types,
            'invoiceSchemes'         => $invoice_schemes,
            'defaultInvoiceScheme'   => $default_invoice_schemes,
            'taxes'                  => $taxes,
            'priceGroups'            => $price_groups,
            'defaultPriceGroupId'    => $default_price_group_id,
            'shippingStatuses'       => $shipping_statuses,
            'defaultDatetime'        => $default_datetime,  // format_now_local — NÃO format_date
            'commissionAgents'       => $commission_agent,
            'customerGroups'         => $customer_groups,
            'permissions'            => [
                'editDiscount' => $edit_discount,
                'editPrice'    => $edit_price,
            ],
            'posSettings'            => $pos_settings,
            'businessSettings'       => [
                'enableRp'        => session('business.enable_rp') == 1,
                'enableCategory'  => session('business.enable_category') == 1,
                'enableBrand'     => session('business.enable_brand') == 1,
                'commsnAgntMode'  => $business_details->sales_cmsn_agnt,
            ],
            'subType'                => $sub_type,
        ]);
    }

    return view('sale_pos.create')->with(compact(/* legacy props */));
}
```

**Validação:** rodar `curl -s -H "X-Inertia: true" -H "X-Inertia-Version: $(grep version manifest)" /sells/create` retorna JSON `{"component":"Sells/Create",...}`.

### 2. Criar a Page Inertia

```tsx
// resources/js/Pages/Sells/Create.tsx
import { AppShellV2 } from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { useForm } from '@inertiajs/react'

interface SellsCreatePageProps {
  businessLocations: Record<number, string>
  defaultLocation: { id: number; name: string; selling_price_group_id: number | null }
  walkInCustomer: { id: number; name: string }
  paymentTypes: Record<string, string>
  invoiceSchemes: Record<number, string>
  defaultInvoiceScheme: { id: number; name: string }
  taxes: Array<{ id: number; name: string; amount: number }>
  priceGroups: Record<number, string>
  defaultPriceGroupId: number | null
  shippingStatuses: Record<string, string>
  defaultDatetime: string  // já em formato local com format_now_local
  commissionAgents: Record<number, string>
  customerGroups: Record<number, string>
  permissions: { editDiscount: boolean; editPrice: boolean }
  posSettings: Record<string, unknown>
  businessSettings: {
    enableRp: boolean
    enableCategory: boolean
    enableBrand: boolean
    commsnAgntMode: 'user' | 'cmsn_agnt' | string
  }
  subType: string | null
}

export default function SellsCreate(props: SellsCreatePageProps) {
  const { data, setData, post, processing, errors } = useForm({
    location_id: props.defaultLocation?.id ?? null,
    contact_id: props.walkInCustomer.id,
    transaction_date: props.defaultDatetime,
    status: 'final', // default Final — Larissa 99% (auto-mem cliente_rotalivre)
    invoice_scheme_id: props.defaultInvoiceScheme?.id ?? null,
    products: [] as Array<{ product_id: number; quantity: number; unit_price: number; discount: number }>,
    payments: [] as Array<{ amount: number; method: string; paid_on: string; account_id: number | null }>,
    shipping: { details: '', address: '', cost: 0, status: '' },
    discount: { type: 'percentage' as 'percentage' | 'fixed', value: 0 },
    notes: '',
  })

  // ... seções: Header, Cliente+Data, Produtos, Pagamento, Frete, Total, Ações

  return (
    <div className="container mx-auto p-6 space-y-6">
      <PageHeader title="Adicionar venda" backHref="/sells" />
      {/* seções */}
    </div>
  )
}

SellsCreate.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>
```

### 3. Mapear campos legacy → alvo (com triagem de visibilidade)

| Campo legacy | Alvo | Visível por padrão? | Justificativa |
|---|---|---|---|
| `select_location_id` | `location_id` | ✅ | Multi-location é real |
| `price_group` | `price_group_id` | 🟡 Só se `priceGroups.length > 1` | ROTA LIVRE só tem 1 |
| `types_of_service_id` | `types_of_service_id` | ❌ | Módulo `types_of_service` desabilitado em ROTA LIVRE |
| `is_subscription` | `is_subscription` | ❌ | Módulo subscription desabilitado em ROTA LIVRE |
| `customer_id` | `contact_id` | ✅ | Crítico — autocomplete buscando contatos |
| `pay_term_number` + `pay_term_type` | `pay_term` | 🟡 Em `<details>` colapsado | Larissa raramente usa |
| `commission_agent` | `commission_agent_id` | 🟡 Só se `commsnAgntMode != null` | Off em ROTA LIVRE |
| `transaction_date` | `transaction_date` | ✅ | Default `defaultDatetime` (format_now_local) |
| `status` | `status` | ✅ | Default `final` em ROTA LIVRE (auto-mem) |
| `invoice_scheme_id` | `invoice_scheme_id` | 🟡 Em `<details>` colapsado | Larissa não muda |
| `invoice_no` | `invoice_no` | 🟡 Em `<details>` colapsado | Auto-gerado se vazio |
| `document` (anexar) | `document` | 🟡 Em `<details>` colapsado | Caso raro |
| Tabela de produtos | `products[]` | ✅ | Coração da tela |
| `discount_type` + `discount_amount` | `discount` | ✅ Mas inline, não card separado | |
| `tax_rate_id` (Imposto do pedido) | `tax_rate_id` | 🟡 Em `<details>` colapsado | Empresa cliente ROTA LIVRE = ME, não destaca imposto |
| `additional_notes` | `notes` | ✅ | |
| Bloco frete (5 campos) | `shipping` | 🟡 Em `<details>` colapsado | Só serviços com entrega |
| Bloco pagamento | `payments[]` | ✅ | Padrão: 1 linha vazia |

> **Ganho concreto:** de 18 campos sempre visíveis → 8 sempre visíveis + 10 em `<details>` colapsáveis. Reduz scroll de 3 telas pra 1.

### 4. Reusar componentes shared

```tsx
import { PageHeader } from '@/Components/shared/PageHeader'
import { DataTable } from '@/Components/shared/DataTable'
import { EmptyState } from '@/Components/shared/EmptyState'
import { StatusBadge } from '@/Components/shared/StatusBadge'
```

Componentes desta tela que provavelmente serão **novos shared** (extrair quando tela 2 surgir, não antes — R-DS-001 imitação):

- `<ProductSearchAutocomplete/>` — busca por nome/SKU/código de barras com debounce 250ms
- `<MoneyInput/>` — input formatado R$ com `inputmode="decimal"` (pra calculadora touch)
- `<PaymentRow/>` — linha de pagamento (valor + método + conta + data + nota)

### 5. Registrar atalhos

```tsx
useEffect(() => {
  const handler = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
      if (e.key === 'Escape') (e.target as HTMLElement).blur()
      return
    }
    if (e.key === '/') {
      e.preventDefault()
      productSearchRef.current?.focus()
    }
    if (e.key === 'Escape') closeAllSheets()
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') handleSubmit()
  }
  window.addEventListener('keydown', handler)
  return () => window.removeEventListener('keydown', handler)
}, [handleSubmit])
```

### 6. Persistir rascunho em `localStorage`

```tsx
// Auto-save a cada mudança (debounced 500ms) — Larissa atende telefone no meio
const STORAGE_KEY = `oimpresso.sells.create.draft.${businessId}.${userId}`

useEffect(() => {
  const t = setTimeout(() => localStorage.setItem(STORAGE_KEY, JSON.stringify(data)), 500)
  return () => clearTimeout(t)
}, [data])

// Ao montar, oferecer "Recuperar rascunho?" se houver
useEffect(() => {
  const draft = localStorage.getItem(STORAGE_KEY)
  if (draft) setShowDraftRecovery(true)
}, [])
```

### 7. Validar build + smoke

```bash
npm run build:inertia
grep "Pages/Sells/Create" public/build-inertia/manifest.json
# deploy
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd ~/domains/oimpresso.com/public_html && git pull && composer install && npm run build:inertia'
# smoke
curl -s https://oimpresso.com/sells/create -H "Cookie: $SESSION" | grep -o '"component":"[^"]*"'
```

## 4. Tokens CSS

| Token | Onde aplica nesta tela | Esta tela usa? |
|---|---|---|
| `--bg`, `--bg-2` | Fundo do `<section>` envolvente | ✅ |
| `--panel`, `--panel-2` | Cards de cada bloco (cliente, produtos, pagamento) | ✅ |
| `--border`, `--border-2` | Divider entre seções colapsáveis | ✅ |
| `--text`, `--text-mute` | Labels + placeholder + helper text | ✅ |
| `--accent`, `--accent-soft` | Botão "Salvar venda" + foco em campos | ✅ |
| `--origin-FIN-{bg,fg}` | Bloco de pagamento (origem Financeiro) | ✅ |
| `--origin-OS-{bg,fg}` | Tag se vier de OS (`sub_type=repair`) | 🟡 |
| `--row-h`, `--card-pad`, `--card-gap` | Densidade dos blocos | ✅ |

```tsx
// ✅ correto — tokens shadcn
<Card className="bg-card border-border p-[var(--card-pad)]">
  <Button variant="default">Salvar venda</Button>
</Card>

// ❌ errado — cor crua
<div className="bg-blue-500 text-white">Salvar</div>
```

## 5. Estados visuais

| Estado | Trigger | Tokens / classes | Notas |
|---|---|---|---|
| `default` | — | `bg-card border-border` | Estado base de cada card |
| `hover` | mouse-over em linha de produto | `hover:bg-muted/50` | Sinaliza editabilidade |
| `focus` | tab/click | `focus-visible:ring-2 focus-visible:ring-accent` | Obrigatório R-DS-006 |
| `disabled` | sem permissão `editPrice` | `opacity-50 pointer-events-none` | Campo `unit_price` readonly |
| `loading` | submit em curso | `<Spinner/>` no botão + `aria-busy` | `processing` do useForm |
| `empty` | tabela de produtos vazia | `<EmptyState icon={<Package/>}/>` | CTA "Buscar produto" |
| `error` | validação backend | `<FormError errors={errors.products}/>` por campo | Não toast genérico |

```tsx
{data.products.length === 0 && (
  <EmptyState
    icon={<Package />}
    title="Nenhum produto adicionado"
    description="Use o campo de busca acima ou aperte / pra focar."
    primaryAction={{ label: 'Buscar produto', onClick: () => productSearchRef.current?.focus() }}
  />
)}
```

## 6. Responsividade

Larissa usa monitor 1280px (auto-mem `cliente_rotalivre`). Otimizar pra essa largura primeiro.

| Breakpoint | Largura | Comportamento |
|---|---|---|
| `default` | <640px | 1 coluna, blocos empilhados, busca de produto sticky no topo |
| `sm` | ≥640px | 1 coluna, label + input lado-a-lado |
| `md` | ≥768px | 2 colunas em "Cliente+Data" e "Pagamento+Frete" |
| `lg` | ≥1024px | Tabela de produtos full-width, blocos auxiliares 2-col |
| `xl` | ≥1280px | **Alvo Larissa** — botão "Salvar venda" sticky no canto inferior direito |
| `2xl` | ≥1536px | Mesmo do `xl`, sem expandir mais (legibilidade) |

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell | Já no AppShellV2 |
| `J` / `K` | — | — | Tela não tem lista navegável; marcar `—` |
| `E` / `A` | — | — | Tela é form, não inbox |
| `N` | — | — | Já está em "criar nova" |
| `/` | Foca busca de produto | Tela inteira | `useEffect` (passo 5) |
| `Esc` | Fecha sheet/modal/drawer ou blur de input | Tela inteira | `useEffect` |
| `⌘+Enter` | Submeter venda | Form | `useEffect` |

## 8. Component contract

```tsx
interface SellsCreatePageProps {
  businessLocations: Record<number, string>
  defaultLocation: { id: number; name: string; selling_price_group_id: number | null }
  walkInCustomer: { id: number; name: string }
  paymentTypes: Record<string, string>
  invoiceSchemes: Record<number, string>
  defaultInvoiceScheme: { id: number; name: string }
  taxes: Array<{ id: number; name: string; amount: number }>
  priceGroups: Record<number, string>
  defaultPriceGroupId: number | null
  shippingStatuses: Record<string, string>
  defaultDatetime: string
  commissionAgents: Record<number, string>
  customerGroups: Record<number, string>
  permissions: { editDiscount: boolean; editPrice: boolean }
  posSettings: Record<string, unknown>
  businessSettings: {
    enableRp: boolean
    enableCategory: boolean
    enableBrand: boolean
    commsnAgntMode: 'user' | 'cmsn_agnt' | string
  }
  subType: string | null
}
```

**Componentes shared usados:**

- [`@/Components/shared/PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx)
- [`@/Components/shared/EmptyState`](../../../resources/js/Components/shared/EmptyState.tsx)
- [`@/Components/shared/StatusBadge`](../../../resources/js/Components/shared/StatusBadge.tsx)
- shadcn: `Button`, `Input`, `Select`, `Sheet`, `Card`

## 9. DoD checklist

- [ ] Page vive em `AppShellV2` via `Tela.layout = (page) => <AppShellV2>{page}</AppShellV2>`
- [ ] Tokens shadcn semânticos (sem cor crua — R-DS-002)
- [ ] Não envolve `<AppShell>` (auto-mem `preference_persistent_layouts`)
- [ ] Sem `window.location.reload()` — usa `router.get/post` (auto-mem `preference_cache_estado_preservado`)
- [ ] `transaction_date` usa `defaultDatetime` (`format_now_local`), NÃO `format_date` (auto-mem `feedback_format_now_local_e_default_datetime`)
- [ ] Default `Status=final` aplicado pra ROTA LIVRE (auto-mem `cliente_rotalivre`)
- [ ] Multi-tenant: queries usam global scope `business_id` (skill `multi-tenant-patterns`)
- [ ] Bundle Inertia builda: `npm run build:inertia` + entrada `Pages/Sells/Create` em `manifest.json`
- [ ] Atalhos `/`, `Esc`, `⌘+Enter` registrados com cleanup
- [ ] Estados cobertos: default/hover/focus/disabled/loading/empty/error
- [ ] PT-BR em todo label/copy
- [ ] Dark mode validado (contraste ≥ 4.5:1 — R-DS-005)
- [ ] Validado em 1280px (Larissa) + 1920px + 768px
- [ ] Smoke em produção: criar venda mock pra biz=1 (Wagner WR2 SC), NÃO biz=4 (auto-mem `feedback_test_business_id_1_nunca_4`)

## 10. Pegadinhas

- ❌ NÃO usar `format_date($default_datetime)` no campo "Data da venda" — aplica shift histórico +3h. Usar `defaultDatetime` direto que já vem de `format_now_local()` (auto-mem `feedback_format_now_local_e_default_datetime`). Sintoma: hora aparece 3h adiantada.
- ❌ NÃO testar criação de venda em `business_id=4` (ROTA LIVRE/Larissa). Sempre `business_id=1` (Wagner WR2 SC). Auto-mem `feedback_test_business_id_1_nunca_4`: "biz=4 é cliente; usar como default em test = grave."
- ❌ NÃO remover o cliente padrão (walk-in) do dropdown — alguns tipos de venda (POS rápido) ainda dependem dele. Marcar visível mas com badge `padrão` pra desencorajar uso em venda regular.
- ❌ NÃO confiar no shim `App\View\Helpers\Form` pra normalizar bool — em React form não tem o problema, mas se precisar reusar mesmo backend `store()`, lembrar que ele aceita bool true/false (auto-mem `feedback_form_shim_bool_attrs`).
- ❌ NÃO usar `route()` antes de confirmar Ziggy global está disponível — desde [PR #180](https://github.com/wagnerra23/oimpresso.com/pull/180) (2026-05-07) está OK, mas verificar `resources/js/global.d.ts` tem declaração antes (auto-mem GOTCHAS.md).
- ❌ NÃO criar `Pages/Sells/_components/` se `Components/shared/` já cobre — extrair shared só quando tela 2 reusar (R-DS-001).
- ❌ NÃO manter `sale_pos.create.blade.php` como fallback indefinidamente — definir flag `useV2SellsCreate` em `pos_settings` ou cookie, e remover Blade após 30 dias em prod sem regressão.

## 11. ADR de origem

- [ADR 0039 — Chat Cockpit](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe (esta tela usa AppShellV2 modo "form" sem coluna direita; documentar exceção §3 inline)
- [ADR 0011 — Padrão Jana](../../decisions/0011-alinhamento-padrao-jana.md) — base UltimatePOS-like; manter compatibilidade do endpoint `store()` legado pra zero downtime
- [ADR 0023 — Inertia v3](../../decisions/0023-inertia-v3.md) — base técnica
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md) — `business_id` global scope IRREVOGÁVEL
- [_DS ADR 0008 — Cockpit layout-mãe](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [_DS ADR 0009 — Sidebar light](../_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md)

> Esta tela **não tem coluna direita "Apps Vinculados"** porque é criação (sem entidade-foco). Justificado inline acima — não exige ADR substitutiva.

---

**Última atualização:** 2026-05-08
