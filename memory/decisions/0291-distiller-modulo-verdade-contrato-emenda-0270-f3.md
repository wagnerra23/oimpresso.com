---
slug: 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
number: 291
title: "Emenda 0270 F3/D-5 — contrato do distiller-módulo-verdade (diário→manual) + instrumentação distiller_freshness"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-19"
module: jana
tags: [memoria, destilacao, briefing, porta-unica, distiller, freshness, sdd, ct100, governanca, anti-elefante-branco]
supersedes: []
superseded_by: []
related:
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
  - 0130-handoff-append-only-mcp-first
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Proposta por [CL] em 2026-06-19** (peça 2 do keystone da união SDD×memória — ledger
> `memory/sessions/2026-06-19-adversario-uniao-sdd-memoria.md`). **Ratificação formal = merge por [W]**
> (convenção do [ADR 0270]: "aprovação de direção no chat; ratificação formal = merge").
> Direção autorizada por Wagner no chat 2026-06-19 ("ADR-emenda 0291" + "criar worktree e começar PR-A").

# ADR 0291 — Contrato do distiller-módulo-verdade (emenda do 0270 F3/D-5)

> O [ADR 0270] **decidiu** o ciclo de vida da informação (porta única + destilar + decair + medir
> o caminho de leitura). Marcou **F3 (destilar) e F2/D-5 (medir freshness)** como roadmap "bloqueio:
> CT 100", sem contrato executável. Esta emenda **crava o contrato** de F3 + a instrumentação de
> `distiller_freshness` (peça 3) — para que o "diário→manual" deixe de ser prosa e vire código,
> teste e métrica viva. **Não cria sistema novo** nem supersede o 0270 — preenche a metade que o 0270
> deixou como roadmap.

## Contexto (verificado em `origin/main` @ `0f2afd06a`, não no plano)

- **O motor F3 não existe.** `Modules/Jana/Services/Memoria/ProfileDistiller.php` destila **só** o
  perfil comercial por business (faturamento 90d / top clientes / metas → `jana_business_profile`)
  via `Laravel\Ai\AnonymousAgent`. **Nunca lê sessions/handoffs/PRs; nunca lê nem escreve `BRIEFING.md`.**
  `git grep BRIEFING` em `*.php`/`scripts` → só **leitores**. **Zero escritores.** Nenhum cron regenera a porta.
- **A métrica F2/D-5 está vazia (mas com trilho).** A peça 4 (já em main) carimbou `distiller_freshness`
  no `governance/sdd-scorecard.json` como **`not_yet_measured`, `stream: MEM`**; o comentário-fonte em
  `scripts/governance/sdd-scorecard.mjs:191` aponta literalmente "peça 2/3 do keystone" como pendente.
  Nenhum check `distiller_freshness` existe no `jana:health-check` (16 checks, nenhum é este).
- **A fundação para herdar é real:** `block-memory-drift.ps1` **isenta** `memory/requisitos/**` (porta é
  livremente mutável — [ADR 0130] escopa append-only só a handoffs); `front_door_coverage=100%`
  (BRIEFING presente por módulo) mas é cobertura de **forma** (11 lápides contam como porta), não de
  **conteúdo destilado**; `HealthCheckCommand::checkProfileDrift()` (check 5, `profile_distiller_drift`)
  é o molde exato do check DURO de freshness; `ledger-check.mjs` (G5) já exige refutação adversarial em
  PR-de-lote >10 arquivos em `memory/requisitos/**`; `.github/scripts/pii-scan.sh` é diff-only + redact.

## Decisão — o contrato do distiller-módulo-verdade

### D-A — Input (o que se lê, por módulo)
Para um módulo `<Mod>`, a janela é **`desde o último `distilled_at`` da porta, OU 30 dias** (o que for maior),
e a fonte são os **eventos recentes**: (1) `memory/sessions/*.md` que citam o módulo; (2) `memory/handoffs/*.md`
do módulo; (3) **PRs mergeados** que tocaram `Modules/<Mod>/` ou `resources/js/Pages/<Mod>/`; (4) `AUDIT*.md`/
`AUDITORIA*.md`/`CAPTERRA*.md` do módulo. **A seleção de "o que é relevante" é PURA e testável** — função sem
LLM/sem FS-write, recebe as listas de eventos + `lastDistilledAt`/janela e devolve o subconjunto a destilar
(entregável testável; espelha a separação de `ProfileDistiller`/`ContextSnapshotService`).

### D-B — Output (a porta reescrita)
A saída **REESCREVE** `memory/requisitos/<Mod>/BRIEFING.md` — **verdade mastigada ≤1 página** (D-2 do 0270):
estado atual, capacidades, gaps, última mudança. Regras duras:
- **MUTÁVEL — sobrescreve, NÃO append** (D-1 do 0270: append-only a "verdade atual" é **proibido**;
  esquecer/reescrever é feature). Permitido porque `block-memory-drift.ps1` isenta `memory/requisitos/**`.
- **Porta ≠ índice de links** (D-2): é verdade destilada, não acúmulo. Os links de **proveniência**
  (SPEC/sessions/handoffs/PRs) vão numa seção de proveniência **no rodapé, não inline** no corpo.
- **Carimba `distilled_at:` no frontmatter** (ISO-8601) + `distilled_by: jana:distill-module-truth` +
  lista de proveniência. `distilled_at` é o **sinal disciplinado de frescor** que o 0270 §"evidência"
  disse faltar (data de mtime não é confiável).
- Mantém compatível com o `BRIEFING-TEMPLATE.md` canônico (seções existentes preservadas quando há sinal).

### D-C — Comando + cron
- `php artisan jana:distill-module-truth {--module=} {--all} {--dry-run}`. `--dry-run` calcula e mostra o
  diff **sem escrever**. **Reusa a chamada LLM do `ProfileDistiller`** (`AnonymousAgent`) — não reinventa.
- **Cron diário** em `app/Console/Kernel.php` (onde já vive `brief:generate`). Isto torna o `brief-update`
  (hoje skill Tier B best-effort) **obrigatório e auditável** (D-3 do 0270): destilação que para = incidente
  (cobrado por D-D).

### D-D — Instrumentação `distiller_freshness` (peça 3) — anti-stale por construção
A métrica e o check leem os `distilled_at:` das portas. Espelham o padrão **honesto** do
`measureFullSuiteFloor` (0279): **não mentem "0" antes da fonte existir.**
- **Scorecard** (`scripts/governance/sdd-scorecard.mjs`): `measureDistillerFreshness()` enumera
  `memory/requisitos/*/BRIEFING.md`. **Zero portas carimbadas → `not_yet_measured`** (honesto: distiller
  ainda não rodou — gate Wagner/CT100 pendente). **≥1 carimbada → `measured`**: `value` = nº de portas com
  `distilled_at` > 7d (stale), `target: 0`, `direction: down`, `stream: MEM`. **Flip automático** quando a
  destilação rodar em prod — igual ao floor do 0279.
- **Health-check** (`HealthCheckCommand::checkDistillerFreshness()`): gêmeo **DURO** (espelha
  `checkProfileDrift`). Conta portas carimbadas >7d; >0 → derruba exit code + ALERT de cron. **Skip** (ok)
  enquanto nenhuma porta tiver `distilled_at` (anti-stale; não acende vermelho antes do 1º carimbo).

### D-E — Guardas Tier 0 (inegociáveis)
- **Refutador G5 antes de qualquer merge** do output destilado (anti-envenenamento da memória canônica):
  um lote `--all` toca >10 arquivos em `memory/requisitos/**` → cai automaticamente no `ledger-check.mjs`,
  que exige entry válida em `governance/sdd-verification-ledger.json` (refutador em sessão fresca, modelo
  ≥ gerador, `error_rate_pct < 2`, `pii_scan:true`, `pii_hits:0`). A porta destilada **carrega proveniência**
  justamente para o refutador conseguir verificar. **Estende o ledger existente — não cria paralelo** (D-6).
- **PII scan diff-only** (repo é PÚBLICO): o distiller passa o output da LLM por `App\Support\PiiRedactor`
  **antes de escrever** e **recusa** se PII sobreviver; o `pii-scan.sh` cobre o diff no CI (defesa em profundidade)
  — CPF/CNPJ + nomes de cliente do CRM nunca vazam ([ADR 0093] · LGPD Art. 7º).
- **CT100-gated:** a destilação real (chamada LLM + impacto no recall) **valida no CT100** ([ADR 0062]
  separa runtimes; 0270 §roadmap). **A execução em prod é decisão do [W]** (smoke skim 10min/lote). Este
  contrato + o código + os testes nascem **sem rodar destilação em prod**.
- **Sem scorecard/índice paralelo** (D-6 do 0270): a freshness entra **no mesmo** `sdd-scorecard.json`
  (peça 4) e **no mesmo** `jana:health-check`. Nada de 5ª família de placar.

## Consequências
- **Positivas:** a pergunta "qual o estado atual de `<Mod>`?" passa a ter porta **destilada e datada** (≤1 pulo,
  D-5); `distiller_freshness` deixa de ser texto e vira métrica viva governada no stream MEM; o "diário→manual"
  ganha um motor com cadência e alarme.
- **Custos/riscos:** a métrica **só fica verde depois** que [W] rodar a destilação em CT100/prod (por design —
  anti-stale); LLM pode alucinar/vazar → mitigado por G5 + PiiRedactor + dry-run + gate humano; reescrever a porta
  exige disciplina contra o instinto append-only → mitigado porque D-D mede e o health-check cobra.

## Roadmap de PRs (cada ≤300 linhas · 1 intent · conventional · RED-first)
- **PR-A (este):** ADR 0291 — contrato. `docs`.
- **PR-B:** núcleo PURO "o que distilar" (`ModuleTruthEventCollector`) + Pest unitário. `feat(jana)`.
- **PR-C:** `DistillerModuloVerdade` (collector → `AnonymousAgent` → `PiiRedactor` → reescreve BRIEFING com
  `distilled_at`+proveniência) + comando `jana:distill-module-truth` + cron + feature tests (LLM mockado:
  dry-run não escreve, recusa PII, proveniência presente, sobrescreve≠append). `feat(jana)`.
- **PR-D (peça 3):** `measureDistillerFreshness()` no scorecard (notYet→measured-quando-carimbado) + node test
  com fixture + `checkDistillerFreshness()` DURO no health-check + Pest. `feat(governance)`.

## Métricas de sucesso
- `distiller_freshness` vira `measured` (≤7d em 100% das portas carimbadas) **assim que** [W] destila em prod.
- `read_path_hops` mediano → 1 nas portas destiladas (já computado em `knowledge-drift.mjs`).
- Lote de destilação `--all` **não mergeia** sem entry G5 válida + 0 PII no diff.

## Referências
- [ADR 0270] ciclo de vida da informação (decisão-mãe; F3/D-5 que esta emenda contrata)
- [ADR 0275] scorecard SDD canônico (onde mora o `distiller_freshness` stream MEM)
- [ADR 0279] floor nightly (padrão anti-stale `notYet`→`measured` herdado)
- [ADR 0061] conhecimento canônico git/MCP · [ADR 0130] handoff append-only (escopo do append-only)
- [ADR 0062] separação runtime Hostinger×CT100 (gate da destilação)
- [ADR 0093] multi-tenant Tier 0 · [ADR 0094] Constituição v2 (Princípio 1 "context as a product")
- Ledger adversarial `memory/sessions/2026-06-19-adversario-uniao-sdd-memoria.md` (keystone 5 peças; G1/G2 = esta)
- `PROTOCOLO-REFUTADOR-BACKFILL.md` + `governance/sdd-verification-ledger.json` (G5) · `.github/scripts/pii-scan.sh`
