---
last_validated: "2026-10-13"
slug: runbook-kb-index
title: "RUNBOOK — /kb (KB · Knowledge Base, lista)"
type: runbook
module: KB
page: /kb
component: resources/js/Pages/kb/Index.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — /kb (KB · Knowledge Base, lista)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 01 do playbook Telas soltas (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter e casos ao lado da Page (`resources/js/Pages/kb/Index.charter.md` · `resources/js/Pages/kb/Index.casos.md`).

## 1. Estrutura

- Controller: `Modules/KB/Http/Controllers/KbController.php` (`Inertia::render('kb/Index')`).
- Props: `kpis`, `nodes` (lista paginada) e filtros; o preview do documento abre ao lado da lista.
- `kb/Index.v2.tsx` é outra tela (charter próprio) e não entra aqui.

## 2. Âncoras de contrato

| âncora | elemento | seção do alvo |
|---|---|---|
| `header` | `<div>` em volta do `PageHeader` | `header` |
| `stats` | `KpiGrid` (repassa o atributo ao `<div>`) | `stats` |
| `tri` | área lista + preview | `tri` |

Contrato: `governance/design/contracts/kb.contract.json`, derivado do alvo
`governance/design/targets/soltas--kb--index.alvo.json`. Rodar: `node scripts/contrato-de-tela.mjs --contract <arquivo>`.
As âncoras são só atributos: não mudam dado, handler, copy nem classe. O `PageHeader` de `Components/shared` não repassa atributos ao DOM; por isso a âncora `header` fica num `<div>` em volta dele, sem classe.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

O escopo é do controller; a Page só mostra o que recebe. Soft-delete LGPD passa pelo `mcp_audit_log`.

## 4. O que esta tela NÃO faz

- Não tem as abas móveis (`kb-mobile-tabs`) do protótipo: seção `abas_mobile` do alvo fica fora do contrato.
- Não há copy literal comum entre protótipo e tela; o contrato trava só âncoras e ordem.
