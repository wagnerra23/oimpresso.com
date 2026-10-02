---
slug: sells-runbook-discount
title: "Sells — Runbook da tela Descontos /discount (migração MWART)"
type: runbook
module: Sells
tela: Discount/Index
status: ativo
owner: W
last_validated: "2026-10-02"
date: 2026-10-02
preconditions:
  - "Permissão discount.view (ver) ou discount.manage (gravar) — D1 [W] 2026-10-02"
  - "Migration 2026_10_02_120000 aplicada: concede as duas a quem tinha discount.access"
  - "Endpoints do resource /discount + /discount/activate/{id} + /discount/mass-deactivate preservados"
steps:
  - "DiscountController@index ganha branch X-Inertia → Inertia::render('Discount/Index'), ANTES do ramo ajax"
  - "view('discount.index') segue como fallback (cutover F5 é humano)"
  - "Lista e opções do drawer vêm em Inertia::defer, escopadas por business_id"
  - "Criar/editar abre drawer PT-02 e grava pelos POST/PUT existentes (SalvarDescontoRequest)"
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/venda-blade.jsx (TelaDescontos)"
  derived_from: "Sells/Drafts (lista dual) + SalesOrder/Index (drawer PT-02)"
  alvo_medido: "governance/design/targets/vendas--descontos--index.secoes.json"
---

# RUNBOOK — Descontos (`/discount`)

> **Tipo:** runbook MWART (Blade → Inertia/React) · thread 04 do playbook `venda-menu`.
> **Refs:** ADR 0104 (MWART), ADR 0093 (multi-tenant), ADR 0358 (tenant 98 em teste),
> decisão D1 de [W] em 2026-10-02 (ver × editar).
> **Estado origem:** Blade `discount.index` + modais `discount.create`/`discount.edit`, linhas
> pelo DataTable (`DiscountController@index`, ramo `ajax`).
> **Estado alvo:** `resources/js/Pages/Discount/Index.tsx` (PT-01 Lista + drawer PT-02 de cadastro).

## 1. Objetivo

Trocar a casca Blade da lista de descontos pela Page React sem rota nova e sem mudar o que é
gravado. Desconto é valor (o PDV aplica sozinho): a tela só cadastra — cálculo e aplicação na
venda ficam onde estão.

## 2. Pré-condições

- `discount.view` para ver a lista; `discount.manage` para criar, editar, desativar, reativar e
  excluir (PR 1 da thread, backend). Sem `manage` os botões aparecem **desabilitados com o motivo**.
- Escopo `business_id` da sessão em toda consulta (Tier 0).

## 3. Passo a passo

1. `DiscountController@index`: depois da trava, se `X-Inertia` → `Inertia::render('Discount/Index')`.
   O teste vem **antes** do `ajax()`, porque o Inertia manda `X-Requested-With` junto.
2. Props: `descontos` e `opcoes` (categorias, marcas, locais, grupos de preço) em `Inertia::defer`;
   `formatoData` (formato do negócio, lido pelo `uf_date` na gravação); `permissoes.editar`; `urls`.
3. Drawer "Adicionar/Editar desconto": envia `application/x-www-form-urlencoded` igual ao Blade —
   checkbox desmarcado **não vai** no corpo (o servidor lê `has()`), datas no formato do negócio,
   valor normalizado com ponto decimal. Editar = `POST` com `_method=PUT`.
4. Excluir (`DELETE`), reativar (`GET /discount/activate/{id}`) e desativar selecionados
   (`POST /discount/mass-deactivate`) usam os endpoints de hoje; depois a lista recarrega o
   prop `descontos`.

## 4. Verificação

- Pest: `tests/Feature/Sells/DescontosContratoTest.php` (lane `sells-pest.yml`).
- `node scripts/design/ds-guard.mjs resources/js/Pages/Discount/Index.tsx`.

## 5. Fora deste runbook

- Validação no servidor (achado A2) — decisão [W]; o FormRequest não valida de propósito.
- Cutover F5 (remover o Blade) — humano, com aviso ao cliente.
