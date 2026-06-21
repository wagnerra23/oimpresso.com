---
date: "2026-06-21"
time: "17:03 BRT"
slug: spec-drift-zerado-adr0296-adversarial-tier0
tldr: "Continuação do incidente cota-disco (handoff 1130). Com o grant restaurado, rodei mcp:tasks:sync → spec_id_drift 646→0 (raiz: parser sem flag /u deixava byte órfão do '·' virar '?' em 751 títulos; +15 colisões reais de id renumeradas). Recuperei 2x classmap stale (artisan/scheduler down via composer dump-autoload). Introduzi o ADR 0296 (capacidade multi-DB) com rodada adversarial: o modo SEQUENCIAL furou o overload da API onde o paralelo morria (44/45 por run) → 24 riscos, veredicto nao-prova-de-falhas-ainda, incl. vazamento Tier 0 LIVE (ContextForTaskService sem business_id) corrigido no #3162."
decided_by: [W]
cycle: CYCLE-08
prs: [3116, 3121, 3124, 3153, 3162, 3163]
related_adrs: ["0296-plano-capacidade-multi-tenant-taxonomia-dados-placement", "0093-multi-tenant-isolation-tier-0", "0134-spec-id-drift-check"]
next_steps:
  - "Revisar + mergear #3162 (fix Tier 0, rodar em MySQL antes) e #3153 (ADR 0296) e #3163 (backlog)"
  - "Próximo bloqueador mais perigoso: US-COPI-128 (S-1 — gate multi_tenant_isolation cego a C3)"
  - "Decidir as 10 perguntas do Wagner no ADR 0296 (§RODADA ADVERSARIAL) antes de promover proposed→aceito"
---

## Estado MCP no momento
- Cycle ativo: **CYCLE-08 — Receita Onda A** (7d restantes) — esta sessão foi **infra/Tier 0**, fora da onda de receita.
- 3 follow-ups registrados (em #3163, sincronizam no merge): **US-ADS-001**, **US-COPI-128**, **US-INFRA-042** — backlog p1, ainda **órfãos** (sem epic/cycle = não estão no roadmap).
- Prod: HEAD `fed684833`→#3160; DB 841 MB/6144 (saudável pós-truncate de `_history`); `spec_id_drift=0`.

## O que aconteceu
Continuação do incidente cota-disco (handoff 1130). O grant `INSERT/UPDATE` do `u906587222_oimpresso` tinha voltado (cota recuperada). A partir daí:
1. **spec_id_drift 646→0.** Diagnóstico: 631/646 eram falso-positivo — títulos no cache `mcp_tasks` com prefixo `? ` (byte órfão `B7` do separador `·`, porque `TaskParserService::US_HEADING_REGEX` rodava **sem flag `/u`**). +15 colisões reais de id (RecurringBilling 9×001, SELL-010 dup, TR cross-SPEC, WA sub-letra). Fixes: check tolerante (#3116), renumber (#3121), flag `/u` (#3124); depois `mcp:tasks:sync` materializou o cache limpo.
2. **2× recuperação de classmap stale.** `MemoryHistoryPruneCommand`/`ProfileDistillCommand` registrados mas fora do classmap autoritativo → `artisan` + scheduler down. Fix: `composer dump-autoload -o`.
3. **ADR 0296 (capacidade multi-DB à prova de falhas) introduzido com rodada adversarial.** Estava só no disco (untracked). A API da Anthropic entrou em **overload sustentado** e matou 44/45 adversários em cada run **paralela** — o modo **SEQUENCIAL (1-por-vez)** furou o overload e entregou cobertura completa: **24 riscos (7 critical)**, veredicto `nao-prova-de-falhas-ainda`.
4. **Vazamento Tier 0 LIVE achado e corrigido.** `ContextForTaskService::buildRecentDecisions` lia `mcp_dual_brain_decisions` (C3, tem `business_id`) **sem filtro** → servia decisões de IA cross-tenant ao Brain. Fix cirúrgico + teste de regressão (#3162). Latente hoje (só biz=4 ativo no ADS), mas Tier 0.

## Artefatos gerados
- **PRs:** #3116/#3121/#3124 (spec_id_drift, **merged**) · #3153 (ADR 0296 + rodada adversarial) · #3162 (fix Tier 0) · #3163 (backlog 3 tasks + este handoff) — 3 últimos **abertos**.
- **ADR 0296** (`memory/decisions/0296-...md`, ~290 linhas) com §RODADA ADVERSARIAL (24 riscos, 14 invariantes novos, emendas com nº de linha, 10 decisões Wagner).
- **2 workflow scripts versionados** em `.claude/workflows/scripts/adr-0296-adversarial-{fault-proof,sequencial}.js` — re-rodam a rodada quando a API normalizar.

## Persistência
- **git:** tudo em PR (3 merged + 3 abertos). Este handoff + índice no #3163.
- **MCP:** 3 tasks via `tasks-create` (sincronizam no merge de #3163 via webhook).

## Próximos passos pra retomar
`gh pr view 3162 && gh pr view 3153` → revisar/mergear; depois atacar **US-COPI-128** (gate C3 cego, S-1). Pra continuar o plano: ler ADR 0296 §RODADA ADVERSARIAL e responder as 10 decisões.

## Lições catalogadas
- **Overload da API → workflow SEQUENCIAL, não paralelo.** Fan-out de 16 adversários numa API sobrecarregada = 44/45 morrem retentando (~1.5M tokens/run pra ~1 achado). 1-por-vez passa (requisição única espera a vez). Custou 2 runs paralelas antes de pivotar.
- **classmap stale é recorrente (3×):** comando novo registrado + deploy interrompido/cancelado entre `git reset` e `composer` = `BindingResolutionException`, scheduler down silencioso. Gate `php artisan about` existe mas deploys cancelados o burlam.
- **Cota de disco estourada = grant auto-revogado** (Hostinger), e o sync do cache mente verde no instante do snapshot — a invariante ZEROLOSS do ADR é cega a constraints/escrita concorrente.

## Pointers detalhados
- ADR 0296 §RODADA ADVERSARIAL (veredicto, 24 riscos, 10 decisões) — `memory/decisions/0296-...md`.
- Handoff irmão (parte 1 da maratona): `memory/handoffs/2026-06-21-1130-incidente-cota-disco-hostinger-mcp-history.md`.
- Fix Tier 0: `Modules/ADS/Services/ContextForTaskService.php` + `Tests/Feature/ContextForTaskTier0Test.php`.
