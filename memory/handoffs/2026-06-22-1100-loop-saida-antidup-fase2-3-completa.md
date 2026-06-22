---
date: "2026-06-22"
time: "11:00 BRT"
slug: loop-saida-antidup-fase2-3-completa
tldr: "Fechei a porta de saída do loop (shipped-log honesto + automação) E a trava anti-duplicação L1+L2+L3, e completei a fase 2/3 (6/6: G6/G8/G18 feitos; L1/L2/G19 já existiam/cobertos). Resta a FASE 3 = relógio (promover 2 gates advisory→required em 3d) + 3 refinamentos de precisão (G8 por-SHA, G5 por-paths, G19 input automático). Nada bloqueante."
decided_by: [W]
cycle: CYCLE-08
prs: [3185, 3188, 3189, 3191, 3192, 3193, 3194, 3196, 3197, 3198, 3200, 3205, 3208, 3209, 3211]
related_adrs: [0294-metodo-dual-track-shapeup-catraca, 0278-leases-coordenacao-whats-active, 0093-multi-tenant-isolation-tier-0, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Promover dup-detector + shipped-log-gate advisory→required após 3 dias verdes + 0 FP (Wagner 2026-06-22; calendário em proposals/anti-duplicacao-work-claim-gate.md)"
  - "Fase 3 refinamentos (não-bloqueantes): G8 deploy por-SHA (ancestralidade), G5 agrupar por arquivos-tocados, G19 cycles-create puxar retro/shipped-log do cycle anterior"
  - "Seed do CYCLE-09: 1 dispatch do cron OU o endpoint /cycle-active já resolve (G6)"
us: []
---

# Porta de saída do loop + trava anti-duplicação — fase 2/3 completa

## Estado MCP no momento
- **CYCLE-08** (Receita — Onda A) · esta sessão off-cycle (governança/processo).
- 15 PRs mergeados nesta sessão (registro + automação + anti-dup + 3 peças MCP-side).

## O que aconteceu
Continuação do handoff `2026-06-22-0850`. Wagner: *"quais PR não viraram roadmap?"* → o trabalho derivou pra fechar **duas membranas** e depois a **fase 2/3 inteira**:

1. **Porta de saída do loop (shipped-log)** — gerador honesto (REST sub-janela + API /commits + revert + borda BRT + cross-check + **G8 deploy real via /api/mcp/version**) + automação (cron auto-PR+auto-merge com PAT, gate `--check`, linha no Daily Brief, 22 fixtures). Registro do CYCLE-08 no main.
2. **Trava anti-duplicação L1+L2+L3** — **L3 `dup-detector`** (eu, #3200, advisory) + descoberta de que **L1** (`TasksClaimTool`) e **L2** (`LeaseBriefSectionService`) **já existiam** (ADR 0278). Concorrência/PR-duplicado do cron barrados (#3198). Soak reduzido 14d→**3d** por decisão Wagner (#3205).
3. **Fase 2/3 (6/6)** — **feitos por mim:** G8 (#3208), G18 backlog→cycle (#3209), G6 endpoint /cycle-active (#3211). **Já cobertos:** L1, L2, G19 (cron+gate+retro).

## Lições catalogadas
- **Verificar adversarialmente ANTES de afirmar "não dá".** Errei 3× por assumir (MCP "repo separado" — FALSO, código está aqui; "G8 só no servidor" — FALSO, endpoint /api/mcp/version é público e o Actions alcança; "comentário satisfaz o guard" — FALSO, o NoMissingTenantScopeRule lê tokens do AST, não comentário). Cada verificação refutou o meu "não" e/ou revelou que metade já estava pronta.
- **PHP-no-CI sem teste local: pegar erro de padrão no 1º, não replicar.** PHPStan "ternário sempre-true" em propriedade de model nullable (G18) → cast + comparação explícita. Guard Tier 0 (ADR 0093) em query de mcp_* no controller → a substring `business_id` tem que estar em **token de código** (string-literal), não comentário (G6).
- **Editar working tree SHALLOW regride arquivo canon** (apaguei 4 entradas do gates-registry) → sempre reconstruir do `main` via `gh api`.
- **Auto-PR do cron precisa de PAT** (GITHUB_TOKEN não dispara CI → PR nasce travado). COWORK_BOT_PAT resolve.
- **PR de branch antiga não roda required novo** (charter_refs adicionado depois) → `gh pr update-branch`.
- `CentrifugoTokenIssuerTest` flaky de alta taxa trava PRs alheios — flagado (`task_7cf42399`).

## Persistência
- **git:** 15 PRs no main (lista no frontmatter). Tudo via `gh api contents` (working tree shallow/worktree órfão).
- **MCP:** webhook propaga ~2min.
- **Propostas atualizadas:** `proposals/fechar-loop-cycle-shipped-log.md` (#3193) + `proposals/anti-duplicacao-work-claim-gate.md` (ACEITA, L3 implementada, #3205).

## FASE 3 (o que resta — nada bloqueante)
**A. Relógio (não-código):** promover `dup-detector` + `shipped-log-gate` advisory→**required** após 3 dias verdes + 0 falso-positivo (calendário Wagner 2026-06-22). Hoje avisam; aí mordem.
**B. Refinamentos de precisão:**
- **G8** deploy: hoje por DATA (aproximação) → por **SHA/ancestralidade** por-PR.
- **G5** agrupamento: por scope-do-título → por **arquivos tocados** (PR multi-área).
- **G19** retroalimentação: humano lê retro → `cycles-create` **puxar** retro/shipped-log do cycle anterior automaticamente.

## Pointers detalhados
- Registro: `memory/governance/shipped/CYCLE-08.md`
- Gerador: `scripts/governance/shipped-log-generate.{mjs,test.mjs}` (G8 incluso)
- Anti-dup L3: `scripts/governance/dup-detector.mjs` + `governance/dup-hot-paths.json` + `.github/workflows/dup-detector-gate.yml`
- G6: `Modules/TeamMcp/Http/Controllers/Mcp/HealthController.php` (cicloAtivo) + `GET /api/mcp/cycle-active`
- G18: `Modules/Jana/Mcp/Tools/CyclesCreateTool.php` (propose_backlog)
