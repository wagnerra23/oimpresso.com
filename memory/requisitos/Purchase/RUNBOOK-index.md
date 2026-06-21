---
title: "RUNBOOK — /purchases (Compras · listagem Inertia)"
module: Purchase
tela: Purchase/Index
owner: F
status: ativo
last_validated: "2026-06-17"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — `/purchases` (listagem de Compras, Inertia/React)

> Tela `resources/js/Pages/Purchase/Index.tsx` servida em `/purchases`.
> Migração MWART parcial: o controller mantém **dual-path** Blade legacy + Inertia.
> NÃO confundir com o módulo greenfield `/compras` (`Pages/Compras/Index.tsx`,
> `memory/requisitos/Compras/`) — é outro alvo, ainda não no ar.

## 1. Dual-path (a pegadinha central)

`PurchaseController@index` decide o que renderizar:

| Condição do request | Render |
|---|---|
| header `X-Inertia: true` OU `?v=2` | `indexInertia()` → `Inertia::render('Purchase/Index')` (React) |
| `request()->ajax()` (datatable) | JSON Datatables (Blade legacy) |
| GET normal sem nada | `view('purchase.index')` (Blade legacy) |

A navegação pelo menu do Cockpit (Inertia `router.visit`) cai no path React —
é o que o cliente enxerga. O Blade legacy só aparece em acesso direto fora do SPA.

**Consequência:** toda ação inline da tela precisa existir nos DOIS lados.
Regressão clássica: ação existe no dropdown Blade e não foi portada pro React.

## 2. Ações inline por compra (paridade Blade × React)

| Ação | Blade (dropdown) | React (botões) |
|---|---|---|
| Ver | ✅ `purchase.view` | ✅ `permissions.view` |
| Imprimir (nota) | ✅ `purchase.view` | ✅ `permissions.view` |
| **Etiquetas / código de barras** | ✅ sempre (`LabelsController@show?purchase_id=`) | ✅ sempre (`window.open('/labels/show?purchase_id=…')`) — restaurado 2026-06-17 |
| Editar | ✅ `purchase.update` | ✅ `permissions.update` |
| Excluir | ✅ `purchase.delete` | ✅ `permissions.delete` |
| Pagamento / devolução / status / e-mail | ✅ | ⚠️ ainda só Blade (gap conhecido) |

> Histórico: a ação **Etiquetas** existia no Blade (`PurchaseController.php:142`,
> incondicional) e não foi portada na migração React. Cliente ROTA LIVRE (biz=4)
> reportou via WhatsApp 2026-06-17 — "cadastrei umas peças e não tem opção de
> imprimir as etiquetas das compras". Fix = botão `Barcode` →
> `/labels/show?purchase_id={id}` (paridade exata com o Blade).

## 3. Quando esta tela quebra (sintomas)

- `/purchases` → "All Inertia requests must receive a valid Inertia response":
  alguma ação chamou `router.visit` pra rota **Blade** (ex: `/labels/show`).
  Rotas Blade abrem via `window.open` / `window.location`, NÃO `router.visit`.
- 403 → user sem `purchase.view` / `purchase.create` / `view_own_purchase`.
- Lista vazia → conferir `TransactionUtil::getListPurchases($business_id)` +
  `permitted_locations`.
- Etiqueta abre tela vazia → `LabelsController@show` recebeu `purchase_id`
  inexistente ou de outro business (scope `getPurchaseProducts($business_id, $purchase_id)`).

## 4. Smoke prod (R1 — evidência curl, não narração)

```bash
curl -sv https://oimpresso.com/purchases 2>&1 | grep '^< HTTP'              # 200/302
curl -sv "https://oimpresso.com/labels/show?purchase_id=1" 2>&1 | grep '^< HTTP'
# Regression adjacente:
curl -sv https://oimpresso.com/sells 2>&1 | grep '^< HTTP'
```

Chrome MCP obrigatório pós-deploy (hook `post-merge-ui-smoke-required.ps1`):
navegar `/purchases` (1280px = monitor Larissa), abrir as ações de uma compra,
conferir o botão **Etiquetas** e que ele abre `/labels/show?purchase_id=…`.

## 5. Tier 0 — invariantes
- ❌ Multi-tenant leak — `/labels/show` só pode listar produtos do `business_id` da sessão.
- ❌ `router.visit` pra rota Blade (`/labels/show`, `/purchases/print/…`) — usar `window.open`.
- ✅ Ação inline nova: portar nos dois paths OU registrar o gap na tabela §2.

## 6. Refs
- Controller: `app/Http/Controllers/PurchaseController.php` (`index` + `indexInertia`)
- Tela: `resources/js/Pages/Purchase/Index.tsx`
- Etiquetas: `app/Http/Controllers/LabelsController.php@show` · rota `routes/web.php` → `/labels/show`
- [ADR 0104 MWART](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093 Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
