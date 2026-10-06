---
owner: W
last_validated: "2026-10-06"
slug: produto-runbook-produto-etiquetas
title: "Produto — Runbook da tela Imprimir etiquetas (migração MWART, playbook thread 04)"
type: runbook
module: Produto
tela: Produto/Etiquetas/Index
status: ativo
date: 2026-10-06
---

# RUNBOOK — Imprimir etiquetas (`/labels/show`)

> **Refs:** [ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · regra mestre de valor (`memory/proibicoes.md`).
> **Origem:** Blade `labels/show` via `LabelsController@show` (+ `labels/partials/show_table_rows`, `public/js/labels.js`).
> **Alvo:** `resources/js/Pages/Produto/Etiquetas/Index.tsx`.
> **Fonte de design:** `prototipo-ui/cowork/Wagner/produto-acoes.jsx` (`Etiquetas`). Casos F1: `cowork-inbox/produto-telas-novas/Etiquetas.casos.md` (UC-ETQ-01..10).

## 1. Estado final esperado

`GET /labels/show` responde `Produto/Etiquetas/Index`; `?classico=1` segue na Blade. A folha impressa
continua saindo de `GET /labels/preview`, com os mesmos parâmetros que o formulário da Blade enviava.
A tela monta a folha; não imprime nem calcula preço.

## 2. Pré-condições

- Permissão `print_labels.access` (thread 01, #8349).
- Linha em JSON de `/labels/add-product-row` com o preço que a impressão sairia (thread 04, #8749).
- Contrato `produto-etiquetas` derivado no `_saida-07.md` do playbook.

## 3. Passo-a-passo

1. **F1 PLAN** — este runbook + charter + casos ao lado do `.tsx`.
2. **F2 BACKEND** — `show()` vira `Inertia::render`; `preview()` não muda.
3. **F3 FRONTEND** — Page com `data-contract` por seção; "Imprimir" abre `/labels/preview?…` em outra aba.
4. **F4 QA** — `tests/Feature/Produto/ProdutoEtiquetasContratoTest.php` (lane estoque-pest, tenant 98 × 99).
5. **F5 CUTOVER** — menu (thread 08) passa a apontar a tela nova; a Blade fica em `?classico=1`.

## 4. Refs

Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/04-etiquetas.md` · recibo `_saida-04.md`.

## 5. Histórico

- 2026-10-06 — criado na thread 04.
