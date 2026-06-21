---
date: "2026-06-19"
topic: "Refutação adversarial (G5) da tese 'já existe TUDO pra unir o programa SDD com a memória-unificada (ADR 0270) num só sistema — basta rodar a memória como stream do SDD'. Veredito: FALSA com alta confiança. Prontidão real da união ~33/100."
authors: [C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0279-sdd-medir-governar-floor-nightly", "0061-conhecimento-canonico-git-mcp-zero-automem", "0130-handoff-append-only-mcp-first"]
prs: []
---

# Refutador G5 — a união SDD × memória-unificada "já existe"? (sessão fresca, default cético)

> Ref de verificação: `origin/main` @ `5f3247078b69` (2026-06-19 02:12). Tudo abaixo é estado VIVO em
> origin/main NOW, não plano/promessa. Regra anti-stale aplicada: doc que promete ≠ peça implementada.
> Tese sob teste (dono): *"Já existe TUDO pra unir o programa SDD (plano 2026-06-12 + ADRs 0270/0271)
> com a memória-unificada (0270 D-1/D-2 'porta única tipada') num só sistema — bastando rodar a
> memória como um stream do SDD."*

## VEREDITO: **TESE FALSA — confiança ALTA (~90%)**

A tese cai por **três pernas independentes**, qualquer uma já bastando:

1. **O motor da memória-unificada (0270) NÃO existe** — a "porta única mutável + destilada"
   depende do distiller módulo-verdade (F3), do `distiller_freshness` (F2/D-5) e do archive (F5).
   **Nenhum dos três está implementado.** O que existe da memória é a camada RASA (cobertura de
   porta + nomes-fantasma + time-decay no chat), não o ciclo "diário → manual" que o 0270 define.
2. **Não há "stream MEM" no SDD** — o avaliador SDD tem 7 streams (SA/FV/KL/GT/CH/F2b/PROM).
   **Nenhum** trata a memória-unificada (0270) como parte do SDD. KL ≠ 0270: KL é ghost-names +
   front-door-coverage; o engine 0270 (destilação/decay/archive/porta mutável) **não é stream
   nenhum nem está no scorecard**. São dois programas com **sobreposição parcial**, não um.
3. **Não há "um só sistema"** — existem **4 famílias de scorecard paralelas** e **≥7 índices-mestre
   concorrentes**; a composta SDD **nem é calculada** (6/10 métricas `not_yet_measured`); o sinal
   vivo do SDD (nightly) está **morto há 3 dias** e nenhuma catraca regrediu. Não há a "cola" única.

Importante para a honestidade: **um dos pilares da DÚVIDA da própria tese caiu a favor dela** — o
suposto "conflito append-only × verdade-mutável" **NÃO é real** (ver §"o que de fato existe"). Ou seja:
a tese não falha por conflito; falha por **peças que ainda não existem** + **falta de cola**.

---

## Top 8 gaps que REFUTAM "já existe tudo"

### G1 — Distiller módulo-verdade (0270 F3) — **PROMETIDO-MAS-NÃO-EXISTE**
0270 D-3/F3 é o coração do "diário → manual": estender o `ProfileDistiller` de "fatos do chat" →
"verdade atual do módulo" (auto-atualizar `BRIEFING.md` a partir de sessions/handoffs/PRs) e tornar
`brief-update` **obrigatório e auditável**.
- `Modules/Jana/Services/Memoria/ProfileDistiller.php` destila **só** perfil comercial por business
  (faturamento 90d, top clientes, metas) → tabela `jana_business_profile`. **Nunca lê sessions/
  handoffs/PRs; nunca lê nem escreve `BRIEFING.md`.**
- `git grep BRIEFING` em `*.php`/`scripts` → só **leitores** (testes de saturação Wave + link no
  ModuleGradeController). **Zero escritores.** Nenhum job/cron regenera BRIEFING.
- `app/Console/Kernel.php` agenda `brief:generate` (Daily Brief global, ADR 0091) — **não** o BRIEFING
  por módulo. `brief-update` segue best-effort (skill Tier B), não obrigatório/auditável.
- O próprio 0270 marca F3 como roadmap "Bloqueio: CT 100" (linha 150). **Sem este motor, não há
  "verdade atual destilada" pra rodar como stream de nada.**

### G2 — `distiller_freshness` (0270 D-5 / F2) — **PROMETIDO-MAS-NÃO-EXISTE**
0270 D-5 promete 3 checks DUROS no `jana:health-check` (`front_door_coverage`, `read_path_hops`,
`distiller_freshness`).
- `Modules/Jana/Console/Commands/HealthCheckCommand.php` roda 16 checks; **nenhum** é
  `front_door_coverage`, `read_path_hops`, `distiller_freshness` nem `verificacao_sdd`.
- `distiller_freshness` aparece **só** no texto do ADR 0270 (linhas 121/148/158). `git grep` em todo
  origin/main → **0 hits em código/teste.** Não existe métrica, alarme, nem health-check.
- `StalenessDetectorService.php` existe mas mede staleness das **rows de `mcp_memory_documents`**
  (idade de `indexed_at` + drift DB↔git) — **não** "dias desde a última destilação por porta".
- `verificacao_sdd` (linha-SDD prometida no brief-fetch + health-check, plano §2) existe **só** no
  texto do plano. **0 hits em código.**

### G3 — `full_suite_pass_rate` hardcoded `not_yet_measured` (elo MEDIR→GOVERNAR aberto) — **PROMETIDO-MAS-NÃO-EXISTE / FALTA-COLA**
A métrica-mãe do SDD não tem valor.
- `scripts/governance/sdd-scorecard.mjs:112` hardcoda `full_suite_pass_rate = notYet(...)` com
  comentário que o próprio **ADR 0279** chama "**factualmente falso**" (CT100 já salvou ~15 runs).
- `governance/nightly-floor.json` **não existe** em origin/main (`git ls-tree` → ausente). O transporte
  CT100 → repo (ADR 0279 Opção A, PR-2) **não foi feito**. Read-side rastreado em US-GOV-023, write-side
  "em aberto no ledger".
- Consequência: **toda a Semana 4-6 (promoções a required) repousa sobre número inexistente.**

### G4 — Nightly morto há 3 dias + 0/18 required são gates SDD — **CONFLITO/ILUSÓRIO**
- Avaliação adversarial 2026-06-18 (`memory/sessions/2026-06-18-sdd-avaliacao-adversarial-scorecard.md`):
  nightly full-suite — **único sinal vivo** — MORTO 16/17/18-jun por
  `PHP Fatal: Cannot redeclare insertAuditLog()` (colisão `Modules/Arquivos/.../AuditLogCommandTest.php:41`
  × `Modules/Jana/.../ImmutabilityTriggersTest.php:122`). `junit.xml` = 0 bytes → zero medição.
  **É o mesmo bug FV-F1 que a onda dizia ter resolvido**, e **nenhuma catraca ficou vermelha** porque
  `not_yet_measured` nunca regride.
- Todo o aparato SDD é **advisory** (`continue-on-error:true` em `sdd-scorecard.yml`/`protection-drift.yml`).
  **0/18 required são gates SDD.** Já houve PR mergeado com anti-ghost VERMELHO. O sistema roda "verde"
  sem mover comportamento. Score SDD honesto mais recente = **46/100** (caiu de 61.9).

### G5 — Nenhum "stream MEM"; KL ≠ engine 0270 — **FALTA-COLA**
- Streams do avaliador SDD: **SA, FV, KL, GT, CH, F2b, PROM**. Nenhum é "MEM"/"memória-unificada".
- KL = "Knowledge/ghost/decay" cobre **só** `ghost_count` (nomes `Modules/X` inexistentes) +
  `front_door_coverage` (BRIEFING presente). **Não** cobre destilação (F3), archive (F5),
  `distiller_freshness`, nem a disciplina de "porta única mutável" (D-2). O engine 0270 **não é stream
  nem está no scorecard**. "Rodar a memória como stream do SDD" exigiria criar um stream que hoje não
  existe e cujo motor (G1/G2) não está construído.
- Os 4 workflows do avaliador (`.claude/workflows/sdd-*.js`) e a skill `.claude/skills/sdd-avaliar/SKILL.md`
  estão **VAZIOS (0 bytes) em origin/main** — stubs staged. O protocolo dos 7 skeptics vive no plano +
  ADR 0275, não em código executável versionado em main.

### G6 — Quatro famílias de scorecard paralelas; composta SDD nem é calculada — **DUPLICADO / FALTA-COLA**
Longe de "um só sistema":
- **(1)** SDD scorecard — `governance/sdd-scorecard.json` (10 métricas, ADR 0275).
- **(2)** Module-grades — `governance/module-grades-baseline.json` + ADRs 0153-0159 + `module-grades-gate.yml`.
- **(3)** Governance-V4 / screen-grade — `memory/governance/scorecards/**` (~250 YAMLs) + ADRs 0160/0230/0236.
- **(4)** `.claude/governance-eval/scorecard.mjs` (eval próprio).
- **Nenhuma soma as duas famílias (SDD + memória) numa composta única.** Pior: a composta SDD
  `sdd_score_v1/v2` é **explicitamente NÃO calculada** enquanto houver `not_yet_measured` —
  e **6/10 métricas estão `not_yet_measured`** (`full_suite_pass_rate`, `coverage_pct`,
  `recall_eval_violations`, `ragas_real_uptime`, `drift_alarms`, `backfill_error_rate`).
  Os números 61.9/46 saem de session logs do avaliador (cálculo manual por run), **não do JSON**.
- `read_path_hops` (métrica-chave de leitura do 0270) É computado em `knowledge-drift.mjs:166-224`
  (mediana, meta 1) mas **NÃO é uma das 10 métricas** e **não entra no `sdd-scorecard.json`** — fica
  como print de console, ungoverned.

### G7 — ≥7 índices-mestre concorrentes (porta única 0270 D-2 não atingida no global) — **DUPLICADO**
0270 D-2 quer porta única por assunto. No nível de índice-mestre, coexistem competindo:
`memory/INDEX.md`, `memory/INDEX_TEMATICO.md`, `memory/decisions/_INDEX-GENERATED.md`,
`memory/decisions/_INDEX-LIFECYCLE.md`, `memory/requisitos/INDEX.md`, `memory/modulos/INDEX.md`,
`memory/reference/_INDEX.md`. (Por módulo a porta foi resolvida — `front_door_coverage=100%` — mas a
"verdade já mastigada" do D-2 é frágil: o avaliador 2026-06-18 nota `front_door=100%` com **11
tombstones/lápides** contadas como porta, i.e. cobertura de FORMA, não de conteúdo destilado.)

### G8 — Auto-mem NÃO está morta (0061 incompleto) — **PROMETIDO-MAS-NÃO-EXISTE (a "morte")**
ADR 0061 "zero auto-mem". O hook `block-automem.ps1` bloqueia **escrita nova**, mas o conteúdo
residual vive e é lido entre sessões:
- `~/.claude/projects/D--oimpresso-com/memory/` tem **17 arquivos** (MEMORY.md + 14 notas-de-trabalho +
  `user_profile.md`), vários modificados **2026-06-18** (ontem).
- `user_profile.md` (cat. LOCAL, ADR 0131) **continua não-migrado** pra `~/.claude/oimpresso-local/` —
  a própria MEMORY.md admite "mover quando puder". "Matar a auto-mem" é **pendente**, não feito.

---

## O que DE FATO já existe (justiça — a fundação é real e honesta)

A Semana 0 do SDD é genuinamente sólida (não é teatro). Verificado em origin/main:

- **ADR 0275 (scorecard SDD canônico)** — 10 métricas + armamento (3 medições) + calendário duro de
  promoções + composta v1/v2 (regimes separados, anti-stale). Aceito, números field-derived.
- **`governance/sdd-scorecard.json` + baseline + `sdd-scorecard.mjs` + `sdd-scorecard.yml`** — existem e
  rodam. **4/10 métricas measured** (`anchor_coverage=5.4%`, `n_quarantine=27`, `ghost_count=14`,
  `front_door_coverage=100%`); **2 armadas** (`ghost_count`, `front_door_coverage`) com catraca real.
- **`gate-selftest.yml` (GT-G6)** — 8/8 live, SEM continue-on-error; prova que 4 catracas MORDEM.
  Peça mais forte do programa ("quem vigia os vigias").
- **`foundation-ratchet.yml` (FV-Q1)** + **`anchor-lint.mjs` (SA-A2/A3)** — métrica de USO (não forma);
  anchor-lint detecta **15 `anchored_dead` reais** (paths citados que não existem).
- **`gates-registry.json`** — registry canônico; anchor-drift, foundation-ratchet, gate-selftest,
  knowledge-ghost-gate, protection-drift, sdd-scorecard, tier0-guards-advisory todos registrados.
- **G5 (protocolo refutador) VIVO** — `governance/sdd-verification-ledger.json` com entries reais
  (PRs #2750/#2754/#2761 backfill refutado em sessão fresca, error_rate 0%, PII scan ok).
- **Camada de leitura/garantia SDD parcialmente construída** — `mcp_sdd_scorecard_history` (tabela +
  migration), `SddScorecardSnapshotCommand`, `SddBriefLineService`, dashboard controller, schedule no
  Kernel. (Plano linha 109 "nada implementado" está **defasado** — a Semana 0 landou desde 12→19 jun.)
- **Memória — camada rasa real:** time-decay (0270 F4) **implementado e ON por default**
  (`Modules/Jana/Config/config.php`: `JANA_TIME_DECAY_ENABLED=true`, half-lives por tipo, multiplicadores
  por lifecycle historical=0.5/superseded=0.3) — construído na Onda 5 ADR 0061, **independente do SDD**.
  `front_door_coverage=100%` por módulo. `ghost_count` 27→14 (codemod #2603/#2693).
- **SEM conflito append-only × mutável (refuta a dúvida #2 da própria tese):** `block-memory-drift.ps1`
  **isenta explicitamente** `memory/requisitos/**` (onde vive BRIEFING.md). ADR 0130 escopa append-only
  **só a handoffs**. BRIEFING é livremente mutável. A maquinaria append-only **não bloqueia** a porta
  mutável do 0270. (Esta é boa notícia pra união — não há contradição a desfazer.)

---

## A KEYSTONE MÍNIMA pra unir os dois (menor conjunto a construir)

A união NÃO precisa de "tudo"; precisa de **5 peças** — e nenhuma existe hoje:

1. **Fechar o elo MEDIR→GOVERNAR (ADR 0279 PR-2/PR-3)** — `nightly-floor.json` transportado CT100→repo;
   `sdd-scorecard.mjs` lê e mata o hardcode `notYet`. Sem isto o scorecard nunca fecha 10/10 e a
   composta nunca é calculada. *(pré-requisito de TUDO; hoje aberto)*
2. **Construir o engine 0270 F3 (distiller módulo-verdade)** — ProfileDistiller (ou novo serviço) que
   lê eventos → reescreve a porta BRIEFING; `brief-update` obrigatório/auditável. É o que dá substância
   a "rodar a memória como verdade destilada". *(hoje 0% — bloqueado em CT 100)*
3. **Instrumentar `distiller_freshness` como métrica viva** + somá-la ao scorecard (11ª métrica) e/ou
   ao `jana:health-check` como check DURO (cumprir 0270 D-5/F2). *(hoje 0 hits em código)*
4. **Criar o "stream MEM" no avaliador SDD** — promover o engine 0270 (F3/F4/F5 + read_path_hops +
   distiller_freshness) a stream de 1ª classe (hoje KL só cobre a casca), com seu skeptic e suas
   métricas **no MESMO `sdd-scorecard.json`** (não num scorecard paralelo). Surfacing de `read_path_hops`
   (já computado) pro JSON é o quick-win. *(hoje inexistente)*
5. **Tirar o `continue-on-error` quando ≥3 métricas armarem** (cumprir o calendário do 0275) — senão a
   "união" mede mas não governa, repetindo o pecado atual. *(hoje 100% advisory)*

Ordem forçada: **1 → (2,3 em paralelo) → 4 → 5**. Peça 1 é o gargalo único; sem ela 4/5 não têm dado.

---

## Nota de prontidão real da união: **33/100**

Decomposição (o que a união exige × o que existe):
- Fundação de scorecard SDD compartilhável: **80/100** (real, honesta, mas advisory e composta não fecha).
- Camada rasa de memória dentro do SDD (ghost + front-door + time-decay): **70/100** (existe, mede forma).
- Engine profundo 0270 (destilar/freshness/archive/porta mutável governada): **8/100** (quase nada;
  só time-decay no chat + staleness de index existem, fora do SDD).
- Cola única (1 scorecard que soma as 2 famílias + 1 stream MEM + 1 índice-mestre): **5/100** (inexistente;
  4 scorecards paralelos, ≥7 índices, composta não calculada).
- Sinal vivo governando comportamento: **10/100** (0/18 required; nightly morto 3 dias).

Média ponderada (cola e engine pesam dobrado, pois são a essência da "união"): **~33/100.**

> **Resumo de uma linha:** a *governança* do SDD existe e é honesta; a *memória-unificada* do 0270
> **quase não foi construída** (só a casca + time-decay herdado); e a *cola* que faria "um só sistema"
> (scorecard único somando as 2 famílias + stream MEM + porta-índice única) **não existe**. "Basta
> rodar a memória como stream do SDD" pressupõe um motor de memória e um trilho de stream que ainda
> precisam ser construídos. **Tese FALSA.**
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
