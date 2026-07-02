---
date: "2026-07-01"
topic: "Avaliação adversarial do programa SDD (7 skeptics) — 2 runs no dia: 67/100 manhã (achou floor=298 inerte no required) → P14 fechou o buraco → 75/100 noite; padrão residual = medido-não-armado (anchor_coverage stale, trilhas C+D sem medição real)"
authors: [C]
type: avaliacao-adversarial-processo
metodo: "workflow sdd-avaliador-processo (8 agents, 1 skeptic por stream, verificação LIVE em git+gh+CT100, não no plano)"
gatilho: "Wagner — 'quero que avalie o plano sdd, e como ficou' → skill sdd-avaliar"
score_composto: 67
score_medio: 67
adrs_citados: [0273, 0275, 0279, 0303, 0306, 0307, 0312, 0314, 0318, 0093, 0062]
run_id: wf_1295252f-020
tokens_subagentes: 1013732
reavaliacao_pos_p14:
  score_composto: 75
  score_medio: 73
  run_id: wf_ec10ba07-13f
  tokens_subagentes: 1070714
  gatilho: "Wagner — /sdd-avaliar (noite, pós-P14 + batches P10/P11 + #3567)"
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0306-strangler-spec-anchored-reconstrucao-sdd
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Scorecard SDD — Avaliação Adversarial (2026-07-01)

**Composto ponderado: 67/100** · média simples 67 · pesos: FV/Fase2b ×1.8, SA/GT ×1.3, demais ×1.0
**Trajetória:** 61.9 (15/jun) → 46 (18/jun, nightly morta) → 65.2 (20/jun) → 60 (21/jun) → **67 (01/jul)** — subindo, primeira vez com dentes required SDD vivos.

> Lente única do veredito: **a fundação virou lei que morde (GT-G3 required + selftest 40/40); mas o número mais caro do programa (floor=298) está armado só no papel — o required que deveria defendê-lo não enxerga a fonte e pula em silêncio.**

## 1) Scorecard por stream

| Stream | Score | Δ vs 21/jun | Status macro | Maior risco sistêmico |
|---|---:|:---:|---|---|
| **GT — Governance scorecard** | **85** | +19 | Dentes G3 (required, morde) e G6 (selftest 40/40) provados live | Nightly CT100 >48h parada → métrica mais pesada DESARMA silenciosa; gate segue verde vigiando só 2 números baratos |
| **SA — Anchors spec↔código** | **82** | +30 | Fundação+gate F2 (no-new-lie) sólidos; escala parcial (coverage 16.1%) | "Gate required verde" confundido com "spec ancorada" — 84% das US em vácuo (`sem_campo` não conta como mentira) |
| **KL — Knowledge/ghost/decay** | **70** | +18 | Catraca anti-ghost morde (ghost=8 armado via ratchet required); execução scaffold | `retrieval_enabled=true` global sobre índice SEM prova de re-seed E2b + context_recall real 0.38 — e os 3 alarmes que pegariam isso estão `not_yet_measured` |
| **Charters + fluxo-novo** | **70** | +2 | component/page 97%; `us:` 14%, adoção fluxo-novo = 1 SPEC | Fundação declarável "feita" pela existência dos artefatos enquanto `anchor_coverage` não tem catraca armada (1/3 medições) |
| **FV — Full-suite/testes** | **63** | −13 | Medição honesta (floor 298 reproduzido live); catraca-mãe ilusória | **Floor=298 `armed:true` NÃO morde no required** — `nightly-floor.json` gitignored/ausente no checkout do PR → ratchet pula em silêncio |
| **Fase 2b — P0 harness** | **62** | −9 | Harness A/B provado no junit (greps=0); lever intocado | Catraca re-armada no PIOR valor (274→298) legitima regressão; P03 declarado "executed" com 12 corruptores tier-A VIVOS |
| **Sem 4-6 — Promoções** | **36** | +21 | 1 de 6 promovida (GT-G3, verificada) | Ilusão de enforcement do R1: `armed` num JSON ≠ suíte defendida em CI; mesmo padrão ameaça C2 quando nascer |

## 2) Achado central (defeito nº 1 — reproduzido por 2 skeptics independentes)

**A catraca armada do floor=298 é teatro no check required.** O baseline (`governance/sdd-scorecard-baseline.json`) diz `armed:true value:298` e o `sdd-scorecard.json` commitado exibe `measured:298` — aparência de floor governado. Mas o check required `SDD scorecard ratchet (GT-G3)` recomputa via `measureFullSuiteFloor()` lendo `governance/nightly-floor.json`, que é **gitignored** (`.gitignore:97`) e **ausente no checkout do CI de PR**. Resultado (simulado 2×): `not_yet_measured` → `sdd-scorecard.mjs:384-390` faz `continue` sem barulho → **nenhum PR que regrida a suite é bloqueado hoje**. O step que materializa a branch órfã existe SÓ no workflow ADVISORY (`sdd-scorecard.yml:78-93`), não no ratchet required (44 linhas, sem fetch). E o `gate-selftest.mjs` (40/40) não tem counterfactual de `full_suite` — a auto-prova nunca exercita a única métrica cara.

**Fix barato (dias):** (a) adicionar o fetch da órfã ao `sdd-scorecard-ratchet.yml` como o publish faz, OU fail-red quando métrica `armed:true` vier `not_yet_measured`; (b) counterfactual de full_suite no gate-selftest. Converte o melhor sinal do programa de relatório em catraca.

## 3) FEITO-VERIFICADO × ILUSÓRIO × FALTA

### Confiável (provado ao vivo)
- **GT-G3 ratchet required (96)** — morde: selftest bad→exit 1, verde 15/15 runs, `enforce_admins:true`.
- **ADR 0273 + 0275 (95)** — leis que o código consome literalmente (regex do §1 é o do anchor-lint).
- **G6 gate-selftest 40/40 (93)** — "boa passa, ruim falha pelo motivo certo"; mas é advisory e testa a mecânica, não a config viva.
- **Anti-ghost catraca (92)** — ghost=8 por módulo, armado, mordendo via ratchet required.
- **F3 nightly CT100 + floor por interseção (90)** — 298 reproduzido live por skeptic independente.
- **Harness A.1/B (92/90)** — `mysql: not found`=0, `3140`=0 no junit real de 20260701.
- **Refutações corajosas** — A.2 FK-off revertido por net-harmful (provado por reprodução), A.3 DoD-teatro morto, D1 mock-tautologia rebatizada; baseline RAGAS real honesto (faithfulness 0.69, context_recall 0.38, ADR 0318).

### Ilusório (parece feito; não morde ou nunca rodou)
- **Catraca floor no required (30)** — defeito central acima.
- **C2 coverage (30)** — `coverage_pct` nunca teve 1ª medição (falta 1ª nightly-coverage publicada).
- **E3 destilação (30)** — 0/90 BRIEFINGs com `distilled_at`; dry-run+skim Wagner (pré-condição do Kernel) nunca executado. Cron agendado ≠ cron rodado.
- **E2b re-seed Meilisearch (45)** — zero artefato provando execução no CT100; busca da Jana pode servir nomes mortos.
- **G7/G8 snapshot+brief (68/66)** — código+Pest ok; `environments(['live'])` sem prova de row real/linha no brief de hoje (decisão Wagner 2026-07-01: cron vai pro CT100, não Hostinger).
- **P03 bookkeeping** — roadmap diz "executed / tier-A=0", mas auditor live acha **12 corruptores tier-A vivos** (TeamMcp/Forja*, Jana/TaskRegistry, Brief/LeaseBrief, PaymentGateway/RetryOrphan...).

### Falta (não começou / bloqueado)
- **T1 mapa teste↔arquivo (5) e T2 lane TDAD-lite (5)** — blueprint puro.
- **Frente C / US-GOV-021** — o lever de ~57% do floor (QueryException de conexão MySQL compartilhada) intocado; US-GOV-018/020 em `review` sem owner.
- **SA-A5/A6** — 717/855 US (84%) `sem_campo`; dívida ativa `req_sem_covering_test=45` + `req_sem_aceite=27` só grandfatherada.
- **Floor não converge:** 274→295→298 (subiu durante o "burn-down"); skipped ~24-26% com `n_quarantine armed:false` (mascaramento por quarentena possível).

## 4) O que falta de ondas

| Onda | Rodou? | Resta |
|---|---|---|
| **Semana 0** (leis+template+backfill mecânico+agregador) | ✅ SIM | `us:` nos charters (14%) |
| **Semanas 1-2** (gates advisory+selftest+harness+anti-ghost+floor) | ✅ majoritariamente | fazer a catraca do floor morder no required |
| **Semanas 2-4** (escala: batch IA, burn-down, destilação, trilhas reais) | ⏸️ **MAL COMEÇOU — o programa está parado AQUI** | 717 US sem_campo · Frente C (57% do floor) · 0 destilações · 6/12 métricas `not_yet_measured` |
| **Semanas 4-6** (promoções R1·C2·T1·T2·SA-A10·GT-G3) | 1 de 6 (GT-G3) | R1 exige floor=0×7 noites (hoje 298, subindo); C2 sem 1ª medição; T1/T2 zero; SA-A10 exige 100% (hoje 11-16%) |

**Caminho crítico:** (0) fix da catraca do floor no required [dias, barato] → (1) US-GOV-018/020/021 review→done + Frente C era-sqlite [derruba ~57% do floor] → (2) P04 burn-down floor→0 + 7 nightlies verdes [relógio real] → (3) R1 flip → (4) 1ª nightly-coverage → C2 → T1 → T2. **Paralelo:** SA-A5/A6 batch IA → SA-A10; E2b re-seed provado + E3 dry-run → destrava KL. Nota: promoções C2/T1/T2 remam contra a política vigente da ADR 0314 ("required = só Tier-0") — reabrir a 0314 antes de promover, não mergear no calado.

## 5) TOP 5 riscos sistêmicos

1. **Teatro de gate no número mais caro:** floor=298 `armed:true` mas inerte no único required que o leria (fonte gitignored ausente no checkout). Nenhum PR que regrida a suite é bloqueado hoje.
2. **Desarme silencioso por máquina externa:** nightly CT100 >48h parada → métrica desarma por design, gate segue verde. Mesmo padrão em G7/G8 e ameaça C2. O canário para de cantar e ninguém ouve.
3. **Gate verde ≠ spec verdadeira:** anchor-gates required são no-new-lie diff-only a 16.1% de cobertura; dashboard "required+verde" induz leitura "spec-anchored atingido" quando o real é "F2 atingido, F3 longe".
4. **Read-path da Jana sobre memória não-validada:** `retrieval_enabled=true` global + re-seed E2b sem prova + context_recall 0.38 + hybrid OFF (ADR 0312) — e os 3 alarmes estão `not_yet_measured`.
5. **Bookkeeping mente vs realidade medida:** P03 "executed" com 12 tier-A vivos; catraca re-armada no pior valor (298 vs 274); skipped ~25% sem `n_quarantine` armado.

## 6) Veredito

**No caminho, na metade honesta: 67/100 (subiu de 60).** A fundação (Semanas 0/1-2) é real e rara — leis canônicas consumidas literalmente pelo código, selftest 40/40, floor por interseção reproduzível, refutações corajosas registradas. O salto 60→67 veio de dentes reais: GT-G3 required mordendo, anti-ghost armado, anchor-gates F2 na lei, baseline RAGAS real honesto. **Mas o programa está parado na transição fundação→escala** (ondas 2-4 mal começaram) e o achado mais grave é o defeito nº 1: o único número caro e honesto do sistema está armado apenas no papel. **Maior alavanca, nesta ordem:** (a) fix de dias no `sdd-scorecard-ratchet.yml` (materializar a órfã + fail-red em armed∧not_yet_measured + counterfactual no selftest); (b) Frente C / era-sqlite (US-GOV-021) — sozinha ataca ~57% do floor e destrava P04→R1, o gargalo de todo o calendário.

---

## Errata (2026-07-01, mesmo dia — verificada por reprodução)

O achado **"P03 declarado 'executed' com 12 corruptores tier-A VIVOS"** (§3 Ilusório, §5 risco 5, e o trecho correspondente do stream Fase 2b) é **FALSO-POSITIVO de checkout stale**: o skeptic rodou `scripts/audit/sqlite-test-corruptors.mjs` no repo principal `D:\oimpresso.com` @ `0b59ec3dc9` (#3412, 114 commits atrás de origin/main, ANTERIOR ao #3445 que guardou os 12), apesar de declarar "verificado em origin/main". Reproduzido em snapshot limpo de `origin/main` (`dd3ed7c311`): auditor dá **0 corruptores** (default e `--strict`, exit 0). **O bookkeeping do P03 estava CORRETO.** Os demais achados (catraca do floor inerte no required, E3 nunca rodou, E2b sem prova, etc.) foram RE-CONFIRMADOS por verificação independente. Defesa incorporada: skeptics passam a provar `HEAD == origin/main` antes de claims "live" (guard no workflow `sdd-avaliador-processo` — é a mesma classe do falso-blocker MSYS de 2026-07-01: ferramenta rodando em contexto errado fabrica achado). Detalhe no [_ROADMAP §Errata](../requisitos/_Governanca/roadmap/_ROADMAP.md).

---

# Reavaliação (mesma data, noite — pós-P14 + batches P10/P11): composto 75/100

**Run 2 do dia** (`wf_ec10ba07-13f`, 8 agents, 1.070.714 tokens, 254 tool calls, ~22min), rodada DEPOIS do P14 (#3548..#3567), dos batches de anchoring P10 (#3539-#3546) e do KL P11 (E2b/E2c/E3 lote 1). **Composto ponderado 75/100** (média simples 73) — pesos iguais ao run 1 (FV/Fase2b ×1.8, SA/GT ×1.3, demais ×1.0).

**Trajetória atualizada:** 61.9 (15/jun) → 46 (18/jun) → 65.2 (20/jun) → 60 (21/jun) → 67 (01/jul manhã) → **75 (01/jul noite, pós-P14)**.

> Lente única do run 2: **o defeito nº 1 do run 1 (floor=298 inerte no required) foi FECHADO e provado — P14 materializa a órfã no ratchet required + fail-red em armed∧¬measured + counterfactuals no gate-selftest (agora 46/46). O padrão residual dominante mudou de "gate que não morde" para "medido, não armado": anchor_coverage desprotegida no agregado e trilhas C+D wired sem 1 medição real voltando pro scorecard.**

## R2.1) Scorecard por stream

| Stream | Score | Δ vs manhã | Status | Maior risco |
|---|---:|:---:|---|---|
| **GT — Governance scorecard** | **88** | +3 | 8/8 passos vivos e mordendo | Floor=298 vem de branch órfã não-protegida + cron CT100 (fonte fora do perímetro do repo) |
| **Fase 2b — P0 harness** | **82** | +20 | Frentes A/B/C verificadas; burn-down é piloto | Efeito do P04 no floor ainda NÃO medido (landou depois do último nightly) |
| **SA — Anchors spec↔código** | **80** | −2 | Catracas diff-aware mordem (provado); F3 longe | `anchor_coverage` **armed=false, baseline stale 5.4% vs live 42.6%** — regressão agregada passa livre |
| **Charters + fluxo-novo** | **79** | +9 | `component:` 144/144; `related_us` só 16.7% | charter-us-lint advisory, sem self-test, com no-op silencioso se BASE_REF falhar |
| **FV — Full-suite / testes** | **78** | +15 | Floor armado+required (P14); harness em review | ~688 erros de env misturados no floor — burn-down vira caça-fantasma se começar antes |
| **KL — Knowledge / decay** | **74** | +4 | Ghost ratchet armado; trilhas C+D wired mas vazias | **peso_real ligado em prod (Tier 0) sem medição real de recall fechando o loop** |
| **Sem 4-6 — Promoções** | **28** | −8 | 1 de 6 promoções (só GT-G3) | C2/T1/T2 aparentam completude mas dependem de pcov/clover que **nunca rodou** no CT100 |

(Δ por stream entre runs é indicativo — skeptics independentes; o comparável é o composto 67→75.)

## R2.2) O que mudou desde a manhã (fechamentos verificados)

- **Defeito nº 1 FECHADO (P14):** `sdd-scorecard-ratchet.yml` agora materializa `governance/nightly-floor` ANTES do ratchet; `sdd-scorecard.mjs:430` avermelha `armed∧¬measured` (fail-closed). Skeptic simulou floor 298→350 → `🔴 RATCHET (ARMADA)` exit 1, e floor-ausente → red. Counterfactuals no gate-selftest: 40/40 → **46/46**.
- **E2b re-seed Meilisearch: de ilusório (45) pra provado (86)** — `governance/reseed-meilisearch-manifest.json` append-only com taskUids reais (1138911 rollback, 1138931 settings-cure), índice 1415/1415 embedded. Honesto: "antes==depois" (o valor foi a PROVA, não a cura).
- **E3 1ª destilação real** — 5 BRIEFINGs com `distilled_at: 2026-07-01` (#3560, skim Wagner + 4 rodadas refutador). Era 0/90 de manhã; cron segue comentado (71/76 portas sem destilar).
- **Anchor coverage 16.1% → 42.6%** (batches P10 #3539-#3546, ambiguidade agregada <1% em 5 módulos, fila Wagner materializada #3549). `related_us` charters 14% → 16.7%.
- **RAGAS real rodou 1×** — faithfulness 0.6916 / relevancy 0.8039 / **context_recall 0.3839** (baseline honesto, ADR 0318). Mas o scorecard `ragas_real_uptime` segue not_yet_measured e a fonte ainda diz "mock com mock" — não reconciliado.
- **P04 piloto landou** (#3507, self-heal tenant biz=1) — efeito no floor só aparece no próximo nightly CT100.

## R2.3) Ilusório remanescente (padrão "medido, não armado")

- **anchor_coverage no scorecard (35)** — armed=false, n=1, baseline stale 5.4% vs live 42.6%. Os gates required são diff-aware/no-new-lie; a cobertura AGREGADA pode despencar sem CI vermelho (cron full-tree só reporta exit 0). *Maior catraca-que-não-travou do programa. Fix quase grátis: 3 re-medições → armar.*
- **C2 coverage (18) / T1 (5) / T2 (5)** — read-side pronto no scorecard cria ilusão de completude; **zero clover.xml em 4 nightlies verificadas no CT100** — pcov nunca foi instalado. "Feito que depende de algo que nunca rodou."
- **Trilhas C+D (recall-eval + RAGAS)** — schedules wired no Kernel (487/492) mas `recall_eval_violations` e `ragas_real_uptime` seguem not_yet_measured; peso_real.retrieval_enabled já flipado true em prod (Tier 0) sobre retriever com recall real 0.38, sem catraca viva de regressão.
- **Nome `full_suite_pass_rate`** — mede arquivos-que-falham (298), não taxa de aprovação. Nightly real: 336 failed + 784 errors (7064/10973 passando). Risco de leitura futura "suite sob controle" → promoção prematura de R1.
- **gate-selftest advisory** — 46/46 mordem, mas a prova não bloqueia merge.

## R2.4) O que falta de ondas (estado noite)

| Onda | Rodou | Resta |
|---|---|---|
| **Sem 0** | ✅ 100% | Higiene: corpo ADR 0273 diz "Proposto" (frontmatter aceito); título ADR 0274 diz 13 colisões (são 14) |
| **Sem 1-2** | ✅ ~85% | US-GOV-018/020 presas em *review*, 021 *doing*; ~688 erros de env no floor |
| **Sem 2-4** | 🟡 ~40% | 491 US sem_campo; 120 charters sem related_us; burn-down B1-B4 inteiro; E3 cron comentado |
| **Sem 4-6** | 🔴 1/6 (GT-G3) | R1 inatingível hoje; C2/T1/T2 sem pcov; SA-A10 F3 precisa 100% (live 42.6%) |

**Caminho crítico (serial):** fechar 018/020/021 → próximo nightly mede efeito do P04 (298→?) → fan-out burn-down B1-B4 → floor→~0 → R1. **Paralelo 1 (1 ação de infra destrava 3 promoções):** instalar pcov na imagem CT100 → clover → C2 → T1 → T2. **Paralelo 2:** batches A5/P10 até 100% → SA-A10 F3. **Barato e urgente:** re-medir anchor_coverage 3× e ARMAR.

## R2.5) Top 5 riscos sistêmicos (run 2)

1. **Cobertura agregada de anchors desprotegida** — anchor_coverage armed=false + baseline stale; PR que quebre campos em massa fora dos SPECs que edita derruba a cobertura sem avermelhar nada.
2. **Decay ligado em prod sem medição real (Tier 0)** — peso_real true sobre retriever com context_recall 0.38; recall-eval e RAGAS not_yet_measured. Se degradar o chat da Jana, nada avermelha.
3. **Fonte do floor fora do perímetro** — órfã não-protegida (qualquer push reescreve) + cron CT100; P14 fecha o buraco de AUSÊNCIA, não o de órfã congelada/reescrita (frescura computed_at não é gate-checada).
4. **Ilusão de completude C2/T1/T2** — o padrão que o programa caça, dentro do próprio programa.
5. **gate-selftest advisory + nome enganoso full_suite_pass_rate** — a prova das catracas não bloqueia merge; o nome pode induzir promoção prematura de R1.

## R2.6) Veredito (run 2)

**No caminho, 75/100 (subiu de 67 no mesmo dia).** O salto veio de fechamento REAL: P14 converteu o achado mais grave da manhã em catraca provada (floor fail-closed + counterfactuals), E2b ganhou prova dura, E3 saiu do zero, anchoring 16→42.6%. A espinha anti-teatro morde comprovadamente (GT-G3 required com dentes, refutador com reprovações reais, floor por interseção). O padrão de falha residual é consistente em 4 dos 7 streams: **"medido, não armado" / "wired, não fechado"**. **Maior alavanca:** US-GOV-018/020/021 review→done + 1 nightly (destrava o caminho crítico inteiro e limpa ~688 erros de env do floor). **Segunda, quase grátis:** armar anchor_coverage (3 re-medições) + instalar pcov no CT100 — juntas convertem 4 "ilusórios" em catracas reais.

---
_Run 1 `wf_1295252f-020` · 8 agents · 1.013.732 tokens · 248 tool calls · 14.5min. Run 2 (pós-P14) `wf_ec10ba07-13f` · 8 agents · 1.070.714 tokens · 254 tool calls · ~22min. Skeptics verificaram em origin/main + gh api + CT100 live (não no plano); run 2 com guard anti-checkout-stale (worktree limpa de origin/main). Avaliações anteriores: [21/jun 60/100](2026-06-21-sdd-avaliacao-adversarial.md) · [20/jun 65.2](2026-06-20-sdd-avaliacao-30threads.md) · [18/jun 46](2026-06-18-sdd-avaliacao-adversarial-scorecard.md) · [15/jun 61.9](2026-06-15-sdd-avaliacao-adversarial-scorecard.md)._
