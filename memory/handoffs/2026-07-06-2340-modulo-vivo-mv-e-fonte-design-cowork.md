---
date: "2026-07-06"
time: "23:40"
slug: modulo-vivo-mv-e-fonte-design-cowork
tldr: "Mega-sessão ~11 PRs: stream Módulo Vivo (MV1 vital-signs + MV2 metabolismo nightly + batch #1 5 telas + MV3 readiness) · adversário ligou a catraca-de-nota órfã e achou charter Tier-0 errado · re-grade cego provou inflação Onda 1 · fonte de design Cowork resolvida (2 projetos lidos ao vivo por DesignSync, INDEX §0.2). Fio ativo em 3 chips; pendência Wagner: protótipo antigo=redesenhar?"
prs: [3856, 3857, 3858, 3860, 3861, 3862, 3863, 3864, 3872, 3875]
related_adrs: [0299, 0264, 0314, 0256]
---

# Handoff 2026-07-06 23:40 — Módulo Vivo (MV1-3) + fonte de design Cowork resolvida

## O que fechou nesta sessão (mega-sessão, ~11 PRs)

**Stream MV — Módulo Vivo (o software se cuida por módulo):**
- **MV1** [#3856] — espinha dorsal `scripts/qa/vital-signs.mjs` (sinais vitais da frota: pior-tela-puxa, frescor que degrada, fila de prioridade). Snapshot `memory/governance/vital-signs.json`.
- **MV2** [#3857] — metabolismo `scripts/qa/mv-metabolismo.mjs` + workflow nightly 06:30 BRT (auto-PR `mv-batch` SEM auto-merge = gate humano Wagner). Batch #1 aprovado.
- **Batch #1 executado** [#3858 Impostos, #3860 restante] — 5 telas blindadas (casos.md + teste de contrato + scorecard): Impostos 78, ProvaViva 74, DsRollout 74, Jana/Pro 79, kb/Index.v2 74.
- **MV3** [#3863] — `scripts/qa/prototipo-readiness.mjs` (quais telas de protótipo estão prontas pra aplicar) + **regra de precedência** em proibicoes.md (teste>casos>charter>SPEC). [#3864] boost de protótipo no metabolismo.

**Adversário + auto-correção** [#3861] — 5 achados alta: catraca de nota LIGADA no CI (era órfã), charter Fiscal/Cockpit Tier-0 corrigido (mandava vazamento cross-tenant), watchdog do metabolismo (memory-health Check Y), relógio dos UC 🧪/⬜ (Check Z), batch-que-mente detectado. + re-grade cego 10 telas provou inflação Onda 1 (média −13.7) e achou bug real (Ponto/Dashboard defer, corrigido em sessão paralela #3862).

**Âncora de design** [#3872] — sentinela `anchor-content-check.mjs` (2/9 âncoras podres pegas: Unificado→shell, Fluxo→arquivo-fantasma). Smoke real prod do Fluxo (biz=1) pra fechar charter-live-signal.

**Fonte de design Cowork resolvida** [#3875 — este handoff] — ver abaixo (o fio ativo).

## FIO ATIVO (continua via chips)

Trabalhando na tela Financeiro/Unificado, Wagner desconfiou da âncora. Descoberto e registrado em [INDEX-DESIGN-MEMORIAS §0.2](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) + [session](../sessions/2026-07-06-fonte-design-cowork-dois-projetos.md):
- Fonte de design = **Cowork** (não Figma), lido AO VIVO por `DesignSync` (autorizada via `/design-login` no terminal CLI).
- **2 projetos:** `019dcfd3` "Oimpresso ERP Conunicação Visual." (fonte das telas) · `019dd02f` "Office Impresso — Design System" (só o DS).
- Âncora do Unificado (`financeiro-page.jsx`) está CERTA — repo é md5-idêntico ao Cowork vivo. Nada a reverter/deletar.
- **PENDÊNCIA Wagner:** "esse protótipo é antigo" ≠ arquivo defasado (refutado por md5). Confirmar se = quer REDESENHAR a direção da tela (Visão unificada) OU só melhorar a toolbar.

**Chips abertos:** (1) redesenhar/aplicar Unificado do Cowork vivo · (2) triagem dos 23 designs órfãos + normalizar 3 âncoras-prosa · (3) evoluir anchor-content-check pra validar Cowork ao vivo.

## Estado MCP no momento do fechamento

MCP não consultado nesta sessão (trabalho foi via git/PRs diretos + integração DesignSync). Tasks vivas devem ser reconciliadas no início da próxima via `cycles-active`/`my-work`. Trabalho registrado em PRs #3856-3875 (git = canon).

## Pra retomar

Comece pelo chip (1) se Wagner quiser seguir o design. `git fetch origin main` (esta sessão trabalhou de origin/main fresco). A integração DesignSync já está autorizada na máquina.
