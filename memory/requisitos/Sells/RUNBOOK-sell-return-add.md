---
slug: sells-runbook-sell-return-add
title: "Sells — Runbook do registro de devolução /sell-return/add/{venda} (migração MWART, PR 2 de 2)"
type: runbook
module: Sells
tela: SellReturn/Add
status: ativo
owner: W
last_validated: "2026-10-05"
date: 2026-10-05
preconditions:
  - "Permissão access_sell_return OU access_own_sell_return"
  - "Escopo business_id da sessão na venda de origem (ADR 0093)"
  - "REGRA MESTRE de valor/estoque (memory/proibicoes.md): store() e cálculo intactos"
steps:
  - "Ramo X-Inertia em SellReturnController@add com 404 para venda de outro business"
  - "Page resources/js/Pages/SellReturn/Add.tsx postando o payload do form Blade no store() existente"
  - "Contrato Pest em tests/Feature/Sells/SellReturnAddContratoTest.php na lane sells-pest"
---

# RUNBOOK — Registro de devolução (`/sell-return/add/{venda}`)

> **Tipo:** runbook MWART (Blade → Inertia/React), **registro** da devolução.
> **Thread:** `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/01-telas-legadas.md` §03 — PR 2 de 2
> (o PR 1 é a lista, `RUNBOOK-sell-return-index.md`).
> **Refs:** ADR 0104 (MWART) · ADR 0093 (multi-tenant) · `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md`.

## 1. Objetivo

Registrar a devolução em React quando a visita é Inertia, **sem mudar como ela é gravada**. A
devolução move **valor** (total devolvido, saldo a pagar ao cliente) e **estoque** (volta ao
local da venda), então o caminho de escrita é o mesmo do form Blade: `POST /sell-return` →
`SellReturnController@store` → `TransactionUtil::addSellReturn`. Nenhuma linha desses dois muda.

## 2. Pré-condições

- [x] Permissão `access_sell_return` ou `access_own_sell_return` (mesma checagem do `add()`).
- [x] Venda de origem buscada com `business_id` da sessão; de outra empresa → 404 antes de
      qualquer prop.
- [x] Carga completa de página continua na Blade (`sell_return.add`), byte a byte.

## 3. Passo a passo

### 3.1 Controller — ramo Inertia no `add()`

Depois da checagem de permissão e de assinatura. Carga inicial só confere que a venda existe
no business (`exists()` barato) e devolve 404 se não; a venda com linhas vem deferida
(`Inertia::defer`, grupo `venda`).

A prop `venda` traz os campos do form **como texto no formato da empresa**, com as mesmas
expressões das diretivas da Blade (`@num_format`, `@format_quantity`, `@format_datetime`,
`num_f(..., true)`). A Page devolve esses textos ao `store()` sem reformatar — o servidor recebe
a mesma coisa pelos dois caminhos.

### 3.2 Front — `resources/js/Pages/SellReturn/Add.tsx`

PT-02 (formulário de detalhe): cabeçalho da venda de origem, tabela de linhas com quantidade
a devolver, desconto, totais e "Salvar devolução". O total mostrado antes de salvar sai de
`_components/devolucaoCalculo.ts`, espelho de `Util::num_uf` + `ProductUtil::calculateInvoiceTotal`
— o mesmo número que o servidor vai gravar. O envio é `application/x-www-form-urlencoded` com as
chaves do form Blade (`products[i][quantity]`, `products[i][unit_price_inc_tax]`,
`products[i][sell_line_id]`, `discount_type`, `discount_amount`, `tax_id`, `tax_amount`,
`transaction_id`, `invoice_no`, `transaction_date`).

### 3.3 Teste — `tests/Feature/Sells/SellReturnAddContratoTest.php`

Lane `sells-pest.yml` (allowlist no workflow). Tenant 98 × 99. A prova de valor/estoque posta o
payload da Blade numa venda e o da Page numa venda gêmea, e compara total, desconto, imposto,
`quantity_returned`, saldo de estoque e situação do pagamento — mais a conta à mão no cabeçalho.

## 4. Fora deste PR

- **Motivo da devolução** (charter R3): o `store()` não grava motivo; exigiria mudar o store → fora.
- **Aviso de item sob medida** (charter R4): não há dado no produto que diga "sob medida".
- Impressão do comprovante após salvar (a Blade chama `pos_print`); excluir devolução e
  adicionar pagamento seguem na Blade.

## 5. Rollback

Remover o `if (request()->header('X-Inertia'))` do `add()`. A Blade nunca deixou de responder
à carga completa, e o `store()` não mudou.
