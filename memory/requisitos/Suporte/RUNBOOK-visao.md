---
last_validated: "2026-10-13"
slug: runbook-visao
title: "RUNBOOK — /suporte/empresas/{id} (Suporte · visão da empresa)"
type: runbook
module: Suporte
page: /suporte/empresas/{id}
component: resources/js/Pages/Suporte/Visao.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — /suporte/empresas/{id} (Suporte · visão da empresa)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 01 do playbook Telas soltas (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter e casos ao lado da Page (`resources/js/Pages/Suporte/Visao.charter.md` · `resources/js/Pages/Suporte/Visao.casos.md`).

## 1. Estrutura

- Controller: `app/Http/Controllers/Support/SupportController.php` (`Inertia::render('Suporte/Visao')`).
- Props: `empresa`, `contagens`, `usuarios`. "Acessar como" chama a rota de impersonação auditada.

## 2. Âncoras de contrato

| âncora | elemento | seção do alvo |
|---|---|---|
| `navrow` | link de volta | `navrow` |
| `faixa` | aviso do Modo Suporte | `faixa` |
| `cabecalho` | nome da empresa | `bloco_3` (posicional no alvo) |
| `cards` | contagens | `cards` |
| `titulo-lista` | título + busca | `titulo_lista` |
| `lista` | tabela de usuários | `lista` |
| `rodape` | nota de auditoria | `rodape` |

Contrato: `governance/design/contracts/suporte-visao.contract.json`, derivado do alvo
`governance/design/targets/soltas--suporte-visao--index.alvo.json`. Rodar: `node scripts/contrato-de-tela.mjs --contract <arquivo>`.
As âncoras são só atributos: não mudam dado, handler, copy nem classe. Os primitivos `Inline`/`Grid` repassam o atributo ao elemento.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

A empresa-cliente vem do controller pelo Modo Suporte (`EnsureSupportAccess`); cada "Acessar como" grava em `support_access_logs` (append-only).

## 4. O que esta tela NÃO faz

- O log de acessos é a tela `Suporte/Log` (RUNBOOK-log.md).
