---
date: "2026-06-30"
time: "16:03 BRT"
slug: anti-bifurcacao-armar-gates
tldr: "Anti-bifurcação virou catraca que morde: dente colisão-ADR (já required via umbrella) + fonte-única charter (#3436) + armar 5 gates (flip live 20→25). Near-miss: tier0 vermelho mascarado por continue-on-error quase travou o main — leia o log, não o status."
cycle: CYCLE-08
prs: [3434, 3435, 3436, 3437]
authors: [W, C]
duration: "sessão longa (épica)"
---

# Handoff — anti-bifurcação armada + armamento de 5 gates

## Estado MCP no momento do fechamento
- Cycle: **CYCLE-08 "Receita — Onda A"** (off-cycle; esta sessão foi housekeeping de governança/anti-regressão, NÃO cycle-aligned — é trabalho de máquina).
- HITL: nenhum novo. **1 chip spawnado** (`task_0cbefece`): "Zerar 2 violações Tier-0 no main".
- **Nenhum ADR novo** criado — a sessão IMPLEMENTOU sob ADRs existentes: 0258 (gate-com-controle-negativo), 0264 (dominio-gate = molde fonte-única), 0275 (calendário advisory→required), 0271 (subtração de gates).

## O que aconteceu
Pergunta crua **[W]**: *"a máquina do charter está quebrada? tem plano mestre? como deveria ter sido construído?"* → diagnóstico via **workflow adversarial** achou a doença: **mede-mas-não-governa**, em 3 camadas (charter · ondas SDD · a própria governança). Achados:
- **Plano do Fable existe** (`memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md`, commit Co-Authored-By Claude Fable 5) — SDD em ondas. **Seguido só no andaime; ondas paradas desde 24/jun** (último commit Fable 13/jun).
- **14 colisões de número de ADR** — incl. o próprio **`0294`** (método dual-track × audit-log-hash-chain) e **`0180`** (um ADR-conserto-de-colisão que ele mesmo colidiu). Doença na camada que devia curá-la.
- **Regra anti-bifurcação** (molde `dominio-gate` 0264): *1 fato = 1 fonte; 2ª fonte = CI vermelho; legado grandfathered, novo morde.*

5 ações:
- **#3434** catraca colisão-ADR (fundida em `adr-index-generate.mjs --check` + `governance/adr-collisions-baseline.json` 14 grandfathered) — **descoberta: já é REQUIRED** via umbrella `Governance Gate` (corrigi 2× minha afirmação errada de "advisory").
- **#3435** `_lib-charter.mjs` (extrai read/frontmatter/walk de ancora+detectar — REDUZ superfície antes de somar gate).
- **#3436** fonte-única ALIAS×charter: armei o `detectar-telas.test.mjs` (existia, NÃO rodava em CI) no `design-memory-gates` + **check C** (charter cobre mockup → ALIAS redundante). Advisory (burn-in + path-scoped).
- **#3437** prep de armamento: +5 contexts no baseline + removi `continue-on-error` de jana-recall/nfebrasil.
- **FLIP**: `gh api POST .../required_status_checks/contexts` → live **20→25**, os 5 mordem.

## Near-miss catalogado (a lição-mãe)
`Tier-0 guards` foi recomendado **ARM-NOW** pelo workflow → **rejeitei** ao ler o LOG REAL: Pest **VERMELHO no main** (2 guards falham: `WithoutGlobalScopesCommentGuardTest` + `BusinessIdGuardTest` usa biz=4), **mascarado por `continue-on-error`** → reportava `success` e **enganou o verificador adversarial**. Armar = main red-lock instantâneo. → chip pra zerar as 2 violações (destrava o 6º gate).

## 5 gates armados (required AGORA, live=25)
Secret scan (gitleaks) · Jana recall-eval (mock) · module-grades-gate · Detectar bucket (ADR 0160) · PHP/Pest NfeBrasil. Todos **always-run** (sem deadlock "Expected"), verificados **verde no log real** (não na conclusion mascarada).

## Próximos passos pra retomar
- Resíduo: baseline(26) vs live(25) = **1** (`anchor entry/covers gate`, pré-existente staged-não-flipado; cético marcou WAIT — divide workflow com `charter status:live` ~57% vermelho).
- Chip `task_0cbefece`: zerar 2 violações Tier-0 → armar o 6º gate.
- WAIT-burnin: dente charter (#3436) ~2 semanas + tirar do path-scoped antes de armar.
- **Trilho grande ainda aberto:** retomar o plano do Fable (nó crítico **US-GOV-018** harness de teste determinístico → suite verde → flips) + **podar 91→33 gates** (ADR 0271 inacabada).

## Lições catalogadas
- **`continue-on-error` mascara red→success** e engana ATÉ verificação adversarial. Verificar gate = ler o log/step real, nunca a `conclusion`.
- **Armar > criar:** ~11 dentes construídos-mas-desarmados; o gargalo é o flip (admin), não o PR.
- **Fundir, não somar:** gate novo dentro de workflow existente (91 já é superfície demais — 0271).
- **Eu errei 2×** ("colisão é advisory" quando já-required); a verificação corrigiu — e eu corrigi a verificação no tier0. Camadas de adversário valem.

## Pointers (on-demand, não duplicar)
- Outputs dos workflows: `tasks/wge2u9oon.output` (dossiê armamento) · `tasks/wxgglzyyx.output` (diagnóstico Fable/bifurcação).
- PRs #3434/#3435/#3436/#3437 · plano Fable `sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md`.
