---
date: "2026-06-19"
topic: "Adversário do processo de memória unificada (ADR 0270) pós-peças 2/3 do keystone: 3 céticos verificaram origin/main. Veredito: a 'união' não existe — engine ocioso (0 destilações), memória-de-design doc-morto, sem cola. Prontidão ~38/100."
authors: [C]
related_adrs:
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0292-errata-0291-distiller-freshness-scorecard-deterministico
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
prs: []
---

# Adversário — a "união da memória" (ADR 0270) depois das peças 2/3

> Sessão fresca, default cético. 3 skeptics independentes (distiller · design-memory · cola),
> cada um verificando origin/main NOW (não plano/PR-description). Anti-stale: doc que promete ≠
> peça que RODA. Continuação do ledger `2026-06-19-adversario-uniao-sdd-memoria.md` (33/100), agora
> com #3015/#3016/#3020/#3023/#3028 mergeados.

## VEREDITO: a união continua **NÃO construída** — prontidão **~38/100** (vs 33)

As peças de hoje são **código de boa qualidade**, mas moveram o engine de *stub* → *testado-mas-nunca-executado*, não para *vivo*. Os três eixos:

| Eixo | Veredito | Score |
|---|---|---|
| Distiller-módulo (ADR 0291) | **PARCIAL-OCIOSO** — máquina pronta, nunca acionada | 35/100 |
| Memória-de-design (prototipo-ui) | **DOC-MORTO** no núcleo, costelas de borda vivas | 31/100 |
| Cola / "um só sistema" | **N-PARALELOS** — design 100% fora | 38/100 (união) |

## Os 3 achados que matam a tese "já está unido"

### 1. O motor nunca destilou nada (engine ocioso)
- `git grep distilled_at -- memory/requisitos/*/BRIEFING.md` = **0 portas carimbadas**. Os BRIEFINGs em main seguem mantidos à mão (`brief-update` best-effort, datados de maio), não pela máquina (PRs mergearam 18-19/jun).
- Cron **comentado de propósito** (`app/Console/Kernel.php:189-197`, gate Wagner/CT100) → o "diário" do 0270 D-3 ("obrigatório e auditável") **não roda**.
- `distiller_freshness` = `not_yet_measured` (`governance/sdd-scorecard.json`) → a métrica-prova do D-5 **mede nada hoje**. É feito-que-depende-de-algo-que-nunca-rodou.
- `front_door_coverage=100%` conta **9/70 lápides/redirects** como porta → cobertura de FORMA, não de verdade-de-módulo.
- O gate G5/PII do distiller só foi exercido em Pest com fixtures; `governance/sdd-verification-ledger.json` tem **0 entries de lote de destilação real**.

### 2. A memória-de-design é doc-morto no coração
- Benchmark (`PROCESSO_MEMORIA_CC.md:222-223`) **parou em 2026-06-02** (linha-semente); §5 REGRESSÕES PROIBIDAS está **vazia** (`:103`). Pela própria régua §13.1/NÚCLEO-8 ("sem medição → não-confiar") o método **se auto-declara não-verificado**.
- `DS-GUARD` e `integrity-check.mjs` **não rodam em CI nenhum** (`git grep` em `.github/**` = vazio) → continuam manuais "no fim da build", exatamente a L-16/TESTE-04 que o doc alega ter curado.
- **IT2 quebrado em main:** `compras/charter.md` e `vendas/charter.md` existem **sem `decisoes.md` irmão** ("tela órfã = estrutura comprometida" pela própria §15) — e nenhum gate pega.
- Ingestão do zip = **prosa manual** (diff-vs-anterior feito à mão no `SYNC_LOG.md`); sem script de unzip, sem diff-vs-último, sem manifest/allowlist. O hook de reprocess **nunca bloqueia** ("é um nudge, não um gate"); sem handoff `new_design_memories`, nada dispara.

### 3. Não há cola — são memórias e placares disjuntos
- **4 famílias de scorecard** vivas e disjuntas (`sdd-scorecard.json` · `module-grades-baseline.json` · `memory/governance/scorecards/**` · `.claude/governance-eval/scorecard.mjs`); **nenhuma soma** num número único. A composta SDD continua **NÃO calculada** (8/12 métricas `not_yet_measured` — pior que os 6/10 do ledger).
- **≥10 índices-mestre** concorrentes; as peças de hoje reduziram **0**.
- A memória-de-design (`INDEX-DESIGN-MEMORIAS.md`, charters) **não está em scorecard nenhum** e o distiller **não a toca** (`ModuleTruthEventCollector` só aceita session/handoff/pr/audit; reescreve só `BRIEFING.md` de módulo). O "stream MEM" é **meio-MEM** (só módulo/recall).
- **Bug de proveniência:** o ledger-âncora `memory/sessions/2026-06-19-adversario-uniao-sdd-memoria.md` **não está em origin/main** (vive só na branch contaminada `feat/governance-ds-rollout-ledger`), mas `sdd-scorecard.json:_meta` **e** a ADR 0291 (related/refs) **apontam pra ele** → link de proveniência pendente na canon.

## Justiça — o que DE FATO avançou (não é niilismo)
- **Estendeu o scorecard, não criou paralelo** (0291 D-6 cumprido) — freshness entrou no MESMO `sdd-scorecard.json`.
- **Anti-stale honesto por construção** — `not_yet_measured`/SKIP até o 1º carimbo; "1ª medição real da fonte, nunca do plano". Não acende verde fingido nem vermelho prematuro.
- **Métrica determinística de verdade** — `sdd-scorecard.json` commitado == `node sdd-scorecard.mjs --json` (diff vazio); a errata 0292 cravou o denominador (vs doc mais novo, não "hoje").
- **Engenharia sólida** — separação pura/impura (collector testável sem LLM), `refused_pii` preserva a porta (não silencia redactando), cron comentado é **cautela deliberada** documentada, não esquecimento. ADRs honestos sobre a pendência (0291:119 admite "só fica verde quando [W] rodar").
- **Borda do design viva** — `design-index-gate.yml` (Pest, todo PR de prototipo-ui) + `handoff-integrity-guard` (C3/C4, baseline + auto-teste) mordem de verdade; reprocess-hook plugado e idempotente.

## Os menores passos que MOVEM o número (ordem)
1. **Rodar a 1ª destilação real** (gate Wagner/CT100): `jana:distill-module-truth --module=<X> --dry-run` → skim → sem dry-run. Sem isto, distiller_freshness fica `n/a` pra sempre e a peça 2/3 é máquina ociosa. *(desbloqueia tudo do eixo 1)*
2. **Corrigir a proveniência:** commitar o ledger-âncora (e este) em main — senão a ADR 0291 e o scorecard citam doc inexistente.
3. **Religar o coração do design-memory:** pôr `ds-guard`/`integrity-check` num workflow (mesmo advisory) + retomar o Benchmark §11; consertar IT2 (compras/vendas sem decisoes.md).
4. **Construir a cola (peças 4-5 do keystone):** um número composto que some as famílias OU ao menos pôr a memória-de-design no scorecard (uma métrica `design_freshness` no stream MEM). É a peça que falta pra "um só sistema".
5. **Decidir a ingestão do zip de design** (o que iniciou esta investigação): ADR + tooling unzip→diff-vs-último→map-guard→sessão. Hoje é prosa manual = risco de regressão silenciosa.

> **Resumo de uma linha:** depois das peças 2/3, o oimpresso tem um **motor de destilação bem-construído e ocioso**, uma **memória-de-design com borda viva e núcleo abandonado**, e **zero cola** entre as duas — "um só sistema de memória" segue **falso**, prontidão ~38/100.
