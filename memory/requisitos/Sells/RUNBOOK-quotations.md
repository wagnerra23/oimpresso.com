---
slug: sells-runbook-quotations
title: "Sells — Runbook da tela Cotações /sells/quotations (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-15
wave: W1-A (Bucket B1 Sells)
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/vendas-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_from: "Sells/Drafts (estrutura idêntica; mudança: sub_status='quotation')"
  divergence_from_blueprint: "KPIs específicos cotação: válidas, vencidas, convertidas (futuro)"
---

# RUNBOOK — Cotações (`/sells/quotations`)

> **Tipo:** runbook MWART (Blade → Inertia/React)
> **Refs:** ADR 0104, ADR 0149, ADR 0110, ADR 0143 (FSM `quote_draft`/`quote_sent`)
> **Estado origem:** Blade `sale_pos.quotations` via `SellController@getQuotations()` linha 2018.
> **Estado alvo:** `Pages/Sells/Quotations.tsx` derivado do Drafts (mesma estrutura, sub_status='quotation').
> **Persona alvo:** Larissa envia cotação (formal proposal) pro cliente, converte em venda quando aprovada.

## 1. Objetivo

Migrar lista de cotações pra Cockpit V2 idêntica ao Drafts, com filtros próprios e ações específicas: enviar cotação por WhatsApp/email, converter em venda, marcar como vencida.

## 2. Pré-condições

- [ ] Permissão `quotation.view_all` OU `quotation.view_own`
- [ ] Endpoint AJAX `/sells/quotations` retorna DataTables JSON via `getDraftDatables` com `is_quotation=1` (preservado)
- [ ] FSM pipeline opt-in: stage `quote_sent` → `quote_accepted` → `quote_rejected` (ADR 0143)

## 3. Passo-a-passo

### 1. Alterar `SellController@getQuotations()` — dual response

```php
public function getQuotations()
{
    if (! auth()->user()->can('quotation.view_all') && ! auth()->user()->can('quotation.view_own')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    // KPIs cotação
    $quoteBase = Transaction::where('business_id', $business_id)
        ->where('type', 'sell')
        ->where('status', 'draft')
        ->where('sub_status', 'quotation');

    $kpis = [
        'total' => (clone $quoteBase)->count(),
        // futuro: validas/vencidas/convertidas via FSM stage_key
    ];

    $business_locations = BusinessLocation::forDropdown($business_id, false);
    $sales_representative = User::forDropdown($business_id, false, false, true);

    if (request()->header('X-Inertia') || request()->wantsJson()) {
        return \Inertia\Inertia::render('Sells/Quotations', [
            'kpis' => $kpis,
            'filters' => [
                'businessLocations' => $business_locations,
                'customers' => \Inertia\Inertia::defer(fn () => Contact::customersDropdown($business_id, false)),
                'salesRepresentative' => $sales_representative,
            ],
            'permissions' => [
                'view_all' => auth()->user()->can('quotation.view_all'),
                'view_own' => auth()->user()->can('quotation.view_own'),
            ],
            'urls' => [
                'datatable' => '/sells/quotations?is_quotation=1',
                'back' => '/sells',
            ],
        ]);
    }

    return view('sale_pos.quotations')
        ->with(compact('business_locations', 'sales_representative'));
}
```

### 2. Criar `Pages/Sells/Quotations.tsx`

Cabeçalho diferenciador "Cotações" + KPI total. Tabela 6 cols (data + nº + cliente + total + validade + ações). Mesma drawer SaleSheet.

### 3. Ações específicas cotação

| Ação | Endpoint | Notas |
|---|---|---|
| Enviar WhatsApp | POST `/whatsapp/send-quote/{id}` | módulo Whatsapp |
| Converter em venda | PUT `/sells/{id}/convert-quote-to-sale` | muda status='final' + sub_status=null + FSM `quote_accepted` |
| Imprimir PDF | GET `/sells/{id}/print?quotation=1` | layout cotação |
| Marcar vencida | POST `/sells/{id}/expire-quote` | FSM `quote_expired` |

## 4. Tokens — idem Drafts (Cockpit V2)

## 5. Estados — idem Drafts + estado "vencida" badge amber

## 6. Responsividade — 1280px

## 7. Atalhos — `N` (nova cotação), `Esc`

## 8. Component contract

```tsx
interface QuotationsPageProps {
  kpis: { total: number };
  filters: { /* idem Drafts */ };
  permissions: { view_all: boolean; view_own: boolean };
  urls: { datatable: string; back: string };
}
```

## 9. DoD

- [ ] Permission gate
- [ ] Tier 0 scope
- [ ] AJAX DataTables back-compat
- [ ] Reuso SaleSheet
- [ ] Defer customers
- [ ] Pest baseline + cross-tenant

## 10. Pegadinhas

- ❌ NÃO confundir `status='quotation'` (legacy) com `sub_status='quotation'` (canon UltimatePOS) — verificar uso real no DB
- ❌ Conversão pra venda toca FSM — usar `ExecuteStageActionService::execute()` NÃO update direto

## 11. Cutover

- Smoke biz=1 cotação nova → /sells/quotations → drawer → converter em venda
- Canary 7d
- Remover Blade após 30d

---

**Última atualização:** 2026-05-15 (Wave 1 Agent W1-A)
