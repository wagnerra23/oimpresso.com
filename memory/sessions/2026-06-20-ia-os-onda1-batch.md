---
topic: "Batch de tasks Onda 1 (quick-wins) da auditoria do IA OS — pronto pra tasks-create"
name: 2026-06-20-ia-os-onda1-batch
description: Batch de tasks Onda 1 (quick-wins) derivado da auditoria do IA OS de engenharia agentica — pronto pra tasks-create
type: session
date: "2026-06-20"
related_adrs: [0074-temporal-validity-bi-temporal-time-travel, 0084-triggers-mysql-imutabilidade-mcp-audit-log, 0095-skills-tiers-convencao-interna]
---

# Batch Onda 1 — IA OS quick-wins

> **Status 2026-06-20 (final da sessao):**
> - **T1** ✅ #3056 merged · **T3** ✅ #3058 merged · **T6** ✅ #3063 merged · **T3-fix** ✅ #3065 merged
> - **T2** (+T2-fix parse-29-hooks) #3057 · **testes T1+T3** #3062 · **T7-A** (tier nas 18 skills) #3067 — auto-merge armado
> - **Reconferencia adversarial** (workflow wznwj303g): T1 solido; T2/T3 ressalvas → endereçadas/testadas.
> - **T4 / T5** (Tier-0) → decisoes tomadas (delegacao Wagner) em [2026-06-20-t4-t5-decisoes-tier0.md](2026-06-20-t4-t5-decisoes-tier0.md): T5=cadeia global, T4=aceitar 0074. Aguardam ratificacao ADR + fechar lacuna de CI Pest(Jana) antes do codigo.
> - **T7-B** (command de telemetria trimestral) → backlog (precisa Pest CT100).
>
> Derivado de [2026-06-20-arte-ia-os-engenharia-agentica.md](2026-06-20-arte-ia-os-engenharia-agentica.md) (parent_audit). Todas as 7 tasks sao isoladas e paralelizaveis (sem overlap de arquivo) — boa entrada pro `coordenador-paralelo`. Caminhos confirmados por glob/grep em 2026-06-20.

## T1 · P1 · Decodificar base64 nos titulos de ADR do indice gerado
- **Gap:** #1 — 56 de 277 titulos renderizam como `!!binary <base64>` na fonte unica queryable.
- **Arquivo:** `scripts/governance/adr-index-generate.mjs`
- **Aceite:** `node scripts/governance/adr-index-generate.mjs` gera 0 titulos `!!binary`; os 56 ADRs afetados legiveis; gate `adr-index-gate.yml` verde.
- **Esforco:** ~2h. **Deps:** nenhuma.

## T2 · P1 · Rodar os ~50 hooks em CI
- **Gap:** #2 — `test-all-hooks-smoke.ps1` e local-only; quebrou 4 hooks em mai/2026 sem CI pegar.
- **Arquivos:** `.github/workflows/gate-selftest.yml` (+ job pwsh) · `.claude/hooks/test-all-hooks-smoke.ps1`
- **Aceite:** PR que quebra um hook de proposito falha o job em CI; `*.test.ps1`/`*.test.mjs` por hook rodam no runner; entra no `governance-gate-umbrella`.
- **Esforco:** ~3h. **Deps:** nenhuma.

## T3 · P1 · Registrar enforcement R10 em settings.json + ActionGate strict
- **Gap:** #3 — `block-pr-without-approval.mjs` existe mas NAO esta registrado (confirmado: 0 em `.claude/settings.json`); R10 depende do modelo lembrar.
- **Arquivos:** `.claude/settings.json` (hook PreToolUse) · `.claude/hooks/block-pr-without-approval.mjs`
- **Aceite:** `gh pr create`/merge sem aprovacao e bloqueado pelo hook registrado; `block-pr-without-approval.test.mjs` cobre o caminho registrado. Alinha EU AI Act Art.14 (ago/2026).
- **Esforco:** ~2h. **Deps:** idealmente apos T2.

## T4 · P1 · Shipar bi-temporal event-time (ADR 0074)
- **Gap:** #4/#6 — so uni-temporal em prod; destrava +18,5% acc em knowledge-updates (Zep).
- **Arquivos:** migration nova em `Modules/Jana/Database/Migrations/` (add `event_valid_from`/`event_valid_until`/`supersedes_id` em `copiloto_memoria_facts`) · `Modules/Jana/Services/Memoria/MeilisearchDriver.php` · tool `memoria-historica` · deteccao via Haiku.
- **Aceite:** consulta "X estava ativo em <data>?" resolve por event-time; ADR 0074 `proposto -> accepted`. **Tier 0:** manter `business_id` global scope.
- **Esforco:** ~2 dias. **Deps:** nenhuma.

## T5 · P2 · Tornar `mcp_audit_log` tamper-evident (portar hash-chain do MarcacaoService)
- **Gap:** #10 — audit append-only sem hash-chain; reuso, nao invencao.
- **Arquivos:** ref `Modules/Ponto/Services/MarcacaoService.php:168` (`verificarIntegridade`, `hash_anterior`, sha256) -> aplicar em `Modules/Jana/Database/Migrations/2026_04_29_100005_create_mcp_audit_log_table.php` + service de escrita do log.
- **Aceite:** cada linha grava `hash`+`hash_anterior`; `verificarIntegridade()` detecta adulteracao; Pest cobre quebra de cadeia. **Tier 0:** nao cruzar `business_id`.
- **Esforco:** ~1 dia. **Deps:** nenhuma.

## T6 · P2 · Corrigir drift Copiloto->Jana nas skills
- **Gap:** genuino — `jana-recall-flow/SKILL.md` cita `Modules/Copiloto` 18x, codigo real e `Modules/Jana` (grep=0).
- **Arquivos:** `.claude/skills/jana-recall-flow/SKILL.md` (+ varrer `jana-arch`, `brief-update`).
- **Aceite:** `grep -r "Modules/Copiloto" .claude/skills` retorna 0; `/sync-skills` propaga pro MCP.
- **Esforco:** ~1h. **Deps:** nenhuma.

## T7 (bonus) · P2 · Fechar `tier` nas ~19 skills sem tier + loop telemetria->promocao
- **Gap:** Skills — ADR 0095 driftando de si mesma (verificador: 51/70 com tier; faltam ~19).
- **Aceite:** 100% das skills ativas declaram `tier`; relatorio trimestral de `mcp_skill_telemetry` (que ja roda) embasa promover/rebaixar.
- **Esforco:** ~3h. **Deps:** nenhuma.
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
