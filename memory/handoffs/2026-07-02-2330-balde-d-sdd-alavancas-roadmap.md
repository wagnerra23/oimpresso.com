---
date: "2026-07-02"
slug: balde-d-sdd-alavancas-roadmap
tldr: "8 PRs merged — BALDE D anchors 4 modulos (0%->100%, G5 Fable 6 rodadas), avaliacao SDD composto 79, FV-F1 OOM fix (2G->4G, falta nightly provar), tamper-guard require-safe (flip=Wagner), roadmap P04 desbloqueado. Pendencias: flip tamper + nightly FV-F1 -> P04 -> R1."
hour: "23:30"
topic: "BALDE D anchor backfill (4 módulos) + avaliação SDD pós-BALDE-D (composto 79) + 2 alavancas (FV-F1 OOM fix · tamper-guard require-safe) + roadmap atualizado"
authors: [C]
prs: [3661, 3662, 3663, 3664, 3674, 3675, 3676, 3682]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0263-require-safe-gates, 0279-nightly-floor]
---

# Handoff — BALDE D anchors + SDD alavancas nº1/nº2 + roadmap

## TL;DR

Sessão longa, 8 PRs todos MERGED. (1) **BALDE D** — fechou o backfill de âncoras SPEC↔código (SA-A5/P10) de 4 módulos table-form: AssetManagement/Auditoria/ConsultaOs/Arquivos, cobertura 0%→**100%** cada, refutador G5 Fable até 6 rodadas, 6 entries no ledger. (2) **Avaliação SDD** re-rodada pós-BALDE-D: composto **79** (60→67→76→79). (3) Ataquei as **2 alavancas** que Wagner pediu ("1 e 2"): FV-F1 (o bug que congela o floor) + tamper-guard require-safe. (4) **Roadmap atualizado**. Duas pendências reais sobraram, ambas gated fora do meu alcance.

## O que foi feito (verificado MERGED em origin/main)

**BALDE D — anchors (P10):**
- `#3661` AssetManagement (8 ok) · `#3662` Auditoria (9 ok + 1 parcial) · `#3663` ConsultaOs (3 pendente) · `#3664` Arquivos (12 ok + 4 parcial + 10 pendente).
- **Achado estrutural** (confirmei escopo com Wagner via AskUserQuestion antes de mexer): estes SPECs tinham US em **tabela/prosa**, invisíveis ao `anchor-lint` (que só conta heading `### US-XXX-NNN`). Precisou **converter tabela→heading** + campo `**Implementado em:**` v1 — reestruturação, não backfill mecânico como os lotes anteriores. Garantia (já feito), ProductCatalogue (ADIADO/intocado triagem) e _DesignSystem (não é módulo) ficaram de fora.
- **G5 pegou coisa real**: refutador Fable-5 (tier-superior, sessão fresca) reprovou AssetManagement (r1-r4) e Arquivos (r1-r6) por **imprecisão documental** (nomes de coluna `user_id`→`receiver`/`transaction_datetime`, `supplier`/`maintenance_date` inexistentes, "100 fixtures"→30, `IndexController@index`→`@__invoke`, `jana:`→`arquivos:health-check`, 8→7 controllers) — **zero feature-fantasma, os 34 anchors sempre corretos**. Tudo corrigido. `anchor_coverage` global agora **88.9%**.
- **2 CI-hurdles resolvidos**: (a) `anchor entry/covers gate` (required) — as US novas anchored_ok precisaram grandfather no `anchor-entry-baseline.json` (+68, mecanismo no-new-lie); (b) `baseline-tamper-guard` — precisou `BASELINE-GROW`+`BASELINE-ABSORB` nos trailers + rebase da base stale (origin/main andou +7 no meio).

**Avaliação SDD (`#3674`):** `/sdd-avaliar` run `wf_b96eea31` (8 agents) → composto **79**. Streams: Fase2b 91 · SA 89 · GT 87 · KL 82 · Charters 82 · FV 73 · Sem4-6 58. Veredito: no caminho, honesto; gargalo-raiz = Pest morre mid-suite antes do flush do junit.

**Alavanca nº1 — FV-F1 (`#3676`):** diagnóstico (agent) reenquadrou = **OOM externo** (não bug de flush; prova experimental: probe 6G ultrapassou os ~53% onde o 2G morria). Fix: Run 1 do nightly `memory_limit 2G→4G` em `scripts/tests/ct100-fullsuite.sh`. **MITIGAÇÃO — falta 1 nightly PROVAR** (R1). 2º killer suspeito (disco 95%) → task de reprodução CT100.

**Alavanca nº2 — tamper-guard (`#3675`):** tornei require-safe (roda em todo PR). Fecha o furo "no-new-lie sobre guard não-required" (risco sistêmico nº2 — o BALDE D esbarrou nele hoje). **Flip da branch protection = Wagner** (ADR 0275 §5 R3) + [proposta com critério](../decisions/proposals/2026-07-02-baseline-tamper-guard-required.md).

**Roadmap (`#3682`):** `_ROADMAP.md` atualizado — nova seção topo 2026-07-02, P10 em curso, **P04 marcado DESBLOQUEADO + próximo passo**.

## Estado MCP no momento do fechamento

- `cycles-active` (COPI): **nenhum cycle ativo**.
- `my-work` (@wagner): 30 tasks (8 review, 8 blocked, 14 todo) — **backlog geral, nada da sessão** (o trabalho SDD/anchors não está no board MCP, é governança).
- Handoffs irmãos do dia (append-only, não editados): 2245 estoque, 2230 sdd-execucao, 2045 arquivos-pest-lane, 1920 e2e3-identidade, 1700 signed-url.
- Base verificada: `origin/main` fresco `53ec0bc774`.

## Pendências reais (gated fora do meu alcance)

1. **Flip do tamper-guard a required** — clique do Wagner (ADR 0275 §5 R3), cadência ~07/jul (leva de 30/jun pode ter consumido a vaga da semana). Proposta pronta.
2. **Nightly confirmar o FV-F1 (4G)** — destrava o **P04 burn-down** (floor 298→0, atacar a fatia DB 57% primeiro) → **R1** (full_suite required, a pedra grande que resta; P13/P14 já caíram).

## Próxima ação sugerida
Após o nightly validar o 4G: preparar o **piloto do P04** (derivar os 298 do nightly vivo + escolher o 1º cluster de isolamento DB). Re-rodar `/sdd-avaliar` ao fechar o burn-down (cadência de honestidade).

## Lições
- **Refutador tier-superior (Fable>Opus) paga**: 6 rodadas pegaram imprecisão documental que auto-eval otimista não vê (mesmo padrão do LOTE C). Anchors corretos ≠ prosa correta.
- **SPEC table-form é invisível ao anchor-gate**: converter tabela→heading é reestruturação, decisão de escopo (perguntei antes de mexer).
- **Diagnose antes de fix (trio)**: FV-F1 parecia bug de flush; o diagnóstico com counterfactual já-rodado (6G) provou OOM — mudou o fix de "reconstruir junit" pra "subir memória".
