---
last_validated: "2026-10-13"
slug: runbook-knowledge
title: "RUNBOOK — /essentials/knowledge-base (Base de conhecimento)"
type: runbook
module: Essentials
page: /essentials/knowledge-base
component: resources/js/Pages/Essentials/Knowledge/Index.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — /essentials/knowledge-base (Base de conhecimento)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 03 do playbook Essenciais (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter: [`Index.charter.md`](../../../resources/js/Pages/Essentials/Knowledge/Index.charter.md) · casos: [`Index.casos.md`](../../../resources/js/Pages/Essentials/Knowledge/Index.casos.md).

## 1. Estrutura

- Controller: `Modules/Essentials/Http/Controllers/KnowledgeBaseController.php` (`Inertia::render` no `index()`).
- Props: `books` (livros com seções), adiada; o `content` vem sanitizado pelo controller (`HtmlSanitizer::clean`).

## 2. Âncoras de contrato

| âncora | elemento | seção do alvo |
|---|---|---|
| `busca` | card de busca | `toolbar` |
| `navegacao` | grade de livros → seções | `navegacao` |
| `vazio` | busca sem resultado | — |

Contrato(s): `governance/design/contracts/essenciais-conhecimento.contract.json`. Rodar: `node scripts/contrato-de-tela.mjs --contract <arquivo>`.
As âncoras são só atributos: não mudam dado, handler, copy nem classe.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

O escopo por `business_id` é do controller; a Page só mostra o que recebe. Qualquer mudança no `index()` passa
pelo teste de contrato da tela (`Index.casos.md`).

## 4. O que esta tela NÃO faz

- A leitura do artigo (seção `artigo` do alvo) é a rota Show, não a Index.
- O protótipo fala em "categoria"; a tela usa livro → seção → artigo (divergência da `_saida-00` do playbook, decisão [W]).
