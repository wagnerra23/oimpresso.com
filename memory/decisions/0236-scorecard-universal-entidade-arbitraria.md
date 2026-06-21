---
slug: 0236-scorecard-universal-entidade-arbitraria
number: 236
title: "Scorecard Universal — entidade avaliável arbitrária (blueprint pattern): temas/capacidades como cidadãos de primeira classe ao lado de módulos"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
decided_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tags: [governance, scorecard, score-as-code, blueprint, themes, capabilities, scoped-scorecards, port-pattern, opslevel-campaigns, anti-regressao, rtm]
supersedes: []
extends: [0160, 0230]
related:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0230-metodo-governance-scorecard
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
authors:
  - W
  - C
---

# 0236 — Scorecard Universal: entidade avaliável arbitrária (blueprint pattern)

## Contexto

O **método grade** do projeto (pesquisar os melhores do mundo → dar nota 0-100 → roadmap pro topo, com os 2 invariantes do [ADR 0230](0230-metodo-governance-scorecard.md): ratchet anti-regressão + rastreabilidade `origin`) já está implementado em **dois lugares — com objeto avaliável diferente, mas sem guarda-chuva comum**:

| Onde | Objeto avaliável | ADR | `kind` |
|---|---|---|---|
| `module-grade-v4` | **Módulo** (`Modules/<X>/`) — 4 buckets, amarrado a `module.json` | [0160](0160-governance-v4-scoped-scorecards-buckets.md) | `ModuleScorecard` |
| Governance grade | **Regra de protocolo** (R1-R12) | [0230](0230-metodo-governance-scorecard.md) | (`RuleScorecard`) |

**O gatilho (sessão 2026-05-30):** Wagner pediu aplicar o método grade ao **"Claude Design"** — que **não é módulo nem regra, é um TEMA/capacidade transversal** (o pipeline de design assistido por IA: persona → análise → enforcement → aprendizado). O agente criou `memory/scorecards/design.yaml` e foi **obrigado a inventar `kind: CapabilityScorecard` ad-hoc**, porque o objeto não cabia em "módulo". Esse foi o sintoma.

Wagner, ao ver o resultado:

> "acho que eu não fiz o framework para isso (...) os scores estão dentro dos módulos, mas acho que cada tema proposto pode ter como analisar o tema e os score card do tema."

**Diagnóstico:** o framework nasceu **module-centric**. Já existem **3 tipos de entidade avaliável** surgindo organicamente (módulo, regra, tema) **sem um padrão que os unifique** — resultando em (a) `kind` ad-hoc, (b) runners fragmentados (`grade.mjs` de governança + `grade-design.mjs` de tema, scripts separados que vão driftar), e (c) a análise de tema (agente `audit-research-expert`) produz **dossiê markdown efêmero** em `memory/sessions/` em vez de **scorecard versionado com ratchet**.

## Estado da arte 2026 (etapa 3 do método — quem é o melhor E POR QUÊ)

- **Port.io** (líder, US$800M valuation dez/2025) — **"scorecards as blueprints"**: o scorecard avalia **qualquer entidade do data model**, "not limited to microservices… any asset: microservice, environment, package, cluster, database, custom assets". MECANISMO: a entidade é um *blueprint* arbitrário; o scorecard se aplica a ela. É exatamente o que o Wagner intuiu — o objeto avaliável não precisa ser módulo.
- **OpsLevel Campaigns** — iniciativas temáticas **time-boxed** (start/end dates), cross-cutting, com nudges Slack/email. MECANISMO: "leve o tema X de 40→85% até a data Y" através de um subconjunto de componentes. É o "tema com prazo".
- **Cortex Initiatives** — temáticas também, mas sem prazo futuro (efetivam na hora) e baseline igual pra todos — mais fraco que OpsLevel nesse eixo.
- **Lição convergente:** os 3 líderes **separam 3 conceitos** que o oimpresso hoje colapsa em "scorecard de módulo": (1) **ENTIDADE avaliável arbitrária**, (2) **RUBRICA score-as-code reusável**, (3) **CAMPANHA temática time-boxed**.
- **Fundação interna:** [ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md) (scoped scorecards por bucket) + [ADR 0230](0230-metodo-governance-scorecard.md) (método + invariantes) + benchmark `memory/sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md`.

## Decisão

Adotar o **Scorecard Universal**: o objeto avaliável **deixa de ser "módulo" e passa a ser "entidade"** (blueprint pattern do Port), de **3 kinds**, sob um framework comum. Este ADR **estende** 0160 e 0230 (não os substitui).

### 1. Três kinds de entidade avaliável (todos score-as-code em `memory/scorecards/`)

- **`ModuleScorecard`** — entidade com **fronteira física** (`Modules/<X>/`). Já existe (ADR 0160, 4 buckets). Avaliada contra o código do módulo + `module.json`. **Inalterada.**
- **`ThemeScorecard`** — entidade **virtual/transversal, SEM pasta**. Definida por um **manifesto** que aponta a sua evidência espalhada (personas, charters, skills, ADRs, hooks, CI). Ex: `claude-design`, `observability`, `session-handoff`, `reranker`. **Novo.**
- **`RuleScorecard`** — regras de protocolo/governança (R1-R12). Já existe (ADR 0230).

> A distinção-chave: **módulo tem fronteira física** (pasta); **tema é virtual** — existe só como manifesto + evidência apontada. O Port modela ambos como *blueprints*; o oimpresso adota a mesma ideia com `kind`.

### 2. Onde os scores moram (estrutura canônica)

```
memory/scorecards/
├── _method.yaml            ← meta: a própria régua tem nota (revisão trimestral)
├── modules/<bucket>.yaml   ← 4 buckets (ADR 0160) — entidade física
├── themes/<tema>.yaml      ← NOVO — temas/capacidades (entidade virtual)
│   ├── claude-design.yaml  ← 1º exemplar (PR #2003)
│   ├── observability.yaml
│   └── session-handoff.yaml
└── governance.yaml         ← regras R1-R12 (ADR 0230)
```

- **Módulo:** rubrica centralizada + `bucket` declarado em `module.json` (mantém 0160). Co-localização futura (`Modules/<X>/Scorecard/`) permanece opção só pra módulo.
- **Tema:** **sem `module.json`** (não há módulo). O manifesto `themes/<tema>.yaml` é **auto-suficiente** — lista unidades + evidência (paths) + best-of-class + ratchet + origin.

### 3. Anatomia do `ThemeScorecard` (manifesto blueprint)

Campos canônicos — **já validados no `claude-design.yaml` (2026-05-30)**:

- `metadata` (scope · pergunta · owner · baseline_date · proxima_revisao · origin)
- `best_of_class` (≥3 com o **POR QUÊ**/mecanismo — etapa 3 do ADR 0230)
- `unidades[]` — cada uma com: `maturity` 0-100 · `nivel` (bronze/silver/gold) · `rec` (CONSOLIDAR/EVOLUIR) · `baseline` (**ratchet — Invariante A**) · `justification` (por que não pode voltar) · `origin` (**RTM — Invariante B**) · `evidence[]` (paths) · `paired_indicator` · `gaps[]`
- `paired_indicators` (anti-gaming)
- `calculo` (agregado ponderado)
- `roadmap` (ondas, impacto×esforço, CONSOLIDAR vs EVOLUIR)
- `veredito` (resposta direta à pergunta de origem)

### 4. Como um tema é analisado (loop fechado)

```
tema proposto
  → agente audit-research-expert (pesquisa estado-da-arte + nota % + gaps + roadmap)
  → PERSISTE como themes/<tema>.yaml versionado   ← (hoje vira só dossiê .md efêmero — este é o furo)
  → runner único valida agregado + ratchet (Invariante A) ao longo do tempo
```

O `audit-research-expert` (que já operacionaliza a etapa 3 do ADR 0230) ganha um **output canônico**: o YAML versionado, não um markdown de sessão que ninguém volta a rodar.

### 5. Runner único (substitui scripts fragmentados)

- **Hoje:** `grade.mjs` (governança — casos × hooks reais) + `grade-design.mjs` (tema — rubrica + evidence-checks) são **scripts separados**.
- **Decisão:** **um runner** que aceita `--scope <module|theme|governance>/<nome>`, lê o YAML, roda os checks adequados ao `kind` (hooks pra `RuleScorecard`; evidence-checks de path pra `ThemeScorecard`; AST/SQL pra `ModuleScorecard`), aplica ratchet + paired indicators, imprime scorecard + veredito. **Exit 1 em regressão.** É o "um motor sobre blueprints diferentes" do Port.

### 6. Campanha temática (V2 — opcional, pattern OpsLevel)

Reconhecer (sem implementar agora) o conceito de **campanha**: um tema com **prazo** (ex: "levar `claude-design` de 72→80 até 2026-08-30") + nudge. Fica como extensão V2; **não bloqueia** este ADR.

## Consequências

### Positivas
- **Tema vira cidadão de primeira classe** — analisável e versionado igual módulo. Fecha a lacuna que o 0160 deixou (module-only).
- `audit-research-expert` deixa de produzir dossiê efêmero → produz **ativo versionado com ratchet** (a nota só sobe).
- **Um runner só** (DRY) vs N scripts que driftam.
- **Alinha com o Port** (blueprint pattern) — padrão validado pelo líder de mercado, sem custo de SaaS.
- **Não quebra nada**: 0160 (módulos) e 0230 (regras) continuam; este ADR **estende**.

### Negativas / custo (mitigadas)
- Mais um diretório + convenção pro time aprender. **Mitigação:** `claude-design.yaml` é o template vivo.
- Tema sem fronteira física → a evidência apontada pode driftar (path some/move). **Mitigação:** evidence-checks no runner **alertam** quando a evidência some (já implementado no `grade-design.mjs`).
- Risco de **proliferação** de temas (scorecard de tudo). **Mitigação:** tema entra **só via `audit-research-expert` + [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)** (sinal qualificado) — não é ad-hoc.

### Neutras
- `module-grade-v4` **inalterado**. O gate CI atual (`module-grades-gate`) cobre **só módulos**; **temas NÃO entram no gate** inicialmente — são **advisory** até Wagner promover (mesma cautela do 0230, que nasceu como proposta non-gating).

## Alternativas consideradas

1. **Forçar tema como "módulo virtual"** (criar `Modules/ClaudeDesign/` vazio) — rejeitado: polui `Modules/` com não-código; `module.json`/AST-scan não fazem sentido pra tema.
2. **Manter scripts separados por escopo** (`grade.mjs`, `grade-design.mjs`, `grade-X.mjs`…) — rejeitado: N scripts driftam, viola DRY; o Port prova que **1 motor sobre blueprints diferentes** escala.
3. **Comprar Port/OpsLevel** — rejeitado (mesmo racional do 0160: pricing por seat, overhead pra ERP modular de time pequeno; score-as-code próprio = zero SaaS).
4. **Não generalizar** (deixar `claude-design.yaml` como exceção) — rejeitado: o sintoma (`kind` ad-hoc) **vai se repetir** no próximo tema (observability, reranker); melhor formalizar agora que há **1 exemplar** pra calibrar o formato.

## Implementação (proposta — Wagner aceita antes de codar)

| Onda | Entrega | Estado |
|---|---|---|
| 1 | `claude-design.yaml` + `grade-design.mjs` (o exemplar) | ✅ feito 2026-05-30 (PR #2003) |
| 2 | **Aceitar este ADR** (framework) | ⏳ este PR |
| 3 | Mover `design.yaml` → `themes/claude-design.yaml` (`kind: ThemeScorecard`) + generalizar a grade num **runner único `--scope`** | a fazer |
| 4 | Regra "`audit-research-expert` persiste `themes/<tema>.yaml`" + **1 segundo tema** (ex: `observability`) como prova | a fazer |
| 5 | Campanha time-boxed + nudge (opcional) | futuro |

## Sources

### Estado da arte 2026
- [Port — Scorecards: concepts and structure (blueprints)](https://docs.port.io/scorecards/concepts-and-structure/)
- [Port — Scorecards as blueprint (roadmap)](https://roadmap.port.io/ideas/p/scorecards-as-blueprint)
- [Port — Blueprints for product-like developer experience](https://www.port.io/guide/blueprints)
- [OpsLevel — Campaigns (time-boxed, cross-cutting)](https://www.opslevel.com/product/maturity/campaigns)
- [OpsLevel vs Cortex — scoped scorecards](https://www.opslevel.com/resources/opslevel-vs-cortex-whats-the-best-internal-developer-portal)

### Fundação interna
- [ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md) — module-grade-v4 (scoped scorecards por bucket) · **estendido** por este ADR
- [ADR 0230](0230-metodo-governance-scorecard.md) — Método Governance Scorecard (4 etapas + 2 invariantes) · **estendido**
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §4 (loop fechado por métrica)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal (filtro anti-proliferação de temas)
- `memory/sessions/2026-05-16-arte-scorecards-alta-2026-benchmark.md` — benchmark Port/Cortex/OpsLevel/Backstage
- `memory/scorecards/design.yaml` + `.claude/governance-eval/grade-design.mjs` (PR #2003) — o exemplar `ThemeScorecard`
