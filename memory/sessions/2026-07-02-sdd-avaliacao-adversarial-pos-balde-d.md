---
date: "2026-07-02"
topic: "Avaliacao adversarial SDD (7 skeptics) pos-merge BALDE D - composto ~79/100 (subiu de 76); SA-Anchors 89 solido e mordendo; gargalo-raiz confirmado = Pest morre mid-suite antes do flush do junit, congela o floor e trava a onda 4-6"
authors: [C]
type: avaliacao-adversarial-processo
metodo: "workflow sdd-avaliador-processo (8 agents, 1 skeptic por stream, verificação LIVE em git+gh+CT100, não no plano)"
gatilho: "Wagner — /sdd-avaliar (após landar os 4 PRs do BALDE D anchor-backfill: #3661/#3662/#3663/#3664)"
score_composto: 79
score_medio: 80
run_id: wf_b96eea31-6cf
tokens_subagentes: 1063048
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Scorecard SDD — Avaliação Adversarial pós-BALDE D (2026-07-02)

## TL;DR

Processo SDD **no caminho e notavelmente honesto — composto ≈79/100** (subiu de 76 na run da manhã, `wf_b48f6109`). A propriedade que mais importa está presente: **a suíte não mente** (floor = interseção de runs válidos, runs mortos excluídos, gate-selftest prova 46/46 catracas mordem, P14 fail-red mata "armado∧ausente→skip"). A fraqueza é **operacional, não estrutural**: o burn-down do floor nunca começou e há dependências de relógio do mundo real (cron RAGAS 07-05, 3 nightlies de coverage). **Maior alavanca única: curar 1 bug** — o Pest que morre mid-suite antes do flush do junit — que congela o floor, bloqueia o burn-down, trava R1 e por consequência toda a onda 4-6 de promoções.

Esta run confirma o efeito do trabalho da sessão: o backfill BALDE D (4 SPECs, 47 US, refutador G5 Fable até 6 rodadas) aterrissou no stream **SA — Anchors (score 89)** sem regressão — 0 dead/zombie live, cobertura global 88.9%.

## Scorecard por stream (verificado LIVE)

| Stream | Score | Status | Maior risco sistêmico |
|---|---|---|---|
| Fase 2b — P0 harness | **91** | Entregue, verificado LIVE | Floor governado depende de CT100 (disco ~95%); staleness não avermelha o ratchet (advisory) |
| SA — Anchors spec↔código | **89** | Sólido, morde de verdade | Gate required "entry/covers" repousa sobre baseline cujo anti-afrouxamento (tamper-guard) é ADVISORY |
| GT — Governance scorecard | **87** | Coração required verde | Catraca G3 confia na frescura de órfã force-pushed pelo CT100; watchdog é advisory |
| KL — Knowledge/decay | **82** | Núcleo sólido, cauda espera relógio | Trilha D (RAGAS uptime) depende de cron 1ª execução 2026-07-05; decay ligado sobre retrieval recall<80% |
| Charters + fluxo-novo | **82** | Fluxo-novo morde; dados-antigos moles | anchor_coverage armado hoje (fresco, sem folga); backfill related_us 37.5% catraca advisory |
| FV — Full-suite/testes | **73** | Honesto mas ESTAGNADO | Floor congelado (298) por nightly instável — ~40% dos runs morrem (junit 0-byte); burn-down nunca começou |
| Sem 4-6 — Promoções required | **58** | 2/6 aterrissaram | A10 estrito não bateu (88.9%≠100%); C2/T1/T2 bloqueados/não-iniciados |

**Composto ponderado** (FV/Fase2b ×1.8, SA/GT ×1.3, demais ×1.0) = **≈79/100**. Os streams de maior peso puxam pra baixo — Fase 2b entregou (91) mas FV (73) segura o teto, porque **medir ≠ derrubar**.

## Top 5 riscos sistêmicos

1. **Nightly instável congela o floor governado** (FV+Fase2b+GT). O bug FV-F1 (junit 0-byte, Pest morto antes do flush) foi só instrumentado, nunca curado — mata ~40% dos runs; os 2 mais recentes (07-01/07-02) morreram → floor parado em 01/07. Defesa contra "fonte presente porém velha" é watchdog **advisory**. CT100 disco ~95% agrava.
2. **Baseline anti-afrouxamento é advisory** (SA-A10). O gate required "entry/covers" grandfathera 655 US via `anchor-entry-baseline.json`; a única defesa contra injetar mentira-nova (`baseline-tamper-guard`) NÃO é required e já falhou 2× em 07-02 sem bloquear merge. Integridade "no-new-lie" repousa sobre guard não-required. **→ promover tamper-guard a required fecha o furo.** _(Vivido nesta sessão: o BALDE D tropeçou exatamente nesse guard — passou com `BASELINE-GROW`+`BASELINE-ABSORB`, mas como advisory teria mergeado vermelho.)_
3. **Feito-que-depende-de-cron-que-nunca-rodou** (KL trilha D). `ragas_real_uptime` depende de cron CT100 cuja 1ª execução é 2026-07-05; até lá not-armed. Baseline real ainda n=1 (RAGAS não-determinístico).
4. **Decay ligado em prod sobre retrieval fraco** (KL trilha C, Tier 0). `retrieval_enabled=true` mede recall do chat em prod com recall<80% em 25/27 queries (context_recall 0.38, hybrid off) — mede a fraqueza do retrieval, não decay puro.
5. **Armamento fresco sem folga + promoção por override** (GT+Charters+Sem4-6). anchor_coverage/distiller_freshness armados hoje (floor 55.2 < live 88.9, morde) mas não sobreviveram a uma regressão real de PR. A10 promovido via override da cadência "1/semana", critério diff-aware+grandfather, não o flip estrito-100% do calendário ADR 0275.

## O que falta de ondas — caminho crítico

`Estabilizar nightly (curar junit-0-byte)` → `B1-B4 burn-down (floor 298→0)` → `R1 (full_suite required — exige 7 nightlies verdes + p95≤25min)` → `P13 (GT-G3 estrito)`. Em paralelo, independente: `C2 destravar pcov (OOM)` → `T1/T2 (decisão CORTE-vs-EXECUTAR do Wagner)`.

O gargalo-raiz de TODO o caminho de teste é **1 bug não-curado**: Pest morre mid-suite antes do flush do junit. Enquanto existir, o floor congela e nenhuma promoção a jusante acontece.

## Veredito

**No caminho, sim — e notavelmente honesto.** A infra de governança (SA/GT/Fase2b) está sólida, required e mordendo; nada aqui é ilusório. É uma fundação verdadeira com a cauda de execução ainda por fazer. Próximas alavancas em ordem: (1) curar o Pest mid-suite (destrava tudo a jusante); (2) promover `baseline-tamper-guard` a required (fecha o furo estrutural nº2 — o mesmo que o BALDE D esbarrou hoje); (3) deixar o relógio correr pra RAGAS (07-05) e coverage (3 nightlies).

## Referências

- Run: `wf_b96eea31-6cf` (8 agents, ~1.06M tokens subagentes). Skill `sdd-avaliar` · trio imunológico SDD (avaliador + refutador G5 + reprodução).
- Run irmã da manhã: [`2026-07-02-sdd-avaliacao-adversarial.md`](2026-07-02-sdd-avaliacao-adversarial.md) (composto 76, `wf_b48f6109`) — esta é o delta pós-BALDE-D.
- Trabalho que motivou a run: PRs #3661 (AssetManagement) · #3662 (Auditoria) · #3663 (ConsultaOs) · #3664 (Arquivos) — P10 SA-A5 BALDE D, 47 US, cobertura 0%→100% por módulo.
