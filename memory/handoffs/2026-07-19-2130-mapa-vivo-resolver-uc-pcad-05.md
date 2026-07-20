---
date: "2026-07-19"
time: "21:30 BRT"
slug: mapa-vivo-resolver-uc-pcad-05
tldr: "Mapa de arquivos por tela virou máquina viva (resolver --screen + charter declara artefatos); 6 briefings refrescados; HISTORIA-LINHAGEM; UC-PCAD-05 Tier 0 fix (CT 100-verificado); 4 PRs da Maiara revisadas+mergeadas. 11 PRs MERGED."
prs: [4524, 4535, 4544, 4545, 4549, 4553, 4554, 4417, 4449, 4464, 4471]
related_adrs: [0256-knowledge-survival-meia-vida-catraca-sentinela, 0314-poda-gates-onda-2-lei-fusoes, 0093-multi-tenant-isolation-tier-0]
decided_by: [W]
next_steps: ["US-PROD-023 (promover 8 telas draft→live, carimba os 3 campos $ na aba geral)", "declarar related_runbook/visual_comparison nos charters ambíguos (ex Financeiro/Unificado)"]
---

# Handoff 2026-07-19 21:30 — mapa vivo + resolver por-tela + UC-PCAD-05

## Estado MCP no momento (brief #383)

- Cycle: — · HITL pending [W]: 2 (FIN-004 cobrança ROTA LIVRE, runbook on-prem pós-Gold)
- EM VOO: G-06 BOM drag-drop (Produto), V0 0-row preço zero (Produto), Zelador diário (Governance)
- Commits 24h: 27 · Incidentes: 0

## O que aconteceu

Fio único (Wagner: *"o mapa de arquivos por tela está apodrecendo, fora das máquinas — deveria ser como?"*): tudo virou **derivado+enforçado** (ADR 0256). 7 PRs meus (mapa=comando · 6 briefings frescos · HISTORIA-LINHAGEM · --strict-coverage · resolver --screen · charter declara artefatos · UC-PCAD-05) + 4 da Maiara revisadas/mergeadas. Cada peça passou por **adversário antes de construir** (matou o gate de frescor gameável, o guard-por-nome, o teste tautológico).

## Artefatos gerados (canon)

- `how-trabalhar.md §Mapa de arquivos por tela` (comando, não .md)
- `memory/HISTORIA-LINHAGEM.md` (linhagem Delphi→UltimatePOS→oimpresso)
- `scripts/qa/screen-coverage-map.mjs` — `--screen` resolver + `classifyArtifact`/`resolveArtifact` (declaração vence nome)
- `scripts/governance/briefing-code-staleness.mjs` — `--strict-coverage` (cobertura não-gameável)
- `charter.schema.json` +related_runbook/visual_comparison/proto_baseline
- `ProductController@store` — guard FK business-scoped (UC-PCAD-05 Tier 0)
- 6 BRIEFINGs refrescados (SRS/Essentials/Vestuario/Manufacturing/Brief/Produto)

## Persistência

- Git: 11 PRs MERGED (ver `prs:`). MCP: propaga via webhook ~2min pós-push deste handoff.

## Próximos passos pra retomar

`/continuar` — ou: as 2 decisões que ficaram na mesa do [W] são **US-PROD-023** (promover as 8 telas draft do Produto, que carimba os 3 campos $) e **declarar artefatos nos charters ambíguos** (o resolver `npm run screen:files Financeiro/Unificado/Index` mostra `⚠ AMBÍGUO` até declararem).

## Lições catalogadas

- Editei o repo PRINCIPAL (`D:\oimpresso.com`, branch de outra sessão) em vez da worktree — revertido cirúrgico. **Conferir cwd git vs path do Edit.**
- `visual-regression` (required + Browser suite) roda ~15-20min mesmo em PR sem `.tsx`; `module-grades-gate` é advisory-mas-stale-required → label `module-grades-allowed-regression`.
- Tier 0 não-verificável local → DRAFT PR + CI CT 100 prova antes do merge (UC-PCAD-05).

## Pointers

- Session log detalhado: [`2026-07-19-mapa-vivo-resolver-tela-uc-pcad-05.md`](../sessions/2026-07-19-mapa-vivo-resolver-tela-uc-pcad-05.md)
