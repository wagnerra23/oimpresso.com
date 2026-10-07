---
slug: configuracoes-runbook-esquemas-fatura
title: "Configurações — Runbook da tela Esquemas de fatura"
type: runbook
module: Configuracoes
tela: Configuracoes/EsquemasFatura/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Esquemas de fatura (`/invoice-schemes`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/05`, tela 3 de 3
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Fatura()` (+ `configuracoes-fatura.jsx`), rota `cfg-fatura`.
> **Decisão D1 ([W] 2026-10-06):** Configurações com abas; a aba entra no `ConfiguracoesSubNav` pelo menu.

## Estado final esperado

`GET /invoice-schemes` (`InvoiceSchemeController::index`) responde Inertia `Configuracoes/EsquemasFatura/Index` **quando a
flag `useV2ConfiguracoesEsquemasFatura` está ligada** para o negócio; desligada, segue a Blade `invoice_scheme/index`.
A página tem as duas abas da Blade: esquemas (numeração) e layouts de fatura (lista com link para o editor de layout).

## 1. Objetivo

Trocar a Blade (DataTable + modais + aba de layouts) pela aba React, sem mudar como a numeração é gravada nem a permissão.

## 2. Pré-condições

- Permissão: `invoice_settings.access` (todas as ações, esquemas e layouts).
- Tabela `invoice_schemes` (`invoice_count` é o contador de notas emitidas, gravado pela venda, não por esta tela).
- Isolamento de editar/excluir/tornar padrão: #8979 (o mesmo furo do código de barras).

## 3. Passo-a-passo

1. **#8979:** fecha o cross-tenant de `update`/`destroy`/`setDefault` (Tier 0).
2. **F1/F2 (este PR):** RUNBOOK + [`esquemas-fatura-parity.md`](./esquemas-fatura-parity.md) + `EsquemasFaturaBaselineTest`.
3. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos.
4. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: esquemas (padrão marcado, prévia do número) · layouts (locais que usam cada um) · vazio ·
drawer novo/editar · confirmação de exclusão (padrão não exclui). `/` busca · `n` novo esquema.
`data-contract`: `page-header`, `esquemas-table`, `layouts-lista`, `esquema-form`, `confirm-excluir`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99) — isolamento no #8979, o resto neste PR
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção das Blades `invoice_scheme/*` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Numeração "aleatória" não limpa o número inicial.** `store()`/`update()` comparam `number_type == 'aleatory'`, mas a
  chave do tipo é `random` (`$this->number_types`). Resultado medido no baseline: esquema aleatório **mantém** o
  `start_number` que vier. Mudar isso mexe na numeração de nota — fica registrado, não muda aqui (decisão [W]).
- No ano (`scheme_type = year`) o prefixo exibido é `<prefixo><ano>-` (`config('constants.invoice_scheme_separator')`).
- Cadastrar como padrão desmarca o padrão anterior do negócio; o padrão não se exclui.
- O `invoice_count` não é editável aqui: é o contador que a venda incrementa.
- A aba de layouts só **lista** (com os locais que usam cada layout); criar/editar layout é o `InvoiceLayoutController`
  (fora do prefixo da thread) e segue na Blade.
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
