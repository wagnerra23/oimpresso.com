---
owner: W
last_validated: "2026-10-01"
slug: produto-runbook-produto-importacao
title: "Produto — Runbook da tela Importação (migração MWART, playbook thread 05)"
type: runbook
module: Produto
tela: Produto/Importacao/Index
status: ativo
date: 2026-10-01
---

# RUNBOOK — Importação de produtos (`/import-products`)

> **Refs:** [ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md), regra mestre VALOR/ESTOQUE (`memory/proibicoes.md`).
> **Origem:** Blade `import_products/index` via `ImportProductsController@index`.
> **Alvo:** `resources/js/Pages/Produto/Importacao/Index.tsx` (uma Page, dois modos: produtos nesta onda; estoque inicial na próxima).
> **Fonte de design:** `prototipo-ui/cowork/Wagner/produto-acoes.jsx` (`TelaImportar`/`ImportarProdutos`). Trio proposto: `cowork-inbox/produto-telas-novas/Importacao.*`.

## 1. Estado final esperado

`GET /import-products` responde `Produto/Importacao/Index`; `?classico=1` mantém a Blade.
Antes de gravar, **Conferir planilha** roda a mesma validação do `store()` e desfaz tudo
(dry-run): a tela mostra uma linha por produto que seria criado, ou a linha do primeiro erro.
**Enviar planilha** só libera depois de uma conferência sem erro do mesmo arquivo.

## 2. Pré-condições

- Permissão `product.create` (já exigida; D2 [W] 2026-10-01: importação continua com ela).
- Contrato `produto-importacao` derivado no `_saida-07.md` do playbook.

## 3. Passo-a-passo

1. **F1 PLAN** — este runbook + charter + casos ao lado do `.tsx`.
2. **F2 BACKEND** — `index()` vira Inertia; `store()` ganha o desvio `conferir=1` **depois** da validação e **antes** de criar produto/estoque (`DB::rollBack()` + redirect com `conferencia`). Nenhuma linha do cálculo nem da gravação muda. Com `conferir`, imagem por URL não é baixada.
3. **F3 FRONTEND** — Page com `data-contract` por seção do contrato.
4. **F4 QA** — `tests/Feature/Produto/ProdutoImportacaoContratoTest.php` (lane estoque-pest, tenant 98 × 99): a mesma planilha pelo caminho antigo e pelo novo grava o mesmo resultado.
5. **F5 CUTOVER** — menu (thread 08) passa a apontar a tela; a Blade fica em `?classico=1`.

## 4. Testes

`ProdutoImportacaoContratoTest` cobre os UC-PIMP do `Index.casos.md`.

## 5. Refs

Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/07-importacao.md` · recibo `_saida-05.md`.

## 6. Histórico

- 2026-10-01 — criado na thread 05 (modo produtos). Estoque inicial fica pendente no `_saida-05`.
- 2026-10-01 — PR-b: `/import-opening-stock` no modo estoque. A conferência roda o `store()` inteiro (validação e gravação no mesmo laço) e desfaz com `DB::rollBack()`; UC-PIMP-06..10.
