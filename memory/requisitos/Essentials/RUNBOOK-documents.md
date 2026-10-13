---
last_validated: "2026-10-13"
slug: runbook-documents
title: "RUNBOOK — /essentials/document (Documentos e Memorandos)"
type: runbook
module: Essentials
page: /essentials/document
component: resources/js/Pages/Essentials/Documents/Index.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — /essentials/document (Documentos e Memorandos)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 03 do playbook Essenciais (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter: [`Index.charter.md`](../../../resources/js/Pages/Essentials/Documents/Index.charter.md) · casos: [`Index.casos.md`](../../../resources/js/Pages/Essentials/Documents/Index.casos.md).

## 1. Estrutura

- Controller: `Modules/Essentials/Http/Controllers/DocumentController.php` (`Inertia::render` no `index()`).
- Props: `documents` e `memos`, adiadas (`Inertia::defer`); a Page alterna as duas listas em abas.

## 2. Âncoras de contrato

| âncora | elemento | seção do alvo |
|---|---|---|
| `cabecalho` | `<header>` (h1, subtítulo, Adicionar) | `titulo` + `subtitulo` |
| `tabela` | wrapper da tabela | `tabela` |
| `vazio` | estado vazio | — |

Contrato(s): `governance/design/contracts/essenciais-documentos.contract.json` e `essenciais-memorandos.contract.json` (as duas abas, mesmas âncoras). Rodar: `node scripts/contrato-de-tela.mjs --contract <arquivo>`.
As âncoras são só atributos: não mudam dado, handler, copy nem classe.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

O escopo por `business_id` é do controller; a Page só mostra o que recebe. Qualquer mudança no `index()` passa
pelo teste de contrato da tela (`Index.casos.md`).

## 4. O que esta tela NÃO faz

- Não tem a casca do módulo do protótipo (cabeçalho "Essenciais" + abas do módulo): cada tela do Essentials é uma rota.
- Compartilhar e excluir só para o item próprio (`is_mine`), decidido no servidor.
