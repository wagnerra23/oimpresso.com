---
date: "2026-06-18"
topic: "Avaliação adversarial do programa SDD (sdd-avaliar, 7 skeptics, run wf_597bcc15-7c0): score composto CAIU 61.9→46/100 — fundação Semana-0 sólida (GT 84), mas o nightly (único sinal vivo) está MORTO há 3 dias e nenhuma catraca ficou vermelha; tudo advisory, motor de cobertura nunca rodou"
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"]
prs: []
---

# Scorecard SDD — avaliação adversarial (2026-06-18)

> Workflow `sdd-avaliador-processo` (8 agents, ~848k tokens, ~14min). 1 skeptic por stream
> verifica o estado **REAL** em git+gh+CT100 (não o plano). `origin/main` fresco `a273254628df`
> (re-fetchado nesta sessão — meu ref local estava 74 commits atrás). Run: `wf_597bcc15-7c0`.
> Comparação: avaliação 2026-06-15 deu **61.9/100**; esta deu **46/100** — a queda É o achado.

## Score composto ponderado: **46/100** · 55 (média simples)
Pesos: FV/Fase2b ×1.8 · SA/GT ×1.3 · demais ×1.0. Cálculo: `(58·1.8 + 52·1.8 + 48·1.3 + 84·1.3 + 58 + 75 + 9) / 9.2 ≈ 45.6`.
A ponderação derruba 55→46 porque os 2 destravadores (FV, Fase2b) estão fracos.

| Stream | Peso | Score | Status macro | Maior risco sistêmico |
|---|---|--:|---|---|
| **GT** — Governance scorecard | ×1.3 | **84** | sólido (Fase 1 completa) | `continue-on-error:true` mascara RED → "7 daily green" medido na conclusão, não no sinal |
| **CH** — Charters + fluxo-novo | ×1.0 | **75** | parcial | `us:` nunca backfillado (3/151 = baseline); AJV-strict reprova 70/137 charters; nada required |
| **KL** — Knowledge/ghost/decay | ×1.0 | **58** | parcial | advisory perene + métrica de FORMA; peso_real retrieval OFF em prod; recall pesa módulo morto (Copiloto=65) |
| **FV** — Full-suite / testes | ×1.8 | **58** | parcial | "mede mas não governa": floor nunca chega ao scorecard; `full_suite_pass_rate` hardcoded `notYet` |
| **F2b** — Fase 2b P0 harness | ×1.8 | **52** | parcial | nightly é SPOF frágil; qualquer fatal o zera; floor ~1280 (não "centenas") |
| **SA** — Anchors spec↔código | ×1.3 | **48** | parcial | infra mede honesto, mas número parado em 5.3%; motor A5/G5 com ZERO execução |
| **PROM** — Semanas 4-6 (required) | ×1.0 | **9** | BLOQUEADO | onda terminal sobre fundação que mente em 2 níveis; promover hoje = required que não morde |

## O QUE PEGOU FOGO (lead — explica a queda 62→46)
**O nightly full-suite — único sinal vivo do programa — está MORTO há 3 dias** (runs 06-16/17/18):
`PHP Fatal: Cannot redeclare function insertAuditLog()` — colisão entre
`Modules/Arquivos/Tests/Feature/AuditLogCommandTest.php:41` e
`Modules/Jana/Tests/Feature/Mcp/ImmutabilityTriggersTest.php:122` (ambos declaram a função no escopo global).
Resultado: `junit.xml` = 0 bytes, pest exit 255, **zero medição**. É exatamente o bug FV-F1 ("artefato 0 bytes")
que a onda supostamente resolveu. **E NENHUMA catraca ficou vermelha** — porque `not_yet_measured` nunca regride.
O auto-quarantine do harness (`ct100-fullsuite.sh:~226`) só casa colisão de *folder* do Pest, não fatal de redeclare.

## FEITO-VERIFICADO (a fundação que genuinamente morde)
- **GT-G6 gate-selftest (94)** — 8/8 live, SEM continue-on-error; prova 4 catracas mordem (boa passa / ruim falha pelo motivo certo). Peça mais forte do programa.
- **GT-G1 ADR 0275 (95)** — 10 métricas + calendário duro + armamento 3-medições; números field-derived (anti-stale real).
- **FV-Q1 foundation-ratchet (90)** — conta USO do trait (71→15), mata métrica-de-forma. **SA-A2/A3 anchor-lint (90)** — detecta 15 `anchored_dead` REAIS (paths ausentes confirmados via git ls-tree).
- **F2b-B config_json→longtext (90)** e **A.1 mysql-client+TLS (85)** — causa-raiz reproduzida byte-a-byte, executando no nightly.

## ILUSÓRIO (parece feito, não governa)
- **FV/US-GOV-018 MEDIR→GOVERNAR (30)** — `sdd-scorecard.mjs:112` hardcoda `full_suite_pass_rate=notYet` com comentário que o próprio ADR 0279 chama "factualmente falso". `nightly-floor.json` não existe. PR #2765 = só proposta OPEN.
- **F2b resultado agregado (35)** — floor NÃO caiu pra centenas: segue **1280-1308** (casa do milhar). Os 3 últimos nightlies mortos (acima).
- **GT-G7/G8 snapshot+brief (78/80)** — wiring correto mas testes PHP não executados (autoload quebrado) e cron não-confirmado em prod.

## FALTA (motor que move o número — nunca rodou)
- **SA-A5/A6 (12/8)** — batch IA + refutador G5 com **0 entries tipo:anchors** no ledger; fila Wagner inexistente. É o passo que levaria 5.3%→~100% e não começou.
- **KL-E2b reseed (15), Trilha C C3-C5, Trilha D D2-D4** — todos dependem de CT100+SSH+secret+flag-flip nunca executados.
- **C2 coverage / T1 mapa / T2 TDAD (4/2/2)** — pcov nunca instrumentado em CI; trilha de coverage não iniciou.

## O QUE FALTA DE ONDAS
| Onda | Rodou (verificado) | Resta |
|---|---|---|
| **Semana 0** | GT inteiro (G1-G8), SA-A1/A2/A3, FV-F1/F2/Q1, Charters template/schema/skill, F2b A.1/B | praticamente nada — sólida |
| **Sem 1-2** | SA-A4 parcial (6 US promovidas), KL codemod+ADR0274+E1 | **SA-A4 travado** (PR #2611 OPEN/RED em Schema SPEC.md); 22 placeholders + 721 sem_campo |
| **Sem 2-4** | KL-E2 portas, E3 parcial (5 BRIEFINGs), FV burn-down B1-B4 parcial | **SA-A5/A6 não-iniciado**; E2b reseed; Trilha C/D no CT100; floor governado |
| **Sem 4-6** | nada (correto — terminal) | **R1, C2, T1, T2, SA-A10, GT-G3-required — 0/6** |

## TOP 5 RISCOS SISTÊMICOS
1. **A suite mente por advisory perene** — 0/18 required são gates SDD; já houve PR mergeado com anti-ghost VERMELHO. Todo o aparato roda "verde" sem mover comportamento.
2. **Mede mas não governa** — `full_suite_pass_rate` hardcoded `not_yet_measured` "pra sempre"; floor por interseção nunca computado; CT100→scorecard é proposta OPEN. R1 e toda a Sem 4-6 repousam sobre número inexistente.
3. **Nightly = SPOF frágil** — 3 noites mortas por 1 função duplicada que o auto-quarantine não captura; nenhuma catraca regrediu (`not_yet_measured` não regride).
4. **Motor de cobertura com zero execução** — SA prometido 5.3%→100% mas A5/G5 nunca rodou pra anchors (ledger 0 entries tipo:anchors). 15 `anchored_dead` já são "anchors-mentira" mergeados.
5. **`continue-on-error` mascara RED + métrica de FORMA** — `sdd-scorecard.yml`/`protection-drift.yml` reportam "success" com ratchet/staleness firando por dentro (run 27755629417). `front_door=100%` (11 tombstones), `ghost_count=14` (grandfathering 35), ragas/recall/peso_real OFF.

## VEREDITO
**No caminho — mas a parte fácil.** A Semana 0 de governança é genuinamente sólida e honesta (GT 84, gate-selftest 8/8 que comprova mordida, anchor-lint que detecta mentiras reais, foundation-ratchet que conta uso). Isso é o oposto de teatro. O problema: **100% do que move comportamento ainda é advisory, e os 2 motores que dariam substância nunca rodaram** — cobertura de anchors (A5/G5, parado em 5.3%, 0 entries no ledger) e o elo que transforma "rodamos a suite" em "a suite governa o merge" (floor→scorecard, hoje hardcoded `notYet` sobre um nightly morto há 3 dias). Nota honesta: **46/100 ponderado — fundação A-, execução do motor D.**

## CAMINHO CRÍTICO (ordem forçada)
1. **HOJE** — consertar `Cannot redeclare insertAuditLog()` (renomear num dos 2 test files) + endurecer `ct100-fullsuite.sh:~226` pra quarentenar fatals de redeclare/parse. Sem isso o nightly fica morto e bloqueia TUDO a jusante.
2. **Elo MEDIR→GOVERNAR** — implementar ADR 0279: step 8 (floor=interseção ≥2 runs) → `nightly-floor.json` → leitura no scorecard matando o comentário falso da linha 112 + watchdog de staleness (>48h = vermelho).
3. **Motor de cobertura SA** — destravar PR #2611 + rodar A5 batch IA com refutador G5 (1ª entry tipo:anchors) → corrigir 15 anchored_dead → armar baseline (1/3→3/3).
4. **Burn-down FV** baixar floor 1280→~0 não-quarentenado + armar n_quarantine; só então contar os 7 nightlies verdes.
5. **Promoções** só depois de scorecard 10/10 vivo e gates sem `continue-on-error`.

> Re-rodar este avaliador a cada fecho de onda / antes de promover gate. `score_composto` candidato a 11ª métrica (ADR 0275).
