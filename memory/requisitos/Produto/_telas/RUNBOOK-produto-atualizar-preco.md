---
owner: W
last_validated: "2026-10-01"
slug: produto-runbook-produto-atualizar-preco
title: "Produto — Runbook da tela Atualizar preço por planilha (migração MWART, playbook thread 06)"
type: runbook
module: Produto
tela: Produto/AtualizarPreco/Index
status: ativo
date: 2026-10-01
---

# RUNBOOK — Atualizar preço por planilha (`/update-product-price`)

> **Refs:** [ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · regra mestre de valor (`memory/proibicoes.md`).
> **Origem:** Blade `selling_price_group/update_product_price` via `SellingPriceGroupController@updateProductPrice`.
> **Alvo:** `resources/js/Pages/Produto/AtualizarPreco/Index.tsx`.
> **Fonte de design:** `prototipo-ui/cowork/Wagner/produto-acoes.jsx` (`AtualizarPreco`). Casos propostos: `cowork-inbox/produto-telas-novas/Importacao.casos.md` (UC-PRC-01..04).

## 1. Estado final esperado

`GET /update-product-price` responde `Produto/AtualizarPreco/Index`; `?classico=1` segue na Blade.
Exportar (`GET /export-product-price`) e aplicar (`POST /import-product-price`) são as MESMAS rotas e o
MESMO código de antes. A conferência é o próprio `import()` rodado dentro de uma transação desfeita.

## 2. Pré-condições

- Permissão `product.update` (a mesma que a Blade já exigia).
- Contrato `produto-atualizar-preco` derivado no `_saida-07.md` do playbook.

## 3. Passo-a-passo

1. **F1 PLAN** — este runbook + charter + casos ao lado do `.tsx`.
2. **F2 BACKEND** — `updateProductPrice()` vira `Inertia::render`; `import()` ganha só um desvio no topo
   (`conferir=1` → `conferirPlanilha()`), o corpo dele não muda. Parse, arredondamento e gravação: zero mudança.
3. **F3 FRONTEND** — Page com `data-contract` por seção; o arquivo sobe como upload, o navegador não lê preço.
4. **F4 QA** — `tests/Feature/Produto/ProdutoAtualizarPrecoContratoTest.php` (lane estoque-pest, tenant 98 × 99):
   caminho antigo × novo gravam igual; conferência não grava.
5. **F5 CUTOVER** — menu (thread 08) passa a apontar a tela nova; a Blade fica em `?classico=1`.

## 4. Testes

`ProdutoAtualizarPrecoContratoTest` cobre UC-PATPRC-01..05 do `Index.casos.md`.

## 5. Refs

Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/08-atualizar-preco.md` · recibo `_saida-06.md`.

## 6. Histórico

- 2026-10-01 — criado na thread 06.
