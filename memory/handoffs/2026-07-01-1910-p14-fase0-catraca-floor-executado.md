---
date: "2026-07-01"
time: "19:10 BRT"
slug: p14-fase0-catraca-floor-executado
tldr: "P14 Fase 0 EXECUTADO (2 sessões paralelas coordenadas, 6 PRs #3535/#3536/#3537/#3548/#3550/#3552): a catraca do floor=298 MORDE no required GT-G3 (órfã materializada + fail-red armed∧¬measured + counterfactuals no selftest 46/46), os 6 required perderam '(advisory)' do nome (dança zero-window), e 2 métricas armaram de carona (n_quarantine=27, sqlite_corruptors=0 por fusão — lei 0314). Resta: #3552 (P14b watchdog) em CI ao fechar; red-until-fixed coletivo do floor está ATIVO e consciente."
decided_by: [W]
prs: [3535, 3536, 3537, 3548, 3550, 3552]
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
  - 0314-poda-gates-onda-2-lei-fusoes
next_steps:
  - "Confirmar merge do #3552 (P14b: jobs renomeados nos 3 workflows + shims removidos + watchdog WATCHDOG_SOURCES pra full_suite/n_quarantine/sqlite_corruptors) — estava verde-parcial em CI ao fechar; depois dele protection-drift zera também os 🟡."
  - "PRs abertos com head anterior ao #3535 não produzem os 6 checks novos → 'Expected — waiting'. Fix por PR: gh pr update-branch (comentei em #3532/#3533/#3534)."
  - "Fase 1 do plano pós-avaliação (burn-down P04): ler o piloto self-heal #3507 ANTES de fan-out (kill-criteria: sem queda medida, não escalar). O floor agora MORDE — burn-down vira o caminho de descer 298→0."
  - "Atenção operacional: floor noturno >298 = TODOS os merges travados até PR visível descer/subir baseline (decisão consciente Wagner). Se acontecer, o caminho é o burn-down ou edição do baseline citando ADR 0275 — nunca desarme silencioso."
---

# P14 Fase 0 — catraca do floor morde no required (executado)

## TL;DR

Defeito nº 1 da avaliação 67 fechado na mesma noite: o required GT-G3 materializa a órfã do floor e avermelha métrica armada sem fonte (era skip silencioso). 6 required sem "(advisory)" no nome (zero-window). Baseline com 5 métricas armadas (+`n_quarantine=27`, +`sqlite_corruptors=0`). Resta #3552 (P14b watchdog) em CI; red-until-fixed coletivo do floor ativo e consciente.

Detalhe completo no [session log](../sessions/2026-07-01-p14-fase0-execucao-catraca-floor.md) e na seção "Execução" do [P14](../requisitos/_Governanca/roadmap/P14-catraca-floor-morde-no-required.md).

## O que mudou de verdade

1. **`sdd-scorecard-ratchet.yml` (required GT-G3)** materializa `governance/nightly-floor.json` da branch órfã ANTES do `--ratchet` (floor HARD, coverage soft até C2 armar).
2. **`sdd-scorecard.mjs`**: métrica `armed:true` no baseline cuja medição não é `measured` → **exit 1 com acusação** (era `continue` silencioso na linha 391 — o teatro de gate).
3. **gate-selftest 40→46 casos**: counterfactuals do floor (regressão 299>298 e fonte-ausente) + do corruptor (tier S) mordem pelo caminho real do `armed`, sem `SDD_RATCHET_ARM`.
4. **6 required renomeados sem "(advisory)"** via dança zero-window (shims → flips → rename real + baseline swap). `protection-drift` sem 🔴.
5. **Baseline GT-G3 com 5 métricas armadas** (era 3): + `n_quarantine=27` (anti-mascaramento da quarentena) e + `sqlite_corruptors=0` (fusão no required existente, sem gate novo — lei ADR 0314).

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ATIVO em COPI.
- `my-work` (@wagner): 30 tasks — 8 review (US-TR-309/310, US-PG-008…), 8 blocked (FIN-4, trilha Gold NFE dormente), 14 todo (US-SELL-036 p0, RecurringBilling escopos, US-OFICINA-026…). Nenhuma task MCP específica do P14 existia — trabalho veio do roadmap SDD (`_ROADMAP.md` §Fase 0), bookkeeping atualizado lá.
- `sessions-recent`: irmãs do dia — avaliação adversarial 67, P11 E2b/E3, máquina de revisão O/R.
- `decisions-search` desde o último handoff (18:14): nenhuma ADR nova aceita; P14 executa ADRs existentes (0275/0279/0314), sem decisão arquitetural nova — nada a criar.
- Sessão paralela `SDD plan evaluation` (nostalgic-matsumoto) ATIVA no mesmo tema — coordenação via Dedup-ack/PRs, não por arquivo compartilhado.
