---
id: resources-js-pages-purchasereturn-index-charter
page: /purchase-return
component: resources/js/Pages/PurchaseReturn/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/compras-extras.jsx
related_us: [US-MWART-008]
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Compras
related_adrs: [66, 93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/compras-extras.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [DevolucoesCompra]
  divergence_from_blueprint: "sem total devolvido nem total devido (somar valor é decisão [W]); aviso sem 'nunca avulsa' (a devolução combinada existe); editar, pagar e excluir na tela clássica"
related_runbook: memory/requisitos/Compras/_telas/RUNBOOK-purchase-return-index.md
---

# Page Charter — /purchase-return (DRAFT)

> **Status:** draft. Thread `compras/playbook/08` (Devoluções de compra). `GET /purchase-return` responde Inertia só com
> a flag `useV2ComprasDevolucoes` ligada para o negócio; desligada, ou com `?legacy=1`, a Blade `purchase_return/index`
> segue. Vira `live` no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).
> Âncora de design: `compras-extras.jsx` → `telaDevs()` (rota `cmp-devolucoes`), alvo medido em
> `governance/design/targets/compras--devolucoes--index.alvo.json` (thread A2).
> **Tela de valor** (regra mestre): os números vêm do servidor, já formatados; a tela não calcula nem soma.

## Mission

Mostrar ao comprador as devoluções de compra do negócio — de qual compra vieram, de quem, quanto valem e quanto ainda é
devido — com os mesmos números que a Blade mostra.

## Goals

- Lista das devoluções (data, nº de referência, compra de origem, local, fornecedor, status do pagamento, total, devido)
  com o filtro de local da Blade.
- O mesmo SQL da Blade (`PurchaseReturnController::consultaDevolucoes`) e o devido pela mesma conta da coluna
  `payment_due` (`final_total − pago`), formatados pelo `num_f`.
- Contagem das devoluções por status de pagamento (contagem, não soma).

## Non-Goals

- ❌ Calcular, somar ou formatar valor no navegador (regra mestre). Total devolvido e total devido (rodapé da Blade e
  KPIs do protótipo) só com dupla prova e aprovação [W].
- ❌ Editar, registrar pagamento, ver pagamentos ou excluir nesta tela antes de PR próprio — seguem na tela clássica.
- ❌ Dizer que a devolução "nunca é avulsa": a devolução combinada (sem compra de origem) existe no sistema.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só devoluções do negócio da sessão | `PurchaseReturnController::consultaDevolucoes` |
| R2 | Com a flag desligada, ou com `?legacy=1`, a rota devolve a Blade | `PurchaseReturnController::index` (`FLAG_V2`) |
| R3 | Sem `access_all_locations`, só devoluções dos locais permitidos | `consultaDevolucoes` (`permitted_locations`) |
| R4 | Devido = `final_total − SUM(pagamentos)`, pago nulo vale zero — a mesma conta da DataTable | `devolucoesDaTela` |
| R5 | Valor chega formatado pelo `num_f`; o navegador só mostra | `devolucoesDaTela` |

## Refs

RUNBOOK: [`RUNBOOK-purchase-return-index.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-return-index.md) · paridade: [`purchase-return-parity.md`](../../../../memory/requisitos/Compras/_telas/purchase-return-parity.md) · ADR 0066 · ADR 0093 · ADR 0104 · ADR 0358.
