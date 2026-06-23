---
slug: _PROPOSTA-0244-estrategia-teste-estado-arte
title: "Estratégia de teste estado-da-arte para telas (locators resilientes + Playwright + Storybook + casos.md), substituindo o runner DOM-grep"
type: adr
status: proposta
authority: pending
decided_by: pending-[W]
proposed_at: "2026-06-02"
module: governance
related_adrs: [0239, 0243]
authors: [claude-cowork]
supersedes: []
---

# _PROPOSTA ADR 0244 — Estratégia de teste estado-da-arte para telas

> **PROPOSTA [CC]** (numerar/ratificar = [W], ADR 0238). [CL] valida contra `main`. Evolui ADR 0243 R5 (defesa que dispara) com o eixo de teste de comportamento.

## Contexto
Os casos de uso (`casos.md`, ADR 0243 R4/R5) precisam virar teste **que roda sozinho** (CI) e **na tela sob demanda** ([W] pediu). O 1º protótipo — runner in-app com **seletor CSS** (`.prod-col`) + registry JS na mão — provou a ergonomia, mas **regrediu em silêncio** (L-24: ao generalizar, a Oficina ficou sem casos; pego por [W], não por mecanismo). Lições: seletor de classe é **frágil**, "presença" não é "correção", e in-app+CI separados **duplicam**.

## Decisão (4 peças + 1 axis)
1. **Locators resilientes** — `getByRole`/`getByText`/`data-testid`, **nunca classe CSS/estilo**. Cada componente declara seu `data-testid` (contrato de teste). Mata a quebra-em-silêncio (L-24).
2. **Playwright** — E2E em navegador real, headless no CI, auto-wait (anti-flake), trace/vídeo no fail. **É a garantia durável.**
3. **Storybook + play-functions / test-runner** — a MESMA interação roda **on-demand na tela** (UI Storybook) **e** no CI, de **fonte única**. Resolve o "rodar quando eu peço" + "roda sozinho" sem duas engrenagens.
4. **`casos.md` (BDD)** — spec durável legível por cima; cada teste rastreia o ID do UC.
   ➕ **Visual regression** (Chromatic/Percy) pro eixo "ficou certo visualmente".

## Caminho pragmático (piloto Larissa/ROTA LIVRE)
- **Fase 1 (agora):** `data-testid` nos componentes dos **UCs críticos** (Oficina UC-06 gate · Vendas UC-V05 split NF-e/NFS-e · Financeiro UC-F02 saldo) + **Playwright** nesses UCs. Já dá garantia durável.
- **Fase 2:** Storybook + play-functions → o "runner na tela" polido, mesma fonte do CI.
- **Ponte:** o `casos.md` + runner DOM-grep atuais ficam como spec/atalho até Playwright/Storybook assumirem; runner ganha **self-guard** (falha se montar com 0 casos — a classe do L-24).

## Não-objetivos
- Não substitui revisão humana nem teste de emissão fiscal real (backend).
- Não manter dois harnesses divergentes — in-app e CI **leem a mesma fonte**.

## Consequências
- **+** garantia durável (CI), resiliente a refactor (testid/role), on-demand de fonte única (Storybook), sem falsa confiança.
- **−** custo de setup (Storybook + disciplina testid + infra Playwright) — por isso o faseamento.
- Evolui 0243 R5; não supersede nada.

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-06-02 | [CC] propõe | estratégia de teste estado-da-arte; origem L-24 (regressão do runner DOM-grep) + pedido [W] "rodar na tela quando eu pedir". |
