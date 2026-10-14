---
last_validated: "2026-10-13"
slug: runbook-perfil
title: "RUNBOOK — /perfil (Meu perfil)"
type: runbook
module: User
page: /perfil
component: resources/js/Pages/User/Perfil.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — /perfil (Meu perfil)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 01 do playbook Telas soltas (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter e casos ao lado da Page (`resources/js/Pages/User/Perfil.charter.md` · `resources/js/Pages/User/Perfil.casos.md`).

## 1. Estrutura

- Controller: `app/Http/Controllers/UserController.php` (`Inertia::render('User/Perfil')`).
- Abas locais (Conta · Mais informações · Dados bancários · Segurança) num `<nav>` passado ao `PageHeader` pelo slot `subnav`.
- Grava por `POST /perfil/update` e `POST /perfil/password`.

## 2. Âncoras de contrato

| âncora | elemento | seção do alvo |
|---|---|---|
| `header` | `<div>` em volta do `PageHeader` | `header` |
| `tabs` | `<nav aria-label="Seções do perfil">` | `tabs` |
| `corpo` | `<div className="p-6 space-y-6">` | `corpo` |

Contrato: `governance/design/contracts/perfil.contract.json`, derivado do alvo
`governance/design/targets/soltas--perfil--index.alvo.json`. Rodar: `node scripts/contrato-de-tela.mjs --contract <arquivo>`.
As âncoras são só atributos: não mudam dado, handler, copy nem classe. O `PageHeader` canônico não repassa atributos ao DOM; a âncora `header` fica num `<div>` em volta dele, sem classe. A `tabs` é declarada no código antes do header (vai pelo slot `subnav`), então fica fora da `ordem` do contrato.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

A tela edita só o próprio usuário logado; o escopo é do controller.

## 4. O que esta tela NÃO faz

- A rota `prefs` (Preferências) é outra tela; o alvo é a rota `perfil` (ressalva 1 do A1).
