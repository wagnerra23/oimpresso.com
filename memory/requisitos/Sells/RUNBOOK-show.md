---
slug: sells-runbook-show
title: "Sells — Runbook da tela Detalhar venda /sells/{id} (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-15
wave: W1-A (Bucket B1 Sells)
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/vendas-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149 aceita 2026-05-15)"
  derived_from: "Sells/Index (mesma entidade transactions)"
  divergence_from_blueprint: "Show é detail full-page (não drawer SaleSheet) — pattern derivado mas seções espelham o drawer"
---

# RUNBOOK — Detalhar venda (`/sells/{id}`)

> **Tipo:** runbook MWART (Blade → Inertia/React)
> **Refs:** ADR 0104 (MWART canon), ADR 0149 (screen-pattern reuse), ADR 0143 (FSM Pipeline LIVE), ADR 0107 (visual gate), ADR 0093 (multi-tenant Tier 0)
> **Estado origem:** Blade `sale_pos.show` renderizada via `SellController@show($id)` linha 1597.
> **Estado alvo:** `Pages/Sells/Show.tsx` (Inertia v3 + React 19 + AppShellV2).
> **Persona alvo:** Larissa (ROTA LIVRE biz=4) abre venda específica pra: revisar produtos, status pagamento, ações FSM (cobrar/cancelar/iniciar produção), reimprimir DANFE, ver timeline.

## 1. Objetivo

Migrar detalhe completo de venda — hoje Blade legacy modal-grande com seções concatenadas (linhas + pagamentos + impostos + atividades) — para página Inertia React **layout 2 colunas** (esquerda: cabeçalho + linhas + pagamentos; direita: FSM panel + timeline + ações). Reusa SaleSheet componentes (FsmActionPanel + SaleTimeline + FiscalSection) já validados, mas em modo full-page.

## 2. Pré-condições

- [ ] Permissão `sell.view` OU `direct_sell.access` OU `view_own_sell_only` (mantém policy legacy)
- [ ] `business_id` global scope ativo (Tier 0 ADR 0093)
- [ ] FSM Pipeline ativo na transação se aplicável (ADR 0143)
- [ ] Page Inertia `resources/js/Pages/Sells/Show.tsx` (criar)
- [ ] Skill Tier A `mwart-process` + `multi-tenant-patterns` + `inertia-defer-default`

## 3. Passo-a-passo

### 1. Alterar `SellController@show($id)` para Inertia::render com defer

```php
public function show($id)
{
    $business_id = request()->session()->get('user.business_id');

    // Auth gate (mesma policy legacy)
    if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && ! auth()->user()->can('view_own_sell_only')) {
        abort(403, 'Unauthorized action.');
    }

    // Query base mínima (eager curto) — multi-tenant Tier 0 ADR 0093
    $query = Transaction::where('business_id', $business_id)
        ->where('id', $id)
        ->with(['contact', 'location']);

    if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
        $query->where('transactions.created_by', request()->session()->get('user.id'));
    }

    $sell = $query->firstOrFail();

    // Cabeçalho enxuto eager (props leves)
    $headline = [
        'id' => $sell->id,
        'invoice_no' => $sell->invoice_no,
        'transaction_date' => $sell->transaction_date,
        'final_total' => (float) $sell->final_total,
        'total_paid' => (float) ($sell->payment_lines()->sum('amount') ?? 0),
        'payment_status' => $sell->payment_status,
        'status' => $sell->status,
        'current_stage_key' => optional($sell->currentStage ?? null)->key,
        'customer' => $sell->contact ? [
            'id' => $sell->contact->id,
            'name' => $sell->contact->name,
            'mobile' => $sell->contact->mobile,
            'email' => $sell->contact->email,
        ] : null,
        'location' => $sell->location ? ['id' => $sell->location->id, 'name' => $sell->location->name] : null,
    ];

    // Branch dual: se vier X-Inertia, render Inertia com defer.
    if (request()->header('X-Inertia') || request()->wantsJson()) {
        return \Inertia\Inertia::render('Sells/Show', [
            'saleId' => (int) $id,
            'headline' => $headline,
            // Props CARAS deferidas (skill inertia-defer-default Tier B)
            'detail' => \Inertia\Inertia::defer(fn () => $this->buildShowDetailPayload($id, $business_id)),
            'permissions' => [
                'edit' => auth()->user()->can('direct_sell.update') || auth()->user()->can('so.update'),
                'delete' => auth()->user()->can('direct_sell.delete') || auth()->user()->can('sell.delete'),
                'print' => true,
            ],
            'urls' => [
                'edit' => action([\App\Http\Controllers\SellController::class, 'edit'], [$id]),
                'print' => '/sells/' . $id . '/print',
                'sheet_data' => '/sells/' . $id . '/sheet-data',
                'back' => '/sells',
            ],
        ]);
    }

    // Fallback Blade legacy preservado.
    return $this->buildShowBladeResponse($id, $business_id, $sell);
}
```

**Validação:** `curl -H "X-Inertia: true" /sells/1` retorna JSON com `"component":"Sells/Show"`. Detail vem em segunda request (deferred reload).

### 2. `buildShowDetailPayload($id, $business_id)` — método privado

Encapsula toda a lógica pesada de `show()` legacy (8 with() + sell_lines + activities + payment_types + order_taxes + line_taxes + sales_orders) numa closure deferida. Retorna array serializado pro frontend.

### 3. Criar `Pages/Sells/Show.tsx`

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import FsmActionPanel from './_components/FsmActionPanel';
import SaleTimeline from './_components/SaleTimeline';
import FiscalSection from './_components/FiscalSection';
import KpiCard from '@/Components/shared/KpiCard';
import { Button } from '@/Components/ui/button';
import { Edit, Printer, ArrowLeft, Receipt } from 'lucide-react';

interface ShowProps { /* ... */ }

export default function SellsShow(props: ShowProps) {
  // Layout 2 colunas: 8/12 esquerda (headline + linhas + pagamentos) + 4/12 direita (FSM + timeline + ações)
}

SellsShow.layout = (page) => <AppShellV2>{page}</AppShellV2>;
```

### 4. Mapear seções Blade → React

| Seção Blade legacy | Seção React | Componente |
|---|---|---|
| `@include('sale_pos.partials.sale_details')` (cabeçalho) | `<SaleHeadline/>` inline | local |
| Tabela linhas (`sell_lines`) | `<SaleLinesTable/>` inline | local |
| Bloco pagamentos (`payment_lines`) | `<SalePaymentsList/>` inline | local |
| `@include('sale_pos.partials.activities')` | `<SaleTimeline/>` shared | `_components/SaleTimeline.tsx` (reuso SaleSheet) |
| Bloco fiscal (NFe/NFC-e) | `<FiscalSection/>` shared | `_components/FiscalSection.tsx` (reuso) |
| Bloco ações (cancel/return/print) | `<FsmActionPanel/>` shared | `_components/FsmActionPanel.tsx` (reuso) |

### 5. Densidade Cowork (NÃO copiar Blade 1:1)

- Cards de seção `bg-card border-border rounded-lg p-4`
- Cabeçalho h1 24px font-semibold "Venda #{invoice_no}"
- KPIs grandes 4-col: Total / Pago / Falta / Status pgto
- Tabela linhas zebra-strip leve (`bg-muted/30` em even)
- Microcopy PT-BR ("À vista" / "A receber" / "Parcial")

## 4. Tokens CSS

`--panel`, `--border`, `--accent`, `--accent-soft`, `--origin-FIN-bg` (bloco pagamento), `--origin-OS-bg` (se sub_type=repair). Sem cor crua.

## 5. Estados visuais

| Estado | Trigger | Token |
|---|---|---|
| loading detail | `<Deferred fallback={<DetailSkeleton/>}>` | skeleton-shimmer |
| empty payments | venda à vista zerada | `<EmptyState/>` mini |
| error 404 | venda doutra biz | abort 404 backend |
| FSM disabled | sem stage | painel não renderiza |

## 6. Responsividade

1280px (Larissa): 2 cols. <1024px: empilha (linhas/pagamentos primeiro, ações depois).

## 7. Atalhos

| Tecla | Ação |
|---|---|
| `E` | edit (se permissão) |
| `P` | print |
| `Esc` | volta /sells |
| `⌘+K` | busca global (AppShellV2) |

## 8. Component contract

```tsx
interface ShowProps {
  saleId: number;
  headline: {
    id: number;
    invoice_no: string;
    transaction_date: string;
    final_total: number;
    total_paid: number;
    payment_status: 'paid' | 'due' | 'partial' | string;
    status: 'final' | 'draft' | 'quotation' | 'proforma' | string;
    current_stage_key: string | null;
    customer: { id: number; name: string; mobile: string | null; email: string | null } | null;
    location: { id: number; name: string } | null;
  };
  detail?: {  // deferred
    lines: Array<{ id: number; product_name: string; quantity: number; unit_price: number; discount: number; subtotal: number; tax_amount: number }>;
    payments: Array<{ id: number; amount: number; method: string; paid_on: string | null; note: string | null }>;
    taxes: { order_taxes: Record<string, number>; line_taxes: Record<string, number> };
    activities: Array<{ description: string; causer_name: string; created_at: string }>;
    shipping: { details: string; address: string; cost: number; status: string | null };
    notes: string | null;
    sub_type: string | null;
    sales_orders: Record<number, string>;
  };
  permissions: { edit: boolean; delete: boolean; print: boolean };
  urls: { edit: string; print: string; sheet_data: string; back: string };
}
```

## 9. DoD checklist

- [ ] `SellController@show` retorna Inertia se header X-Inertia, Blade caso contrário
- [ ] `detail` prop usa `Inertia::defer()` (skill `inertia-defer-default` Tier B)
- [ ] `<Deferred>` wrapping client com fallback skeleton
- [ ] Multi-tenant `business_id` em TODA query do método (Tier 0 ADR 0093)
- [ ] Permission gate respeitado (`sell.view` OR `direct_sell.access` OR `view_own_sell_only`)
- [ ] Persistent Layout via `AppShellV2`
- [ ] PT-BR labels
- [ ] Reusa SaleTimeline, FiscalSection, FsmActionPanel (sem duplicar)
- [ ] Pest baseline `Wave1ShowBaselineTest.php` passa
- [ ] Pest inertia `Wave1ShowInertiaTest.php` passa (biz=1 vê venda, biz=99 não vê)

## 10. Pegadinhas

- ❌ NÃO usar `withoutGlobalScopes` sem comentário SUPERADMIN — viola Tier 0
- ❌ NÃO eager-load 8 with() na resposta inicial — usa defer
- ❌ NÃO copiar HTML Blade 1:1; reescrever em padrão Cowork denso
- ❌ NÃO esquecer fallback Blade — Larissa só vai pro Inertia se flag ON
- ❌ NÃO duplicar SaleTimeline/FiscalSection — reusa local componentes

## 11. Cutover plan (parent agent executa)

- Smoke biz=1 ROTA LIVRE manual em `/sells/1` — confirma visual + dados
- Canary 7d antes de remover Blade legacy `resources/views/sale_pos/show.blade.php`
- Comunicação Larissa via WhatsApp pré-cutover
- Rollback <30s via feature flag `useV2SellsShow` (a registrar em US-SELL-Wave1)

---

**Última atualização:** 2026-05-15 (Wave 1 Agent W1-A)
