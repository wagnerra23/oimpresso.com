---
date: "2026-06-16"
time: "2111 BRT"
slug: "handoff-integrity-gate"
tldr: "Prompt Cowork 2 tarefas. TAREFA 1 (headers os-page-h inline -> PageHeader) PULADA: premissa stale — os-page-h e CSS canon (nao alvo), Dre/ContasPagar ja migrados (ref morta), Unificado/Dashboard charter-protegidos; [W] confirmou. TAREFA 2 FEITA: gate de integridade do handoff (fila COWORK_NOTES <-> PROMPT_PARA_CODE) em 2 PRs — #2864 regra (PROCESSO_MEMORIA_CC §16 + IT8) + #2865 catraca (guard + auto-teste + baseline 0/0 + workflow advisory + censo). CI verde, NAO mergeado (aguarda [W])."
decided_by: [W]
cycle: "CYCLE-08"
prs: [2864, 2865]
us: []
next_steps:
  - "Mergear #2865 (gate) ANTES de #2864 (regra) — senao o §16 referencia handoff:check/baseline que ainda nao estao no main (ref morta, justo o que a regra proibe). Ou mergear os dois juntos."
  - "Se a §16 virar ADR formal = Tier 0 = numero é [W] (nao cunhei)."
  - "TAREFA 1: nada a fazer (premissa stale confirmada). Se um dia migrar headers Financeiro, resolver primeiro o split de canon os-page-h (cowork-bundle Tier 0) vs <PageHeader> v3.8 via ADR."
---

## Estado MCP no momento

- **Cycle:** CYCLE-08 (Receita — Onda A), 12d restantes, 57% decorrido. Este trabalho é **off-cycle** (governança de processo do loop Cowork↔Code) — coerente com o drift do brief (commits 7d não tocam tasks do cycle).
- **my-work:** 30 tasks ativas (5 review / 7 blocked / 18 todo) — nenhuma mapeia esta sessão (é processo, não US).
- **Worktree dedicado:** `D:/oimpresso-handoff` off `origin/main` fresco (4d9726142). cwd da sessão é a worktree órfã `frosty-greider-83ab2f` (toplevel resolve pro repo principal).

## O que aconteceu

Prompt colado [W]/Cowork com 2 tarefas. Validei tudo contra `origin/main` ANTES de codar (Passo 0 / §10.4) — e isso mudou as duas.

**TAREFA 1 — migrar headers `os-page-h` inline → `<PageHeader>`: NÃO feita.** A Onda 0 (inventário) achou que o pedido confundia 2 coisas: `os-page-h`/`fin-page-h` é **CSS canon** do bundle Cowork (Tier 0 `feedback-cowork-bundle-aplicar-inteiro`), **não** alvo; o que o `pageheader:guard` ratcheteia é o **componente** `@/Components/shared/PageHeader` (104 baseline, verde — não toca essas telas). Estado real no main: **Dre** e **ContasPagar** já migrados (Wave 4, 25/mai) → a "pendência" do prompt era **ref morta**. **Unificado** charter v15 (10/jun) re-afirma `os-page-h` como "Markup canon EXATO" + hero "3 lentes" aprovado [W]; **Dashboard** foi DELIBERADAMENTE movido PRA `os-page-h` (19/mai, "paridade Unificado"). Migrar = regressão Tier 0 charter-protegida. **[W] confirmou pular** (perguntei com evidência).

**TAREFA 2 — gate de integridade do handoff: feita, 1 PR por onda.** A fila `COWORK_NOTES.md` apodrecia invisível (refs mortas pra `PROMPT_PARA_CODE_*` inexistentes + prompts órfãos) e nada travava. Confirmei o home antes (Regra 7): `cowork-inbox.py` é mover-de-conteúdo, não validador → **estendi a família `scripts/*-guard.mjs`**, não dupliquei. Self-test 14/14 local, gate verde no real (5 prompts / 5 citados / 0 órfão / 0 ref-morta). CI pegou 1 erro esperado: workflow novo precisa entrar no censo `gates-registry.json` no mesmo PR (memory-health Check G) — registrado, re-verde.

## Artefatos gerados

- **PR #2864** (`feat/handoff-integrity-rule`) — `prototipo-ui/PROCESSO_MEMORIA_CC.md` §16 (5 regras + IT8) + retorno `prototipo-ui/CODE_NOTES.md`.
- **PR #2865** (`feat/handoff-integrity-gate`) — `scripts/handoff-integrity-guard.mjs` (~150 LOC) + `scripts/handoff-integrity-guard.test.mjs` (8 casos/14 asserts) + `config/handoff-integrity-baseline.json` (0/0) + `.github/workflows/handoff-integrity.yml` (advisory) + `package.json` (4 scripts) + `prototipo-ui/COWORK_NOTES.md` (marcador `<!-- LINHA-DAGUA-HANDOFF -->` + zona "Handoffs ATIVOS") + `scripts/governance/gates-registry.json` (censo).

## Persistência

- **git:** 3 branches pushados (`feat/handoff-integrity-rule`, `feat/handoff-integrity-gate`, este handoff). 2 PRs abertos, CI verde, **não-mergeados** (publication-policy; [W] aprova merge).
- **MCP:** webhook GitHub→MCP propaga este handoff ~2min após merge deste PR.
- **BRIEFING:** N/A (não tocou módulo de produto).

## Próximos passos pra retomar

```
gh pr merge 2865 --squash && gh pr merge 2864 --squash   # gate ANTES da regra (anti ref-morta)
```

## Lições catalogadas

- **Handoff stale é a doença que o próprio PR cura:** a TAREFA 1 do prompt citava telas "pendentes" que já estavam migradas (ref morta) — exemplo vivo do que a TAREFA 2 mecaniza. Validar contra `origin/main` ANTES de codar pegou isso.
- **Não confundir CSS canon com componente deprecado:** `os-page-h` (classe, canon) ≠ `shared/PageHeader` (componente, em migração). O guard real (`pageheader:guard`) só liga pro segundo.
- **Workflow novo = entrada no censo `gates-registry.json` no MESMO PR** (memory-health Check G / ADR 0256), senão 🔴 mecânico. (já era regra; reconfirmada.)

## Pointers detalhados

- Regra: `prototipo-ui/PROCESSO_MEMORIA_CC.md` §16 + IT8 (#2864).
- Mecanismo: `scripts/handoff-integrity-guard.mjs` + `.test.mjs` (#2865). Rodar: `npm run handoff:check` · `npm run handoff:selftest`.
- Decisão TAREFA 1: `prototipo-ui/CODE_NOTES.md` (entrada 2026-06-16) — split de canon `os-page-h` vs `<PageHeader>` v3.8 documentado.
