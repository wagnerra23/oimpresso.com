---
date: "2026-07-02"
topic: "Avaliação adversarial SDD (7 skeptics) — 76/100 composto; P14 mordendo live (298→350=exit 1), anchor_coverage armado (55.2); 16 frentes em aberto divididas em 3 ondas; novo defeito nº1 = watchdog staleness prometido no baseline mas ausente no código"
authors: [C]
type: avaliacao-adversarial-processo
metodo: "workflow sdd-avaliador-processo (8 agents, 1 skeptic por stream, verificação LIVE em git+gh+CT100 via SSH, não no plano)"
gatilho: "Wagner — '/sdd-avaliar o que falta? ja tem 8 abertas fazendo quantas etapas ainda em aberto? divida em ondas'"
score_composto: 76
score_medio: 75
adrs_citados: [0273, 0274, 0275, 0276, 0279, 0264, 0271, 0314, 0318, 0093, 0062]
run_id: wf_b48f6109-d50
tokens_subagentes: 1063787
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0306-strangler-spec-anchored-reconstrucao-sdd
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Scorecard SDD — Avaliação Adversarial (2026-07-02)

**Composto ponderado: 76/100** · média simples 75 · pesos: FV/Fase2b ×1.8, SA/GT ×1.3, demais ×1.0
**Trajetória:** 61.9 (15/jun) → 46 (18/jun) → 65.2 (20/jun) → 60 (21/jun) → 67 (01/jul manhã) → 75 (01/jul noite, pós-P14) → **76 (02/jul)**.

> Lente única do veredito: **a fundação de governança agora morde de verdade (GT-G3+G6 required, counterfactuals provados live; anchor_coverage armado hoje) — o que separa 76 de 90 não é código de governança faltando, é burn-down (342 US sem âncora, floor 298) + relógio de mundo real (nightlies, janelas de promoção).**

## 1) Scorecard por stream

| Stream | Score | Δ vs 01/jul-noite | Estado dominante | Maior risco |
|---|---:|:---:|---|---|
| **GT — Governance scorecard** | **89** | +4 | 8/8 FEITO-VERIFICADO (G3/G6 required mordem, provado live) | `sdd-scorecard.json` commitado não-reproduzível de checkout nu (floor via órfã gitignored + PAT); o número-que-o-time-lê depende da cadeia publish→PAT→auto-PR |
| **Fase 2b — P0 harness** | **85** | +23 | Harness A/B/C FEITO; elo MEDIR→GOVERNAR fechado (P14, provado 298→350 exit 1) | Floor 298 = estado QUEBRADO travado, não meta; lever real (US-GOV-021 era-sqlite) ainda burn-down; 2/5 nightlies com junit 0 bytes |
| **Charters + fluxo-novo** | **84** | +14 | 4/5 FEITO; join `related_us` só 10.4% (25/158) | `related_us` advisory sem catraca agregada — pode estagnar em 10% pra sempre com CI verde |
| **FV — Full-suite** | **78** | +15 | Fundação verificada (nightly CT100 conferida por SSH, totals batem); burn-down PARCIAL; R1 não-iniciado | **Watchdog de staleness do floor é papel, não código** — `measureFullSuiteFloor` não compara `computed_at` vs now; fonte parada = 298 stale-green indefinido no gate required |
| **KL — Knowledge/decay** | **78** | +8 | Anti-ghost/codemod/alias/E2b FEITO+required; decay-real PARCIAL | 3/4 métricas de decay `not_yet_measured`; `peso_real` LIGADO em prod (Tier 0) sem 1 medição real do efeito; evals em PR rodam MOCK tautológico |
| **SA — Anchors spec↔código** | **74** | −8* | A1-A4 FEITO (coverage 12.6→60%, refutador G5 26 entries); A5/A6/A10 abertos | Regressão agregada defendida por 1 única catraca armada HOJE (floor 55.2), desarme automático em 48h se a medição parar |
| **Sem 4-6 — Promoções** | **34** | −2 | 1/6 FEITO (GT-G3); R1/C2/T1/T2 não-iniciados, A10 bloqueado | Ler a catraca de débito (floor 298) como "R1 quase feito" — R1 exige suite VERDE (~1120 fails/run) + p95 ≤25min (hoje ~110min, 4.4×) |

\* SA caiu na régua, não no estado: o skeptic de hoje puniu A10 (48) e A6 (55) que ontem contavam mais alto — o coverage SUBIU (16.1→60%) e o armamento aconteceu.

## 2) Achado central

O defeito nº 1 de 01/jul (catraca do floor inerte no required) **foi fechado e provado**: P14 fail-closed (fonte armada ausente → RED, fixture `sdd-scorecard-floor-ausente` no selftest) + materialização da órfã no ratchet required + simulação live 298→350 = exit 1. O gate-selftest passou de 40→46 counterfactuals, todos mordendo.

**Novo defeito nº 1 (herdeiro direto): o watchdog de staleness prometido no baseline não existe no código.** `governance/sdd-scorecard-baseline.json:33` afirma "fonte parada >48h desarma até nova sequência de 3 (watchdog ADR 0275 métrica 9)", mas `scripts/governance/sdd-scorecard.mjs::measureFullSuiteFloor` (~linhas 120-140) lê `computed_at` só pro detail e nunca compara com now. Se o cron CT100 ou o push da órfã `governance/nightly-floor` pararem, o required GT-G3 segue verde sobre um floor morto — exatamente o "canário que para de rodar é regressão silenciosa". **Fix ~15 linhas + fixture no gate-selftest (~1h).** Mesmo padrão ameaça `anchor_coverage` (armado hoje, mesma janela de 48h no papel).

## 3) FEITO-VERIFICADO × ILUSÓRIO × FALTA

### Confiável (provado ao vivo nesta avaliação)
- **GT-G6 gate-selftest required (96)** — 46/46 catracas com counterfactual good/bad. Núcleo anti-"suite mente".
- **GT-G3 ratchet required (93)** — 298→350 = exit 1 provado; 6 métricas armadas (anchor_coverage 55.2, full_suite 298, n_quarantine 27, sqlite_corruptors 0, ghost_count 8, front_door 100); guard P14 fail-closed.
- **SA-A2/A3 anchor-lint (94)** — fixture bad exit 1; dead=0, zombie=0, placeholder=0 nos 58 SPECs; 60% coverage live.
- **KL anti-ghost (92)** — ghost sintético injetado → FAIL exit 1 real.
- **FV-F3 nightly CT100 (92)** — verificado por SSH: 17 runs reais, totals batem exatamente com o scorecard; floor 298 = interseção de 3 runs.
- **Fase2b Frentes A/B (92/95)** — contract test 8/8 live; FK-off revertido honestamente (net-harmful no ledger).
- **E2b re-seed Meilisearch (90)** — manifest append-only com entry real (id P11-E2b, positive_control 14 hits) — era "ilusório (45)" em 01/jul, virou provado.

### Ilusório ou meio-mordente (parece defesa, não barra merge)
- **FV-Q1 foundation-ratchet (68)** — real e testada, mas DEMOVIDA a advisory (ADR 0314 D-1, deliberado); o lever vive no GT-G3.
- **Contract test harness (68)** — 8/8 pass mas `continue-on-error:true` — vermelho mergeia.
- **KL Trilhas C/D (70/64)** — evals RAGAS/recall em PR rodam MOCK tautológico; o real nunca produziu 1 nightly; `ragas_real_uptime` e `recall_eval_violations` not_yet_measured.
- **Charter `related_us` (58)** — campo existe, gate advisory: métrica de forma sem correção agregada.
- **SA-A6 fila Wagner (55)** — sem artefato; `_pendente_` pode virar lixeira que infla coverage sem escalar.

### Falta (não-iniciado, honesto)
- **R1 (12), C2 (10), T1 (5), T2 (4)** — zero script, zero janela advisory, zero pcov. **A10 (22)** bloqueado por coverage 60%<100%.

## 4) Etapas em aberto — 16 frentes, divididas em 3 ondas (8 sessões paralelas de capacidade)

**Contagem: 15 etapas do plano em aberto + 1 fix novo (watchdog).** Semana 0 = 100% rodada; Sem 1-2 ≈ 80%; Sem 2-4 ≈ 55%; Sem 4-6 ≈ 15%.

### Onda 1 — AGORA, paralelizável nas 8 sessões (código, sem relógio de mundo real)

| # | Sessão | Frente | Por quê |
|---|---|---|---|
| 1 | S1 | **Watchdog staleness** no `measureFullSuiteFloor` (+anchor_coverage) + fixture no gate-selftest | Alavanca nº 1 — ~1h, fecha a única promessa escrita que o código não cumpre |
| 2 | S2 | **US-GOV-021 era-sqlite** (isolar ~19-30 testes que dropam tabela CORE) | CAMINHO CRÍTICO — todo o stream de promoções (R1→C2→T1→T2) está serializado atrás do floor |
| 3-5 | S3-S5 | **Cauda longa de anchors** — 25 módulos a 0% (342 US) em 3 lotes IA + refutador G5 (Infra 45, Marketplaces 26, Inventory 25, ComunicacaoVisual 18, Autopecas 15, NFSe 15, Ponto 10…) | Destrava A10 (exige 100%); protocolo G5 já maduro (<2% erro) |
| 6 | S6 | **`related_us` backfill** ~120 charters de tela (lotes IA, mesmo protocolo) + decidir catraca agregada | Único join US→tela; hoje 10.4% sem nada que force subir |
| 7 | S7 | **KL-E3 + A6**: reconciliar lote 2 BRIEFINGs (#3599 no log mas `distilled_at`=5), armar `distiller_freshness` no baseline, criar artefato fila-Wagner | Fecha as pontas PARCIAL de KL e SA-A6 |
| 8 | S8 | **Resiliência junit da nightly** (2/5 runs com XML 0 bytes) + **instrumentar pcov** na nightly CT100 (`nightly-coverage.json`) | Protege a fonte do floor + inicia a 1ª medição do C2 |

### Onda 2 — MEDIÇÃO REAL (CT100/secret/relógio de dias — pouco paralelizável)
1. 1ª nightly REAL de `jana:recall-eval --mode=real` no CT100 → tira `recall_eval_violations` do not_yet_measured + prova o counterfactual do decay pós-`peso_real` ON (Tier 0 — ligado em prod sem medição).
2. Confirmar proveniência do baseline RAGAS real (faithfulness 1.0 suspeito de ci-eval) + iniciar janela D4 `ragas_real_uptime` (30d).
3. Burn-down do floor 298→0 (continuação da S2 da Onda 1, em batches por módulo B1-B4).
4. 3 medições consecutivas de `coverage_pct` → armar no baseline (pré-req C2).
5. Promover contract test do harness advisory→required após 2 verdes registrados.

### Onda 3 — PROMOÇÕES (semanas, serial por dependência — calendário ADR 0275)
1. **T1** mapa teste↔arquivo via `--coverage-php` per-test (depende pcov da Onda 1/2) → **T2** TDAD-lite lane sombra 14d.
2. **A10** anchor-gate full-tree required quando coverage=100% (estrito — cuidado: `_pendente_` conta no denominador).
3. **C2** required após 14d advisory + FP<5%.
4. **R1**: floor=0 → sharding/paralelização da suite (~110min → ≤25min p95) → janela de 7 nightlies verdes → flip com strict:true/merge-queue. É MESES; nenhum atalho de IA acelera as 7 noites.
5. Higiene: promover `anchor_format` a required pós-backfill (hoje grace-period perene), reconciliar §Status do ADR 0273 e README do baseline anti-ghost (stale cosmético).

## 5) TOP 5 riscos sistêmicos

1. **Watchdog de staleness é promessa, não código (FV/GT)** — fix ~1h, fazer JÁ (S1 Onda 1).
2. **Defesa agregada de anchors tem 1 dia de vida (SA)** — catraca `anchor_coverage` armada hoje, desarme em 48h se a medição parar; único freio até A10.
3. **Metade "decay em prod" da KL não mede nada** — `peso_real` ON em prod sem counterfactual; PR que degrade a Jana passa verde hoje. Honesto (exposto), mas descoberto.
4. **Floor 298 ≠ suite consertada** — risco de narrativa: catraca armada mede débito travado (336 failed/784 errors/noite), não R1 em andamento. Idem A10 vs no-new-lie.
5. **Cadeia frágil do número-que-o-humano-lê (GT)** — scorecard commitado depende de publish→COWORK_BOT_PAT→auto-PR + junit da nightly; defasa em silêncio, só G4 advisory avisa D+1.

## 6) Veredito

**No caminho — 76/100, sétima medição da série, subindo.** A fundação é genuinamente rara: os required mordem (provado com counterfactuals live, não leitura de código), o floor virou catraca armada fail-closed, e o sistema é honesto consigo mesmo (`not_yet_measured` em vez de zero mentido, reversões registradas no ledger, tautologia RAGAS auto-exposta). O que falta é **trabalho de volume (Onda 1, paralelizável já nas 8 sessões) + medição real no CT100 (Onda 2) + relógio (Onda 3)** — não arquitetura nova. Maior alavanca imediata: o watchdog de staleness (~1h). Maior alavanca estrutural: US-GOV-021, porque o funil inteiro de promoções está serializado atrás do floor.

## Estado MCP no momento do fechamento
- Brief #297 live (cycle —, HITL 2 pendentes Wagner); 90 commits/24h; ADRs 24h: 0316/0317/0318.
- Run do avaliador: `wf_b48f6109-d50`, 8 agents, 1.063.787 tokens, 263 tool calls, ~19.7min.
