# Handoff 2026-06-04 17:10 BRT — QA-de-tela sustentável (ADR 0249)

## Estado: o que ficou pronto

Sistema de **QA-de-tela sustentável fundado e em `main`** (ADR 0249 aceita). 4 PRs mergeados + 1 aberto.

- **ADR 0249** (`0249-screen-qa-specialist-sustentavel`) — accepted. Separa **CI vigia (determinístico)** de **agente julga (LLM-as-judge)**.
- **Catraca de nota** (`screen-grades-gate.yml`) — no ar; bloqueia queda de nota de tela vs `origin/main`. Override label `screen-grades-allowed-regression`.
- **Catraca de cobertura** (`screen-coverage-gate.yml`, PR #2225 aberto) — bloqueia queda de charter/E2E/a11y/scorecard.
- **222 scorecards** materializados em `memory/governance/scorecards/screens/*.yaml` (seed do baseline 30/mai).
- **Agente-autor** `.claude/agents/screen-qa-specialist.md` — escreve E2E + nota; não é guardião.

## Próximo a fazer (Onda 2b/3)

1. **Mergear #2225** (catraca de cobertura) — checar `gh pr checks 2225` verde.
2. **Dim-16 mecânica** — script grep por tela do diff (charter? `@/ui`? tokens v4? zero inline-style?) como piso de CI sem LLM.
3. **Sentinela "TELAS SEM RE-SMOKE"** no gerador do Daily Brief (estende `charter:health`).
4. **Self-healing** — `screen-smoke-after-merge` invoca o agente pra regenerar E2E no drift.
5. Rolar o agente-autor por módulos P0 (Sells → Financeiro → NfeBrasil → Ponto) — a catraca trava cada ganho.

## Pegadinhas desta sessão (pro próximo não tropeçar)

- **Push de `.github/workflows/`** exige token com `workflow` scope. Credential Windows não tinha; usar `git push "https://x-access-token:$(gh auth token)@github.com/wagnerra23/oimpresso.com.git" <branch>`.
- **Workflow novo não roda na própria PR** (GitHub só dispara workflows `pull_request` que já existem no default branch) — só ativa do próximo PR em diante.
- A nota screen-grade é **LLM-as-judge**, não computável por command — não tentar "espelhar `module:grade` determinístico" pra as 16 dims.

## Estado MCP no momento do fechamento

- **cycles-active:** CYCLE-08 "Receita — Onda A (monetizar carteira legacy)" · 2026-05-31→06-28 · 24 dias restantes. Goals: pricing público, 5 clientes migração-demo, MRR R$ [redacted Tier 0] ComVis V1 live, Agrosys de-riscado.
- **my-work:** owner não resolvido pelo token MCP atual (passar `owner:` explícito).
- **decisions-search "screen-grade QA tela catraca enforcement":** retornou 0155 (module-grade v3 gate — base que a 0249 espelha), 0141 (agents tool use). **A 0249 ainda não apareceu** — webhook git→MCP tem delay pós-merge (commitada em `main`, sincroniza em minutos).
