---
slug: sells-runbook-pos
title: "Sells — Runbook da tela Lista de POS /pos (migração MWART)"
type: runbook
module: Sells
tela: Sells/Pos/Index
status: ativo
owner: W
last_validated: "2026-10-01"
date: 2026-10-01
wave: "playbook venda-menu · thread 01"
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/venda-blade.jsx (TelaPos)"
  derived_from: "Sells/Drafts (golden de lista dual Blade/Inertia)"
  divergence_from_blueprint: "Filtros de local, cliente, vendedor e tipo de serviço e a contagem por status/forma no rodapé ficam fora desta onda — o endpoint de dados ainda não os oferece."
---

# RUNBOOK — Lista de POS (`/pos`)

> **Tipo:** runbook MWART (Blade → Inertia/React)
> **Refs:** ADR 0104, ADR 0093, ADR 0358 (tenant de teste 98)
> **Estado origem:** Blade `sale_pos.index` via `SellPosController@index`, DataTable AJAX `sale_pos/partials/sales_table` contra `SellController@index?is_direct_sale=0`.
> **Estado alvo:** `resources/js/Pages/Sells/Pos/Index.tsx` (lista PT-01) alimentada por `GET /sells-list-json?is_direct_sale=0`.
> **Fonte de design:** `prototipo-ui/cowork/Wagner/venda-blade.jsx` `TelaPos` + alvo medido `governance/design/targets/vendas--pos--index.{secoes,alvo}.json`.
> **Charter e casos:** `resources/js/Pages/Sells/Pos/Index.charter.md` · `Index.casos.md` (UC-POS-01..08, textos revisados em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/ListaPos.*`).

## 1. Objetivo

Trocar o índice de vendas de balcão (`is_direct_sale = 0`) por uma Page React, mantendo o Blade como fallback do GET comum. O rodapé de totais (total · pago · em aberto) passa a vir do servidor, sobre o filtro inteiro, em vez de ser somado pelo DataTable no cliente.

## 2. Pré-condições

- [ ] Permissão `sell.view` OU `sell.create` (o mesmo gate do `SellPosController@index` legado).
- [ ] `SellController@inertiaList` aceita `is_direct_sale` (whitelist 0/1, default inalterado).
- [ ] Multi-tenant: `transactions.business_id` da sessão (Tier 0, ADR 0093).

## 3. Passo-a-passo

1. **Endpoint de dados (estender o dono, sem rota nova).** `SellController@inertiaList` ganha o parâmetro opcional `is_direct_sale`. Com `0`: filtra `transactions.is_direct_sale = 0`, aceita `sell.view`/`sell.create` como gate e aplica `permitted_locations()` como o legado. Sem o parâmetro, nada muda.
2. **Branch dual no controller.** `SellPosController@index`: com `X-Inertia`, `Inertia::render('Sells/Pos/Index', { permissions, urls })` — sem query cara (as linhas vêm do endpoint). Sem o header, o `view('sale_pos.index')` de sempre.
3. **Page.** `Sells/Pos/Index.tsx` busca `/sells-list-json?is_direct_sale=0` com filtro de status de pagamento, período e busca; mostra a tabela, a linha vencida com trilho de urgência e o rodapé com `totals` do servidor.
4. **Ações por linha.** Ver detalhe (drawer `SaleSheet` do Sells/Index) · Editar (`/pos/{id}/edit`, com `sell.update`) · Imprimir recibo (`/sells/{id}/print`) · Adicionar pagamento (só com saldo e permissão de pagamento; abre o drawer) · Devolver venda (`/sell-return/add/{id}`) · Excluir (`DELETE /pos/{id}` com `sell.delete`; sem ela o item fica desabilitado dizendo o motivo).

## 4. Verificação

- Teste de contrato `tests/Feature/Sells/SellsPosIndexContratoTest.php` na lane `sells-pest.yml` (MySQL, tenant 98 × 99): render Inertia com `X-Inertia` + `X-Requested-With`, isolamento entre empresas, filtro `is_direct_sale`, totais do rodapé, filtro vencido e período, permissão.
- `npx tsc --noEmit` e `node scripts/design/ds-guard.mjs` nos arquivos tocados.

## 5. Fora desta onda

- Filtros de local, cliente, vendedor e tipo de serviço (o endpoint ainda não filtra por eles).
- Contagem por status de pagamento e por forma no rodapé (R2, segunda metade).
- Restrições de status por papel do legado (`view_paid_sells_only` etc.), que o `inertiaList` não aplica em modo nenhum.
- Cutover F5 (o GET comum continua no Blade) — decisão humana.
