---
slug: 2026-07-17-1503-seguranca-agente-c11-porte-c7
tldr: "Fechei 3 chips da dimensão segurança-do-agente (grade 2026-07-17, 5,0/10): C11 invoca o corpus de injection em CI (path-filter + cron) + teste que fecha o furo 'verde com defesa desligada'; porte de 2 blockers .ps1→.mjs cross-plataforma (escopo real = 2, medido); C7 devcontainer com egress default-deny (o único controle de TIPO diferente). Todos MERGED e verificados rodando no estado de main. Rumo [W]: fechar em vez de puxar mais chips — a grade aponta ratio negócio/governança 3,33× alarme:true."
time: "15:03 UTC"
prs: [4409, 4416, 4420]
title: "Segurança-do-agente 5,0/10 → 3 chips (C11 corpus · porte lote B · C7 egress) todos MERGED"
type: handoff
authority: informativo
lifecycle: ativo
date: "2026-07-17"
related_adrs: [0298, 0314, 0290, 0271]
pii: false
---

# Handoff — segurança-do-agente: C11 + porte lote B + C7 (3 PRs merged e verificados)

Sessão de melhoria da dimensão `seguranca-do-agente` (grade 2026-07-17, nota 5,0/10). Diagnóstico:
30 hooks PreToolUse (forte) + zero controle de ambiente (fraco — defesa 100% sintática).

## O que aterrissou (verificado no estado de main, não só verde no PR)

| chip | PR | commit main | prova funcional em main |
|---|---|---|---|
| C11 corpus invocado | [#4409](https://github.com/wagnerra23/oimpresso.com/pull/4409) | `0ad8eba6a0` | corpus rodou: 6/6 camada A, 0 regressões |
| porte lote B (2 blockers) | [#4416](https://github.com/wagnerra23/oimpresso.com/pull/4416) | `dfe2e8491d` | diferencial 8/8; testes verdes |
| C7 egress default-deny | [#4420](https://github.com/wagnerra23/oimpresso.com/pull/4420) | `eff337de35` | firewall rodou em main (`29590001202`): bite+release+fail-loud |

Detalhe completo em [session log](../sessions/2026-07-17-seguranca-agente-c11-porte-c7.md).

## Aberto de propósito (nada bloqueia o valor de hoje)

- **Os 4 caminhos UNGUARDED do corpus** (curl exfil · gh api · gh pr merge · node -e) seguem
  UNGUARDED **fora** do devcontainer — é gap de canal permitido, não de config. Documentado no
  `.devcontainer/README.md`. NÃO flipei os cenários do corpus pra "fechar": seria mentir sobre alcance.
- **Resto do lote B** (4 advisory: bom-encoding, test-without-red, warn-red-first, nudge-test-contract-anchor)
  + **lote A** (5 hooks SessionStart) — ver triagem `memory/sessions/2026-07-09-triagem-hooks-ps1-subtracao.md`.
- **3 hooks em `warn` nunca armados** — armar é decisão [W], não faxina técnica.
- **Chip `task_5c3028a8`** (estender `memory-health` pro drift do AUTOMATIONS.md apontar pra
  arquivo vivo) — rodando em sessão paralela iniciada por [W].

## Rumo (decisão [W] tomada nesta sessão)

A grade aponta a **pior nota em inteligencia-de-negocio 3,0** e ratio negócio÷governança
**3,33× `alarme:true`**. Os 3 PRs de hoje são governança — [W] escolheu **fechar a sessão** em vez
de puxar mais chips de segurança (que seriam mais governança/commodity). Próximo bloco de maior
alavanca segundo a grade = eixo Servir, mas precisa de sinal de cliente (ADR 0105).

Candidatos de negócio já no backlog (my-work): `FIN-4` (cobrança ROTA LIVRE, em HITL/blocked),
`US-OFICINA-026` (outreach Martinho), `FORJA-142` (Sells/Create P0 piloto ROTA LIVRE),
`US-RECURRINGBILLING-002/003` (motor cobrança + boleto).

## Armadilhas desta sessão (pro próximo)

- **Harness de hook em PowerShell:** o hook lê `[Console]::In.ReadToEnd()` — payload por PIPELINE
  não chega. Convenção da casa (`.test.ps1`): JSON em arquivo temp + `cmd /c "powershell -File hook < arquivo"`.
- **`git show origin/main:<path>` no Git Bash** = MSYS mangling (`:`→`;`), stdout vazio, parece
  arquivo ausente. Use `ls-tree` / `MSYS_NO_PATHCONV=1` / `cat-file`.
- **Sempre controle-positivo + negativo:** resultado uniforme ("tudo passa"/"tudo falha") é sinal
  de harness errado, não de sistema quebrado.

## Estado MCP no momento do fechamento (2026-07-17 15:03 UTC)

- `cycles-active`: **nenhum cycle ATIVO em COPI** (off-cycle).
- `my-work` (@wagner): 30 tasks (10 review · 8 blocked · 12 todo). **Nenhuma tocada nesta sessão** —
  o trabalho foi governança de infra (hooks/CI/devcontainer), não US de backlog.
- `decisions-search "segurança do agente ..."`: ADRs vizinhas existentes (ARQ-0006 policy firewall,
  0080 trust tiers, 0284 pipeline incidente) — **nenhuma superseded**; C7/C11 são infra sob ADR 0298
  (censo de gates) + 0314 (required = só Tier-0), não ADR nova.
- Último handoff anterior: [2026-07-17 10:41](2026-07-17-1041-gate-fotografava-vazio-sidebar-preta.md).
