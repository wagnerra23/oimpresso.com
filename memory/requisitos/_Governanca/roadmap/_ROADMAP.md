# Roadmap SDD — Enforcement (de 60/100 à 1ª decisão em L3)

> Origem: avaliação adversarial 2026-06-21 (composto **60/100**) — [session log](../../../sessions/2026-06-21-sdd-avaliacao-adversarial.md).
> Régua: o **teste contrafactual** (se um funcionário tentar quebrar uma decisão já tomada, o processo barra sozinho?). Hoje a detecção está em **L2 (medido, advisory)**; **L3 (required + counterfactual) tem 0 gates SDD**.
> 13 projetos detalhados, cada um verificado no repo real. **Status: EM EXECUÇÃO — vários itens já landaram (ver reconciliação abaixo).**
> Última atualização: **2026-07-02 (pós-BALDE D — composto 79, ver seção "✅ Atualização 2026-07-02" abaixo)**; anterior 2026-07-01 (noite, 2º passe) — **P14 EXECUTADO na mesma noite** (2 sessões paralelas coordenadas): core #3536 (materialização + fail-red armed∧¬measured + counterfactuals floor no selftest), rename dos 6 required sem "(advisory)" (#3535 shims → flips → #3550 baseline + #3552 jobs/watchdog), caronas #3537 (n_quarantine=27 ARMADO) e #3548 (sqlite_corruptors=0 ARMADO, fusão GT-G3 — lei 0314, sem gate novo). O defeito nº 1 da avaliação 67 está fechado: floor=298 agora MORDE no required (counterfactual live: checkout sem órfã → exit 1). Passe anterior (mesmo dia): **avaliação adversarial deu composto 67/100** ([session log](../../../sessions/2026-07-01-sdd-avaliacao-adversarial.md)) + plano de execução pós-avaliação (§ no fim) verificado por 4 agents em origin/main: **P14 novo** (catraca do floor inerte no required — defeito nº 1), errata do falso-positivo "12 tier-A", chips paralelos P10/P11 disparados. Anterior (mesmo dia): P09 executado + reconciliações P01/P03/P05/P08/P13/Pfr.

## ✅ Atualização 2026-07-02 (pós-BALDE D) — composto 79 (subiu 60→67→76→79)

Avaliação adversarial re-rodada pós-merge do BALDE D ([session log](../../../sessions/2026-07-02-sdd-avaliacao-adversarial-pos-balde-d.md), run `wf_b96eea31`, composto **≈79**; a run da manhã do mesmo dia deu 76). Streams: Fase2b 91 · SA 89 · GT 87 · KL 82 · Charters 82 · **FV 73** · **Sem4-6 58**. Veredito: **no caminho, honesto** — a suíte não mente; a fraqueza é operacional (burn-down nunca começou) + relógio real (RAGAS 07-05). **Gargalo-raiz único** = o Pest morre mid-suite antes do flush do junit (congela o floor → trava R1 → trava a onda 4-6).

**Landou hoje (verificado MERGED em origin/main):**
- **P10 — BALDE D:** 4 SPECs ancorados — AssetManagement (8 ok) · Auditoria (9 ok + 1 parcial) · ConsultaOs (3 pend) · Arquivos (12 ok + 4 parc + 10 pend). Cobertura 0%→**100%** por módulo, refutador G5 Fable até 6 rodadas, 6 entries no ledger (#3661/#3662/#3663/#3664). `anchor_coverage` global agora **88.9%**. Residual P10: ~100 US `sem_campo` (EvolutionAgent/Governance/TaskRegistry/SRS — parte gated trilha E identidade).
- **FV-F1 (alavanca nº1 do burn-down):** causa-raiz diagnosticada = **OOM externo** (não bug de flush; prova experimental probe 6G ultrapassou os ~53% onde o 2G morria). Fix: Run 1 do nightly `memory_limit 2G→4G` (#3676). **Mitigação — falta 1 nightly PROVAR** o floor computar non-stale; se morrer a 4G → 6G (teto provado). 2º killer suspeito (disco CT100 ~95%) → task de reprodução no CT100 disparada.
- **tamper-guard require-safe (#3675):** armado pra virar required — fecha o furo "no-new-lie sobre guard não-required" (**risco sistêmico nº2**; o BALDE D esbarrou nele hoje e passou só por conformidade voluntária dos trailers). **Flip da branch protection = clique do Wagner** (ADR 0275 §5 R3), cadência ~07/jul + [proposta com critério](../../../decisions/proposals/2026-07-02-baseline-tamper-guard-required.md).

**Próximo passo:** `FV-F1 nightly confirmar 4G` (blocker — burn-down não mede sobre floor que morre) → **P04 burn-down** (floor 298→0, atacar a fatia DB 57% primeiro, medir cada cluster) → **R1** (full_suite required). Detalhe na Fase 1 abaixo. `P13`/`P14` já executados — R1 é a pedra grande que resta.

## ✅ Reconciliação de estado (2026-07-01 — verificado no repo/branch-protection real)

Os docs nasceram `proposed` mas o trabalho landou sem atualizar o bookkeeping. Estado REAL (cada um verificado, não presumido):

| Item | Estado | Prova |
|---|---|---|
| **P01** | ✅ executado | floor commit-back ativo (auto-PR); `full_suite=298 measured` no main |
| **P03** | ✅ executado | `sqlite-test-corruptors --strict` exit 0 (corruptores REAIS=0) |
| **P05** | ✅ executado | `anchor entry/covers gate` na lista `required` (branch protection) |
| **P06** | 🟡 parcial | migration `mcp_sdd_scorecard_history` **aplicada em prod** + linha SDD **aparece no brief** (composta 50, k=2). Snapshot refrescado à mão 2026-07-01. **Falta:** o cron diário — `schedule:run` NÃO está no Hostinger; **decisão Wagner 2026-07-01: agendar no CT100, não Hostinger** (ADR 0062 — IA/governança ≠ shared hosting). |
| **P07** | 🟡 código-completo (arquitetura revista 2026-07-02) | 6 peças no main + **incidente do 1º run instrumentado** (20260702-073601: pcov no mesmo processo matou a suíte aos 53% E o junit → noite de floor perdida). Correção estrutural: coverage = 2ª invocação separada pós-junit (`[P07 coverage]`, 6G), contrato no `fullsuiteHarness.spec.ts`. Mecânica clover provada em fatia (14MB válido). Relógio recomeça: 3 nightlies válidas p/ armar. Detalhe no [P07](P07-instrumentar-pcov-ci-coverage.md). |
| **P08** | ✅ executado | `drift_alarms`+`backfill_error_rate` = `measured` no scorecard + 6ª catraca `anchor-lint` morde no gate-selftest (#3140); needed 0 secret/prod |
| **P09** | ✅ executado | `anchored_dead=0` E `placeholder=0` no main (#3473+#3475) |
| **P12-C** | ✅ executado | recall-eval mock no CI (`jana-recall-eval.yml`) + schedule real no `Kernel.php:438` (dom 06:30 BRT) |
| **P12-D** | 🔄 em curso | `OPENAI_API_KEY` É secret do repo → dispatch `jana-ragas-canary update_baseline=true mode=real` disparado 2026-07-01; bot popula baseline real (>0), mata a tautologia |
| **P13** | ✅ executado | `SDD scorecard ratchet (GT-G3)` na lista `required` — **2º dente em L3** |
| **Pfr** | ⚰️ feito→revertido | `foundation-ratchet` **foi** promovido a required 2026-06-21 (#3143, flip Wagner-autorizado, janela 14d pulada) e **demovido de volta a advisory** 2026-06-30 (ADR 0314 D-1 "poda", quality/não-Tier-0, #3466). Números: 0 failures/300+ runs, baseline estático. **NÃO re-propor** sem reabrir a 0314 — [proibições §5](../../../proibicoes.md). 1º dente SDD vivo em L3 = **GT-G3/P13** (segue required). |

**Divergência do plano (corrigida 2026-07-01):** os DOIS dentes landaram required em 2026-06-21 (#3143 `foundation-ratchet` + #3181 GT-G3). Em 2026-06-30 a **ADR 0314 D-1** ("poda onda 2") **demoveu o `foundation-ratchet` de volta a advisory** (quality/não-Tier-0), mas **manteve o GT-G3 required**. Estado atual: **1 dente SDD vivo em L3 (GT-G3)**, não 2. Pfr está **descartado** (não pendente) — verificado em git+gh; o "⏳ não feito" do 1º passe estava stale. Re-promoção exige reabrir a 0314.

**Ainda falta (pós-verificação 2026-07-01):** **P10** (batches IA — campanha multi-dia + fila A6) · **Trilho B** (`P02`→`P04`, burn-down full-suite required, semanas CT100) · **peso_real flag** (P12-5, Tier 0 — exige smoke CT100, nunca cego) · relógio (nightlies P07, RAGAS terminando) · follow-ups (`req_sem_lane` reconhecer CT100-nightly, **cron SDD-snapshot agendar no CT100**, **ADR 0307 §D linha 58 stale** — ainda diz "foundation-ratchet FEITO E VIVO required"; append-only, quem reconcilia é a 0314). As métricas `not_yet_measured` estão **desbloqueadas** (secret + pcov existem) — resta o relógio, não a mão. **Pfr saiu da fila** (demovido pela 0314) — só volta se a política de required virar "ratchet-everything", e aí como **leva** dos advisory de higiene, não exceção.

> **`dead_tests` pré-P10 — ✅ fechado (2026-07-01):** os 49 `**Testado em:**` mortos (Accounting/Crm/Essentials/LaravelAI/Manufacturing/RecurringBilling/Repair/_DesignSystem — todos testes planejados nunca escritos) reconciliados pra gramática `_lacuna_` (convenção Financeiro/NfeBrasil 2026-06-23). `anchor-lint --check` saiu de exit 1 → **exit 0** (dead_tests 49→0; anchor_coverage 12.6% inalterado). F2 total destravado pra P10.

## Achado-chave (refinou o diagnóstico)

A avaliação adversarial disse que o **risco-mãe** era o read-side do floor cego. Os agentes de planejamento, re-verificando, acharam que **isso já está parcialmente resolvido** (PR #2958: read-side lê o arquivo, CI materializa via `git fetch`, `scorecard.json` em main mostra `full_suite measured=274`). O gap real é **menor e mais barato** do que parecia: falta (a) **commit-back** do floor pra main (hoje stale 1 noite: 274 vs branch 295) e (b) **armar o baseline** (`armed:false`/`valid_measurements:0`).

**Consequência:** existem **dois caminhos pra L3**, e o mais rápido **não depende da suíte ficar verde**.

## DECISÃO Wagner (2026-06-21)

**O 1º gate a virar `required` é o `foundation-ratchet`** (não o GT-G3). Alinhado ao BLUEPRINT-SDD-ONDA1 Gap 3. Motivo: já tem selftest 13/13, baseline armado com medição real, catracas só-descem (menor risco de falso-positivo na janela de 14d) e **não depende de P05/P08** — é o dente mais estável pra abrir precedente. **P13 (GT-G3) vira o 2º dente.** Próximo passo aprovado: commitar o roadmap + começar o Trilho A (P06 quick win + P05/P08 + preparar a promoção do foundation-ratchet).

## Dois trilhos pra L3

### 🦷 Trilho A — caminho rápido à 1ª decisão em L3 (não precisa de suíte verde)
**1º dente = promover `foundation-ratchet` a `required`** (remover `continue-on-error` + entrar no `required-checks-baseline` + 7 verdes/14d). Já está armado e advisory — basta iniciar a janela e preparar o flip. **0 relógio humano fora os 14d.**
Em paralelo, `P05` + `P08` → **`P13`** (GT-G3, o **2º dente**) + janela advisory 14d. GT-G3 guarda o scorecard/ghost-baseline, que também já está armado (`ghost_count` + `front_door_coverage`).

### 🧱 Trilho B — full-suite `required` (R1) — o burn-down pesado
`P01` (commit-back) → `P02` (armar baseline) · `P03` (18 corruptores) → `P04` (7 noites verdes) → **R1** (promoção futura).
Esforço dominado por **7+ noites de relógio real** (CT100). Semanas, não dias.

### ⚡ Quick wins (avulsos, visíveis)
- **`P06`** (~1h, zero código novo): migrar a tabela em prod → **a linha SDD aparece no brief** + card do dashboard sai do empty-state. *Soft-dep de P01 — pode soltar já* (composta v1 = média das armadas; já há 2 armadas → sai não-nula sem P01).
- **`P07`**: `pcov` no CI → destrava `coverage_pct` (catraca C2).

### 📚 Conteúdo (paralelo, travado no volume)
`P09` ✅ **executado 2026-07-01** (dead=0 + placeholder=0 no main; #3473+#3475) → `P10` (batches IA + fila Wagner) · `P11` (renames + distiller) · `P12` (decay real).

## Os 13 projetos

| # | Projeto | Onda | Depende | Destrava | Esforço (código / relógio) | DoD (counterfactual) |
|---|---|:---:|---|---|---|---|
| [P01](P01-reconectar-read-side-floor.md) ✅ | Commit-back do floor pra main | 0 | — | P02,P06,P07,P13 | **executado** | ✅ floor auto-PR ativo; full_suite=298 measured |
| [P02](P02-armar-baseline-full-suite.md) | Armar baseline full-suite | 0 | P01 | P13 | 0.3d / +3 noites | `armed:true`,`valid:3`; regressão → exit 1 |
| [P03](P03-us-gov-021-isolamento-era-sqlite.md) ✅ | US-GOV-021: isolar 18 corruptores | 2 | — | P04 | **executado** | ✅ `sqlite-test-corruptors --strict` exit 0 (REAIS=0) |
| [P04](P04-burn-down-ate-nightly-verde.md) 🔜 | Burn-down até nightly verde | 2 | P03,P01,P02 | (R1) | 3-4d / **2-3 sem** | **DESBLOQUEADO** (deps executed) — **PRÓXIMO PASSO**, gated no nightly confirmar o fix FV-F1 (#3676) primeiro. Alavanca: fatia DB 57% antes de asserts. DoD: 7 noites floor=0, skipped não infla |
| [P05](P05-fechar-grandfather-baseline-tamper-guard.md) ✅ | Fechar grandfather (vetor #2848) | 1 | — | P11,P13 | **executado** | ✅ entry/covers armado a required |
| [P06](P06-materializar-g7-g8-historia-brief.md) | Migrar prod → linha SDD no brief | 3 | (P01 soft) | — | **~1h** / 1-2d cron | `snapshot` FAILURE→exit 0 + 1 row |
| [P07](P07-instrumentar-pcov-ci-coverage.md) | `pcov` no CI (coverage_pct) | 3 | — | P13 | 0.8d / 3+14d | `coverage_pct` vira `measured` |
| [P08](P08-conectar-metricas-gt-e-fixture-anchor.md) ✅ | Conectar 2 métricas GT + fixture anchor | 1 | — | P13 | **executado** (#3140) | ✅ `drift_alarms`+`backfill_error_rate` `measured`; 6ª catraca `anchor-lint` morde |
| [P09](P09-sa-a4-sanear-placeholders-anchored-dead.md) ✅ | SA-A4: sanear placeholders + dead | 4 | — | P10 | **executado 2026-07-01** (#3473+#3475) | ✅ `anchor-lint` dead=0, placeholder=0 |
| [P10](P10-sa-a5-a6-batches-ia-fila-wagner.md) 🟡 | SA-A5/A6: batches IA + fila + enforce | 4 | P09 | P13 | 3-4d / 2-3 sem | **em curso** — cov global **88.9%** (waves 1-3 + lotes A/B/C + **BALDE D 2026-07-02**: AssetManagement/Auditoria/ConsultaOs/Arquivos, #3661/#3662/#3663/#3664). Residual ~100 US `sem_campo` (parte gated trilha E). PR sem ledger → umbrella vermelho |
| [P11](P11-kl-e2-renames-reseed-distiller.md) 🟡 | KL E2: renames + re-seed + distiller | 4 | P05 | — | 1d / dias | E2a ✅ #3155 (ghost 14→8 armado) · E2b ✅ **executado 2026-07-01** (manifest `governance/reseed-meilisearch-manifest.json`) · E3 🟡 dry-run ✅ (crash GLOB_BRACE corrigido #3532; lote 1 aguarda **skim Wagner**) — freshness `measured` só após run real |
| [P12](P12-decay-real-ragas-recall.md) | Decay real: RAGAS + recall-eval | 5 | — | — | 1d / **secret Wagner** | RAGAS baseline>0 (sai da tautologia) |
| [P13](P13-promover-gt-g3-required.md) ✅ | **Promover GT-G3 a `required`** | 6 | P05,P08 | — | **executado** | ✅ `SDD scorecard ratchet (GT-G3)` na lista required |
| [P14](P14-catraca-floor-morde-no-required.md) ✅ | **Catraca do floor MORDE no required** (defeito nº 1 da avaliação 67) | 0 | — | P04,R1,C2 | **executado 2026-07-01** (#3535/#3536/#3537/#3548/#3550/#3552) | ✅ selftest 46/46 (floor 299>298 + fonte-ausente + corruptor tier-S mordem no armed real); checkout sem órfã → exit 1 live; 0 required com "(advisory)"; +2 métricas armadas (n_quarantine=27 · sqlite_corruptors=0) |

## Divergências que os agentes acharam (criticar aqui)

1. **Risco-mãe menor que o laudo.** Read-side já conectado (#2958); falta só commit-back + armar. P01 caiu de "refazer o elo" pra "1 job CI + 1 edit". *(P01, P02)*
2. **P06 é quick-win de ~1h** — composta já sai não-nula (2 métricas armadas), não precisa de P01. A linha SDD no brief pode acender essa semana. *(P06)*
3. **O lever do floor são 18 corruptores, não 14/27 quarentenados** — os quarentenados são conjunto DISJUNTO dos que dropam tabela CORE. Já existe auditor `sqlite-test-corruptors.mjs` medindo os 18 certos. *(P03)*
4. **P11 DoD `ghost_count→0` é estruturalmente impossível só com codemod** — o detector conta ghosts em `adr/` mas o corretor PULA `adr/` por design (#2729 Tier 0). PontoWr2/Copiloto/DocVault citam 100% dentro de `adr/`. Exige reconciliar detector×corretor antes de prometer →0. *(P11)*
5. **P12 pior que a evidência** — `JanaServiceProvider:79` não é cron, é registro; `jana:recall-eval` está ZERO agendado (sem CI, sem Kernel). *(P12)*
6. **Conflito de ordem do 1º required — RESOLVIDO 2026-06-21:** Wagner escolheu **foundation-ratchet** (BLUEPRINT Gap 3) como 1º dente; GT-G3 (P13) é o 2º. *(P13)*

## Caminho crítico recomendado

**1º dente:** promover `foundation-ratchet` (já armado/advisory → iniciar janela 14d + flip) — **sem depender da suíte verde**.
Em paralelo: `P06` (quick win visível, ~1h) · `P05` + `P08` (~2-3d) → **`P13`** GT-G3 (2º dente).
Trilho B começa em paralelo (`P01`→`P02`, `P03`→`P04`) rumo a R1.
**Não promover nada antes do baseline armado e do grandfather fechado (P05)** — senão vira `main` required-vermelho.

---

## Plano de execução 2026-07-01 — pós-avaliação 67/100 (verificado por 4 agents em origin/main)

> Origem: [avaliação adversarial 2026-07-01](../../../sessions/2026-07-01-sdd-avaliacao-adversarial.md) (composto **67**, subiu de 60) + workflow de verificação `wf_26bdd155` (4 agents, file:line em snapshot `dd3ed7c311`). **Alocação de modelo:** julgamento/adversarial/refutação → Fable 5; volume mecânico (geradores de lote, fan-out de burn-down) → Opus 4.8; **refutador sempre de tier ≥ gerador, nunca igual quando evitável** (achado: as 4 entries do ledger são opus↔opus).

### ⚠️ Errata da avaliação (verificada por reprodução, 2026-07-01)

O achado **"12 corruptores era-sqlite tier-A vivos / P03 bookkeeping mente" é FALSO-POSITIVO de checkout stale**: o skeptic rodou o auditor no repo principal `D:\oimpresso.com` @ `0b59ec3dc9` (#3412, 114 commits atrás de main, ANTERIOR ao #3445 que guardou os 12). Em `origin/main` o auditor dá **0 corruptores** (default e `--strict`, exit 0 — reproduzido). **P03 segue `executed` CORRETO.** Defesas: (a) skeptics do `sdd-avaliador-processo` passam a exigir prova `git rev-parse HEAD == origin/main` pós-fetch antes de qualquer claim "live" (editar workflow + skill); (b) `git pull` no repo principal. Os DEMAIS achados da avaliação (catraca inerte, E3 nunca rodou, etc.) foram RE-CONFIRMADOS pelos 4 agents.

### Fase 0 — P14 (dias, barato, destrava a confiança em tudo) → [P14](P14-catraca-floor-morde-no-required.md)

Materializar a órfã no workflow required + fail-red `armed ∧ ¬measured` + counterfactual de full_suite no selftest (40→42) + rename dos **6** required com "(advisory)" no nome (ordem zero-window, 2 flips Wagner). **Decisão Wagner antes do flip:** o fix converte regressão de nightly em red-until-fixed COLETIVO (floor >298 trava merges do repo até descer/subir baseline via PR) — trajetória 274→295→298 torna provável. Quick-wins de carona (mesma janela): **armar `n_quarantine`** (5 medições =27 já commitadas, falta só PR no baseline — anti-mascaramento por quarentena) e **métrica `sqlite_corruptors`** no scorecard (fusão no GT-G3 já required, ~30 LOC + baseline 0 armed + 2 fixtures — em vez de promover gate novo, respeitando a lei de fusões da 0314).

### Fase 1 — Burn-down P04 (relógio real 2-3 sem; decisão Fable 5, fan-out Opus 4.8)

Root-cause REFINADO pós-#3505: a cascata (57% do floor) não é mais era-sqlite (zerado) — é o `migrate:fresh` do RefreshDatabase apagando o seed biz=1 no MySQL persistente (454 FK "Cannot add child row" = 73% das QueryException). **Fase 1.0 — LER o piloto self-heal #3507 antes de qualquer fan-out** (nightly manual 20260701-132941 ainda não aterrissou na órfã; kill-criteria: sem queda medida, não escalar). **1.1** clusters de causa (business/owner 121 · nfe_certificados 22). **1.2** fan-out per-módulo do residual ExpectationFailed (322): tests/Feature raiz 89 primeiro (infra compartilhada) → OficinaAuto 29 → PaymentGateway 20 → KB 17 → NfeBrasil 16 → Financeiro 14 → cauda. 1 módulo/agent, áreas disjuntas, 1 PR ≤300 linhas, floor re-derivado da órfã após cada lote. **1.3** US-GOV-018/020 review→done (falta: owner, anchor `Implementado em:`, evidência medida 1514→298 na §Validação — números da órfã `554047dd06`).

### Trilhas paralelas (chips disparados 2026-07-01, sessões próprias)

- **P10 anchoring** (chip `task_6a900cfd`, sessão Fable 5 + geradores Opus 4.8): 12 lotes, universo **662 US** (717 − 55 gated trilha E: TaskRegistry/Inventory/EvolutionAgent/LaravelAI/MemoriaAutonoma — identidade antes de anchor), ordem valor×buraco (L1 Sells+Financeiro recalibra ambiguidade), fila A6 `_ANCHOR-REVIEW-QUEUE.md` (a criar), kill-criteria §103, charters `related_us` como PR pareado por módulo. Armar `anchor_coverage` JÁ no valor corrente (~16%, 3 medições do cron + PR). Estimativa: ~2-3 semanas civis; ~8-14M tokens.
- **P11 E2b/E3** (chip `task_f2a0ccdb`, Fable 5): **Passo 0 bloqueador** — resolver com evidência a contradição do scheduler Hostinger, porque o cron do distiller **JÁ está descomentado** (`Kernel.php:237-245`, 05:30 `['live']`) sem o gate dry-run→skim ter rodado nunca (0/75 BRIEFINGs com `distilled_at`); se o scheduler estiver vivo → hotfix gateando o bloco. E2b = 3 comandos no container `oimpresso-mcp` + artefato de prova (hits por nome morto antes/depois). E3 = venue clone git + auto-PR draft (nunca a árvore deployada — o comando escreve filesystem sem git), ledger G5, PII manual (motor não pega nomes CRM), guard de shrink (trunca porta em 4000 chars).
- **G7/G8 (P06 residual):** host-cron CT100 + `docker exec oimpresso-mcp ... --input=` (container conecta direto no MySQL prod; a imagem não tem node — gerar o JSON no host). Rejeitadas: scheduler Laravel completo no CT100 (dispararia crons `['live']` em duplicidade — perigo Tier 0) e GH Actions→DB prod (whitelist). Alarme em 3 camadas: wrapper→`mcp_alertas`, check `sdd_snapshot_freshness` no `jana:health-check` (roda no Hostinger, vigia cross-host), stale-guard no `SddBriefLineService` (hoje snapshot morto = silêncio = "estável" disfarçado).
- **P12 recall real:** trocar `environments(['live'])`→`(['staging'])` no recall-eval (espelho do ragas) + read-side novo `measureRecallEval()` (hoje HARDCODED not_yet_measured em `sdd-scorecard.mjs:363-366` — sem transporte staging→git a métrica nunca vive). Ordem obrigatória: **E2b provado ANTES do 1º real-eval** (senão o baseline nasce medindo índice stale).

### Decisões Wagner pendentes (nunca no calado)

1. **R1 × ADR 0314:** promover full-suite a required rema contra "required = só Tier-0". Alternativas: (a) reabrir a 0314 pra R1 quando floor=0×7 noites; (b) **não promover** — floor vive como métrica armada no GT-G3 (que já é required) + alarme alto de staleness. A recomendação técnica é (b)-até-floor-zero, depois decidir.
2. **T1/T2 (mapa teste↔arquivo + lane TDAD): CORTE vs EXECUTAR — agora com escopo real.** A proposta de corte (2026-07-01) apontava "dependência em cadeia, zero artefato"; a cadeia caiu em 2026-07-02 (pcov vivo, 1º clover real na nightly CT100). Escopo honesto pra decidir: [T1](T1-mapa-teste-arquivo-per-test.md) (mapa per-test, 1-1.5d + 7 nightlies, nunca vira required) e [T2](T2-tdad-lite-lane-impactados-pr.md) (lane sombra 14d, FN<1%; flip required SÓ reabrindo a 0314). Ambos `proposed` — nada roda sem OK. Se Wagner mantiver o corte, demover pra feature-wish (ADR 0105) citando os docs.
3. **P14 red-until-fixed coletivo** (Fase 0 acima) — flipar ciente.
4. **Hotfix do cron distiller** se o Passo 0 do P11 provar scheduler vivo.

### Cadência de honestidade

Re-rodar `/sdd-avaliar` ao fechar Fase 0+1 e a cada quinzena (skill `sdd-avaliar`); alvo: composto subir de 67 sem regressão de stream. Nenhum gate promovido com stream <70.

---

## Stream PT — Product Truth (PROPOSTO 2026-07-02 · aguarda Wagner · [ADR 0319](../../../decisions/proposals/0319-product-truth-stream-adversario-modulo-analise.md))

> Origem: Wagner 2026-07-02 ("tenho gaps por módulo? tenho adversário refutador por módulo?" → resposta: gaps profundos só em ~10/78 módulos; refutador por módulo NÃO existe — o skeptic hoje é por stream de governança, não por módulo de produto).

Fecha o loop adversarial na camada **produto/módulo**: as afirmações escritas (BRIEFING/INVENTARIO/SPEC/charters) hoje se propagam pra Jana e pras sessões via MCP **sem ninguém pago pra refutá-las** (caso real: ficha da Jana ficou ~2 meses com "nota 0" e a Jana live em prod — corrigido #3625).

- **Mecânica:** `/pt-avaliar <Modulo>` — F0 extrai claims (determinístico) → F1 skeptic refuta contra código real (`CONFIRMADO`/`STALE`/`ILUSÓRIO`/`FALTA`, prova de checkout obrigatória) → F2 ledger append-only (`governance/pt-ledger/`) + `verified_at`/`claim_accuracy` no frontmatter do BRIEFING → F3 fila Wagner via tasks MCP (nada corrigido no calado).
- **Métricas SEM gate novo (lei 0314):** `pt_claim_accuracy` + `pt_freshness` fundem no GT-G3 required, armadas após 3 medições (ADR 0275).
- **Anti-decadência:** watchdog staleness 30d + counterfactual no gate-selftest (claim falsa injetada TEM que ser pega) + armamento antes de morder.
- **Pré-req:** ajuste no distiller (`jana:distill-module-truth`) pra preservar/consumir `verified_at` + ledger — senão o re-destile apaga o veredicto.
- **Peça B pareada:** agent `analise-refutador` ataca a interpretação do `wagner-understand` ANTES de executar pedido não-trivial (gatilho restrito: Tier 0 / ambíguo / ≥3 arquivos novos; escopo pré-aprovado pula; ambiguidade sobrevivente → 1 pergunta; senão executa R11). Emenda = **R15** (R12 ocupado).
- **Rollout:** Peça A **espera S1+S7 da Onda 1** → piloto Financeiro → mede erro na população de claims de capacidade → **teto ≤10 módulos/quinzena**.
- **Refutação dogfood 2026-07-02:** a própria 0319 passou por 3 skeptics (a Peça B aplicada a si mesma) — pegaram 1 erro factual (R12 ocupado) + duplicações (G5 já refuta lote IA; `distiller_freshness`/`sdd-verification-ledger` já existem) + tensão com a 0314 (armar doc-quality no required). **11 emendas vinculantes E1-E11 no ADR.** Núcleo sobreviveu; forma original morreu.
- **Status:** `proposed` — nada roda sem OK. Armamento das métricas = checkbox Wagner separado do aceite.

---

## Stream MV — Módulo Vivo (APROVADO Wagner 2026-07-05 · fases MV2+ aguardam OK por fase)

> Origem: Wagner 2026-07-05 — *"acho que eu preciso de uma máquina séria muito bem planejada para cuidar disso para mim"* + *"o software deve amadurecer e se tornar vivo por módulo"* + *"especificações e memórias e tarefas tudo integrado"* + *"interagir com scorecards das telas"*. Pesquisa + desenho completo: [arte 2026-07-05](../../../sessions/2026-07-05-arte-maquina-governanca-telas.md) (Meta ACH mutation-oracle · Spec Kit · Playwright Test Agents · Infection MSI · fitness functions). Decisão de plug: stream aqui no roadmap SDD (AUTOMATION-ROADMAP está `arquivado`; 1 tema = 1 doc vivo).

Visão: cada módulo como organismo vivo — **escada M0→M3** (critérios objetivos, casa com module-grade v4 buckets + ADR 0105 sinal-cliente) · **sinais vitais** que DEGRADAM por frescor (nunca verde-stale) · **metabolismo** (cron com batimento por criticidade) · nascimento (`criar-modulo`) e morte (`deprecar-modulo`) conectados. Wagner só nos gates humanos: aprovar batch de tasks, gate visual de tela, promoção de nível.

| # | Fase | Estado | Entrega |
|---|---|---|---|
| [MV1](MV1-espinha-dorsal-vital-signs.md) | Espinha dorsal dos sinais vitais | ✅ executado 2026-07-05 | `scripts/qa/vital-signs.mjs` + selftest contrato + `vital-signs.json` (snapshot) + `vital-signs-history.jsonl` (trend append-only). Advisory por lei (0314) — leitura, não gate. 1º run: 234 telas · 219 scorecard · 25 casos.md · 15 stale; topo da fila = dinheiro sem prontuário (Impostos/ProvaViva 600) |
| [MV2](MV2-cron-metabolismo.md) | Cron-metabolismo (nightly GH Actions — determinístico; sessões de execução é que são agênticas) | ✅ executado 2026-07-05 | `mv-metabolismo.mjs` (seletor: gate-pendente-não-empilha → verde+fresca-pula → batimento 1d/3d/7d → budget 5) + workflow nightly 06:30 BRT com auto-PR `mv-batch` **SEM auto-merge** (merge Wagner = aprova batch) + 19 checks de contrato. 1º batch real: 2026-07-06 (5 telas, Impostos/ProvaViva no topo) |
| MV3 | Fechar casos.md no caminho do dinheiro + **fila de prontidão do protótipo** | 🟡 em curso | contrato-first: Sells/Financeiro/RecurringBilling/NfeBrasil/Fiscal — 1 sessão/tela via screen-qa-specialist. `scripts/qa/prototipo-readiness.mjs` (2026-07-06) responde "aplicar sem quebrar?" derivando da blindagem — aposenta a fila manual `TELAS_REVIEW_QUEUE.md`. 1º run: 3 prontas (Impostos/ProvaViva/Unificado), 6 em 1-ciclo (Compras/Cobranca/Conciliacao/Dre/Fluxo/PaymentGateways — todas faltam casos.md-com-UC). **Boost (Wagner 2026-07-06):** o metabolismo MV2 multiplica ×1.6 a prioridade das telas 1-ciclo de protótipo (blindá-las desbloqueia a aplicação do visual) — a nightly gera `prototipo-readiness.json` e o `mv-metabolismo` re-ordena a fila; ação proposta marca "🎨 desbloqueia aplicação do protótipo" |
| MV4 | Mutation-oracle cirúrgico (Infection) no código Tier-0 | 🔜 | MSI ratchet em `num_uf`/FSM/cálculo de valor — o juiz do juiz (anti gate-teatro); roda no CT100; métrica funde no GT-G3 (lei 0314, sem gate novo) |
| MV5 | E2E Playwright em escala (UCs críticos, self-healing) | 🔜 depende MV3 | UC vem do casos.md — nunca do código (anti-tautologia §Ideias descartadas) |
| MV6 | Escada M0→M3 + sinais vitais renderizados (BRIEFING/cockpit) | 🔜 depende MV1 | posição na escada por módulo + prontuário visível; promoção de nível = evidência scorecard + OK Wagner |

- **Anti-morte conhecida:** (a) gate-teatro → mutation é o juiz (MV4); (b) frota de testes tautológicos → asserção SEMPRE cita âncora de contrato (casos/SPEC/ADR/charter), barrada no gate pré-adoção; (c) verde-stale → frescor degrada sozinho no MV1.
- **Custo:** tela verde+fresca PULA o ciclo; budget noturno com parada; trabalho de fundo (casos.md) é finito → vira manutenção incremental.
