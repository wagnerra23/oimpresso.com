---
date: 2026-06-20
topic: "Avaliação adversarial do programa SDD — 30 threads granulares (estado real vs plano): processo ~2/3 construído, 0% governando, composto 65/100"
authors: [W, C]
us: [US-GOV-016, US-GOV-017, US-GOV-018]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Avaliação adversarial SDD — 30 threads (2026-06-20)

## Metodologia

Disparado a pedido do Wagner ("o plano sdd foi completamente concluido precisa revisar?" → "pode fazer em treads 30 treads"). Decomposição granular: **1 skeptic adversarial por PASSO** do plano-mãe (em vez de 1 por stream, como o `sdd-avaliador-processo` canônico). Cada thread READ-ONLY verifica o artefato em `origin/main` (gh/git/`git show`) + roda o script local LIVE + mede o floor do nightly (interseção ≥2 runs). 30 threads agrupadas em 7 streams pra rollup ponderado (FV/Fase2b ×1.8, SA/GT ×1.3, resto ×1.0).

**Execução em 2 runs:**
- Run 1 (`wf_0f494924-060`, 30 threads paralelas): **10/30 completaram**, 20 falharam por rate-limit de servidor (burst de ~16 Opus concorrentes — "Server is temporarily limiting requests, not your usage limit").
- Run 2 (`wf_9f7a4531-d0c`, part-2): re-rodou as **20 que faltaram em chunks de 5** (máx 5 Opus concorrentes) → **21 agents, zero falhas**. Síntese final combinou as 20 novas + 10 do run 1.

Custo total ~3.65M tokens subagent, ~1090 tool uses, ~76 min de wall-clock somado.

**Lição de processo:** fan-out de 30 Opus simultâneos estoura o rate-limit do servidor. Throttle em chunks de ≤5 concorrentes resolve. Atualizar o `sdd-avaliador-processo` (ou criar variante 30-threads) com execução chunked.

---

# SCORECARD ADVERSARIAL SDD — 30 threads verificadas (2026-06-20)

## 1) Scorecard por stream

| Stream | Peso | n | Média | Status agregado | Maior risco |
|---|---|---|---|---|---|
| **FV — Full-suite / testes** | 1.8 | 6 | **79.8** | 🟢 forte | Loop de CONSUMO aberto: JUnit é morgue-artifact (nenhum gate/scorecard lê); floor 274 medido mas scorecard commitado mente `not_yet_measured` |
| **F2B — Fase 2b harness** | 1.8 | 3 | **80.3** | 🟢 forte | Frente A: rótulo "~850" não bate medição (colapso de cascata, não 850 verdes); Frente C quarentena cosmética ainda roda no floor |
| **GT — Governance scorecard** | 1.3 | 7 | **61.7** | 🟡 parcial | G7/G8 dormentes: migration `mcp_sdd_scorecard_history` NUNCA aplicada em prod → 0 rows, linha SDD nunca aparece no brief; G3 advisory |
| **CH — Charters + fluxo-novo** | 1.0 | 2 | **61.5** | 🟡 parcial | `us:` backfill = ZERO net-new (nem é campo do schema); template fluxo-novo nunca instanciado (0/57 SPECs com `anchor_format: v1`) |
| **SA — Anchors spec↔código** | 1.3 | 5 | **59.6** | 🟡 parcial | Backfill paralisado: anchor_coverage 5.3–7.5% vs alvo 100%; A5 fez 3/57 módulos; A10 dois degraus acima do estado |
| **KL — Knowledge / ghost / decay** | 1.0 | 5 | **54.0** | 🟡 parcial | REGRESSÃO real: #2848 (Forja) re-criou `MemCofre/` após o codemod, baseline-por-módulo engoliu como "0 NOVOS"; D2/D3 RAGAS ilusórios; Trilha C flag OFF |
| **PR — Promoções a required** | 1.0 | 2 | **12.0** | 🔴 não-iniciado | Bloqueado a montante (correto por calendário); R1/C2 nem `measured`, T1/T2 zero artefato, pcov nunca instrumentado |

**score_composto ponderado (Σ score·peso ÷ Σ peso, por thread) = 65.2** (média-stream×peso = 62.3 · média simples = 62.2)

## 2) Tabela granular das 30 (ordenada por stream)

| Passo | Stream | Score | St | Modo-de-falha #1 |
|---|---|---|---|---|
| A1 ADR 0273 formato+sentinela | SA | 92 | ✅ | §Status do ADR stale ("Proposto" vs frontmatter "aceito"); bug cosmético v1_violations no JSON |
| A2/A3 anchor-lint + anchor-drift | SA | 92 | ✅ | Advisory, não required — PR pode introduzir anchored_dead e mergear amarelo |
| A4 backfill placeholders | SA | 68 | 🟡 | PR dedicado #2611 OPEN/CONFLICTING há 7d (zumbi); 15 anchored_dead que MENTEM |
| A5/A6 batch IA + refutador G5 + fila | SA | 38 | 🔴 | Gate ledger-check NÃO morde em PR real; só 3/57 módulos; fila Wagner A6 inexistente |
| A10 anchor-gate required? | SA | 8 | 🔴 | Não-iniciado; critério é coverage=100%, live mede 5.3% (gap ~95pts) |
| C1 fix harness MySQL | FV | 93 | ✅ | Transporte floor→scorecard no CI não plugado (#2765 OPEN) |
| F3 nightly MySQL CT100 — FLOOR | FV | 88 | ✅ | scorecard commitado em main diz `not_yet_measured`; advisory-only não governa |
| US-GOV-018 re-diagnose | FV | 88 | ✅ | scorecard commitado mostra `not_yet_measured` (floor 274 só em CI/órfã) |
| Q1 foundation-ratchet | FV | 80 | 🟡 | Ainda advisory; WIP da branch atual reverte regex de uso-real (re-introduz métrica de forma) |
| Q2/Q3 triage+quarentena+B1-B4 | FV | 68 | 🟡 | write-side floor NÃO no CT100; quarentena infla floor (skipped +823); B1-B4 nunca rodaram |
| F1 JUnit + F2 composite pest-mysql | FV | 62 | 🟡 | JUnit é artefato morto: nenhum ratchet/scorecard lê; composite skip-as-pass em 100 runs |
| G4 protection-drift + watchdog | GT | 88 | ✅ | Advisory por design — detecta demoção na manhã seguinte, não previne (janela cega ~24h) |
| G1 ADR 0275 (10 métricas) | GT | 72 | 🟡 | Só 2/10 métricas armam; 7 `not_yet_measured`; drift contagem (ADR=10, scorecard=12) |
| G6 gate-selftest (anti-suite-mente) | GT | 72 | 🟡 | O vigia-dos-vigias é advisory; cobre só 4 de ~18 catracas required |
| G2 agregador sdd-scorecard.mjs | GT | 62 | 🟡 | full_suite é MIRAGEM em main (lê branch órfã); ratchet continue-on-error + não-required |
| G5 refutador + ledger | GT | 58 | 🟡 | backfill_error_rate hardcoded notYet — ignora 4 entries reais; threshold erra lotes de anchor |
| G7 snapshot + G8 linha no brief | GT | 42 | 🔴 | Migration NUNCA aplicada em prod → 0 rows; linha SDD nunca aparece no brief (ID 254) |
| G3 meta-catraca required? | GT | 38 | 🔴 | Advisory de nascença (continue-on-error×3); último da fila; confusão de nome c/ "Governance Gate" |
| Anti-ghost gate + codemod + ADR 0274 | KL | 82 | ✅ | MemCofre→SRS Classe A NÃO aplicado: 12 docs ainda citam, ghost congelado no baseline |
| E1 órfãs + E2 renames + alias-map | KL | 52 | 🟡 | REGRESSÃO #2848 re-criou MemCofre após codemod; baseline-por-módulo grandfathered o ghost |
| E2b re-seed Meili + E3 BRIEFINGs | KL | 52 | 🟡 | 27 renames Classe A não aplicados → nome morto vivo no índice; motor E3 cron comentado, 5/75 |
| Trilha C decay C1-C5 | KL | 42 | 🟡 | scorecard hardcoda recall_eval_violations=notYet; flag OFF; --mode=real nunca rodou |
| Trilha D RAGAS D1-D4 | KL | 42 | 🟡 | D2 ilusório (baseline=0 → gate incapaz de falhar); D3 tautologia (faithfulness=1.0 fixo) |
| Backfill charters + preflight + grace | CH | 68 | 🟡 | `us:` backfill = ZERO net-new; `us:` nem é campo do schema (estruturalmente não-medido) |
| Template fluxo-novo (SPEC nasce c/ anchor) | CH | 55 | 🟡 | 0/57 SPECs declaram `anchor_format: v1` → ramo de lint estrito é fixture vazia/morta |
| Frente A harness/imagem (~850) | F2B | 88 | ✅ | Rótulo "~850" não bate medição (colapso de cascata, não 850 verdes); A real é #2640+#2728 |
| Frente B config_json json→longtext (212) | F2B | 90 | ✅ | Teste-âncora skipped sob MySQL; sem guard determinístico contra regressão do schema |
| Frente C era-sqlite + dívida ~490 | F2B | 63 | 🟡 | Quarentena cosmética (14/27 só @group, sem skip/--exclude-group → ainda rodam no floor) |
| R1 full-suite + C2 coverage | PR | 12 | 🔴 | Não-iniciado; full-suite roda cron CT100 → não pode ser status-check GH nativo |
| T1 mapa + T2 TDAD-lite + calendário | PR | 12 | 🔴 | Não-iniciado; bloqueado na origem (R1/C2 não flipados); zero artefato T1/T2 |

Distribuição: ✅ FEITO-VERIFICADO = **10/30** · 🟡 PARCIAL = **14/30** · 🔴 ILUSÓRIO/NÃO-MORDE/NÃO-INICIADO = **6/30**.

## 3) O que falta de ondas

| Onda | Rodou-de-verdade | Resta |
|---|---|---|
| **Sem 0 (fundação ADRs+infra)** | ADR 0273/0275/0274/0279 aceitos; sdd-scorecard.mjs + anchor-lint + gate-selftest + protection-drift + ledger vivos e mordem em fixture | Armar 7/10 métricas (hoje 2-4/12 measured); fechar drift contagem 10-vs-12 |
| **Sem 1-2 (harness + anchors base)** | **NÚCLEO REAL DA ENTREGA**: C1 harness MySQL deployado (sqlite-poisoning=0); Frente A/B merged+exercitadas; floor 274 determinístico; JUnit tripwire + composite | Frente C isolamento era-sqlite (US-GOV-021); JUnit consumo; A4 backfill fragmentado/zumbi |
| **Sem 2-4 (governança + knowledge)** | G4 protection-drift agendado+morde; anti-ghost gate+codemod; G7/G8 código+testes verdes | G7/G8 dormentes em prod (migration nunca aplicada); KL regressão MemCofre; Trilha C/D ilusórias; A5/A6 mal-começados |
| **Sem 4-6 (promoções terminal)** | **NADA** (corretamente bloqueado) | R1, C2, T1, T2, A10, G3 — toda a onda. pcov nunca instrumentado |

**CAMINHO CRÍTICO:** `pcov instrumentado + floor publicado/lido no CI` → **R1 flip** (full_suite_pass_rate vira measured) → **C2 flip** (coverage_pct measured) → só então **T1→T2→A10→G3**. Hoje a cadeia inteira da Sem 4-6 está travada na ORIGEM porque `full_suite_pass_rate=not_yet_measured` (transporte do floor depende de credencial de push no CT100) e `coverage_pct=not_yet_measured` (pcov nunca ligado). **Quem destrava: o transporte CT100→main do floor + instrumentação pcov.**

**Passos do plano-mãe SEM cobertura mesmo com 30 threads:** A6 (fila Wagner — artefato inexistente); B1-B4 (nunca executados, superseded pela triage, mas plano ainda os lista); C5 (cron recall-eval real — sem schedule); D3-antes-de-D2 (ordem que o plano exige nunca corrigida); `us:` no charter (entregável literal, net-new=0).

## 4) Top 5 riscos sistêmicos

1. **TODOS os gates SDD são advisory — nenhum dos 18 required é SDD.** Confirmado em ~15 threads via `gh api .../branches/main/protection`. anchor-lint, ledger-check, foundation-ratchet, gate-selftest, sdd-scorecard, knowledge-ghost-gate, protection-drift, memory-schema-gate — TODOS fora dos required. O sistema MEDE mas não GOVERNA. Evidência viva: regressão MemCofre (#2848) e ghost_count 14→16 entraram no main sem bloqueio.
2. **"A suite mente" via baseline-que-engole-regressão.** KL-E1/E2: #2848 shipou seu próprio `knowledge-ghosts-baseline/MemCofre.json` grandfathering Chat/DocVault/MemCofre/PontoWr2 → catraca reportou "0 NOVOS" para ghost recém-nascido. Mesmo padrão em SA-A4 (MemCofre congelado em 9 baselines em vez de corrigido).
3. **Feito-que-depende-de-algo-que-nunca-rodou em produção.** G7/G8: migration `mcp_sdd_scorecard_history` nunca aplicada em prod (`information_schema` confirma ausência), 0 rows, `SddBriefLineService` no-op permanente, brief ID 254 sem linha SDD. Métricas ilusórias paralelas: D2 RAGAS (baseline=0 → gate incapaz de falhar), D3 (tautologia faithfulness=1.0), Trilha C hardcoded `notYet`, template fluxo-novo 0/57.
4. **Contradição de medição em métricas-chave.** n_quarantine: **27 vs 127 vs not_yet_measured vs measured** (4 valores conforme a fonte). ghost_count: 14 (main) vs 16 (worktree stale). anchor_coverage: 5.3% (local) vs 7.5% (origin/main) — entries do ledger não re-verificadas. full_suite floor 274 (CI/órfã) vs not_yet_measured (scorecard commitado).
5. **Branch atual de governança re-introduz anti-padrão já corrigido.** FV-Q1: o WIP `82f005341` (feat/governance-ds-rollout-ledger, branch DESTA sessão) reverteu a regex de "uso real" do PR #2810 para `\bRefreshDatabase\b` cru + baseline 71 — a métrica-de-forma que #2810 consertou (~50 falsos positivos). **Não mergear sem revisão = regressão.**

## 5) Promoções a required (calendário ADR 0275 §5)

| Gate candidato | Stream/score | Veredito | Por quê |
|---|---|---|---|
| **R1 full-suite required** | FV 79.8 / PR 12 | 🔴 **NÃO** | full_suite_pass_rate `not_yet_measured` (transporte floor CT100→main não plugado). Pré-requisito de TODA a cadeia |
| **C2 coverage ratchet** | PR 12 | 🔴 **NÃO** | pcov nunca instrumentado; `coverage:none`; gate não existe; depende de R1 |
| **anchor-lint → F2 (A10)** | SA 59.6 | 🔴 **NÃO** | coverage 5.3-7.5% vs 100% exigido; promover hoje quebraria 721 SPECs no dia 1 |
| **G3 meta-catraca** | GT 61.7 | 🔴 **NÃO** | Stream <70; advisory de nascença; é o ÚLTIMO da fila |
| **foundation-ratchet (Q1)** | FV 79.8 | 🟡 **QUASE** | Morde em fixture E na árvore real, stream≥70 — MAS calendário exige Q2/Q3 antes, e o WIP da branch reverte a regex. Promover só após Q2/Q3 + NÃO mergear 82f005341 |
| **gate-selftest (G6)** | GT 61.7 | 🟡 **NÃO ainda** | Prova decisiva (neutralizei um gate → selftest vermelho), MAS cobre 4/18 catracas e é advisory. Ampliar cobertura antes do flip |

**ZERO gates SDD podem ir a required hoje.** O calendário está sendo respeitado (nenhuma promoção com stream<70) — mas após toda a Sem 0-4, o enforcement permanece em 0.

## 6) Veredito

**O plano SDD NÃO está concluído — está ~2/3 construído e 0% governando.** A engenharia de fundação é genuína e raramente infla: harness MySQL consertado+deployado (sqlite-poisoning zerado), Frentes A/B mataram cascatas reais (212 SQLSTATE 3140 → 0; Base-table 688 → 16), floor não-determinístico domado como interseção de ≥2 runs (274, hash reproduzível) — trabalho honesto que sobrevive ao ceticismo (FV 79.8, F2B 80.3). Mas a tese central do programa — "catracas que impedem regressão" — **ainda não existe na prática**: nenhum dos 18 required é SDD, todo gate é advisory, e duas regressões reais (MemCofre via #2848, ghost_count 14→16) entraram no main sem bloqueio. Várias métricas que justificam o scorecard são placeholder/ilusórias (G7/G8 dormentes com 0 rows, D2/D3 RAGAS estruturalmente incapazes de falhar, Trilha C hardcoded `notYet`, fluxo-novo 0/57).

**Nota honesta do processo: 65/100** — alto em "mediu a verdade sem mentir" (scorecards reportam `not_yet_measured` em vez de fingir verde; refutadores G5/triage mataram falsos-positivos), baixo em "fecha o loop com dente".

**Maior alavanca: transporte floor CT100→main + instrumentação pcov** — flipa `full_suite_pass_rate` e `coverage_pct` de `not_yet_measured` pra `measured`, destravando R1→C2 e, em cascata, toda a Sem 4-6.

**Antes de qualquer decisão custosa (flip de required, fechar US-GOV-018, declarar onda concluída), exige reprodução em DB scratch/counterfactual:** (a) `php artisan migrate` em prod + provar ≥1 row em `mcp_sdd_scorecard_history` + linha SDD no próximo brief; (b) re-rodar nightly com o floor lido pelo scorecard commitado (matar a contradição 274-vs-not_yet_measured); (c) counterfactual de promoção — armar 1 gate como required em branch de teste e provar que um PR-regressão é bloqueado.

> **Resumo:** a casa foi construída e os alarmes instalados, mas todos os alarmes estão em modo "anota no caderno", nenhum em "tranca a porta".

### Arquivos-chave de evidência
- `scripts/governance/sdd-scorecard.mjs` (métricas hardcoded notYet L146/L269)
- `governance/sdd-scorecard.json` (snapshot stale `not_yet_measured`)
- `governance/nightly-floor.json` (branch órfã `origin/governance/nightly-floor`, floor=274)
- `governance/required-checks-baseline.json` (zero entrada SDD)
- `scripts/tests/foundation-ratchet.mjs` (WIP `82f005341` reverte regex — **NÃO mergear**)
- `Modules/Governance/Database/Migrations/2026_06_12_100000_create_mcp_sdd_scorecard_history_table.php` (nunca aplicada em prod)
