---
owner: W
last_validated: "2026-10-01"
slug: produto-runbook-produto-cadastros
title: "Produto — Runbook da tela Cadastros de apoio (migração MWART, playbook thread 02)"
type: runbook
module: Produto
tela: Produto/Cadastros/Index
status: ativo
date: 2026-10-01
---

# RUNBOOK — Cadastros de apoio (`/units` · abas)

> **Refs:** [ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Origem:** Blades `unit/index` e `brand/index` (modais + DataTables) via `UnitController@index` e `BrandController@index`.
> **Alvo:** `resources/js/Pages/Produto/Cadastros/Index.tsx` (uma tela, 6 abas — decisão [W] D3 2026-10-01: Page parametrizada).
> **Fonte de design:** `prototipo-ui/cowork/Wagner/produto-cadastros.jsx` (`ProdutoCadastros`). Trio proposto: `cowork-inbox/produto-telas-novas/Cadastros.*`.

## 1. Estado final esperado

`GET /units` (visita do browser ou `X-Inertia`) responde `Produto/Cadastros/Index` com as abas
Unidades e Marcas vivas; o ajax do DataTables (sem `X-Inertia`) e `?classico=1` seguem na Blade,
que hospeda os modais de criar/editar. As outras 4 abas abrem a tela atual de cada uma.

## 2. Pré-condições

- Permissões `unit.*` e `brand.*` existem (legado). `variation.*`/`warranty.*` vieram da thread 01.
- Contrato `produto-cadastros` derivado no `_saida-07.md` do playbook.

## 3. Passo-a-passo

1. **F1 PLAN** — este runbook + charter + casos ao lado do `.tsx`.
2. **F2 BACKEND** — `UnitController@index` desvia por `X-Inertia` (não por `ajax()` sozinho, §5 2026-09-08); `cadastros()` monta `can` por aba e as listas escopadas por `business_id`, com `em_uso` por subconsulta em `products`. `BrandController@destroy` passa a recusar marca em uso (charter R4).
3. **F3 FRONTEND** — Page com `data-contract` por seção do contrato.
4. **F4 QA** — `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` (lane estoque-pest, tenant 98 × 99).
5. **F5 CUTOVER** — menu (thread 08) passa a apontar `/units`; a Blade fica em `?classico=1`.

## 4. Testes

`ProdutoCadastrosContratoTest` cobre UC-PCADAP-01..08 do `Index.casos.md`.

## 5. Refs

Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/05-cadastros.md` · recibo `_saida-02.md`.

## 6. Histórico

- 2026-10-01 — criado na thread 02 (Unidades + Marcas). Categorias e create/edit em modal ficam pendentes no `_saida-02`.
