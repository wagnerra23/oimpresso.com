---
slug: 0257-adr-status-lifecycle-kind-modelo-canonico
number: 257
title: "Modelo canônico de status/lifecycle/kind de ADR + exceção de normalização no append-only"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-07"
module: governance
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# ADR 0257 — Modelo canônico status/lifecycle/kind de ADR

## Contexto

A sentinela `memory-health` (ADR 0256, Check E) achou **56 ADRs com `status`/`lifecycle` fora do enum**: `accepted`(28)/`aceita`(1)/`proposed`(10) misturados com `aceito`/`proposto`; `active`(6)/`canon`(8)/`feature_wish`(3) misturados com `ativo`. Por isso "quantos ADRs ativos?" não tinha resposta limpa.

**Causa-raiz estrutural (não é só grafia):** havia **2 campos com semântica sobreposta e mal definida** (`status` vs `lifecycle` — superseded≈substituido, deprecated≈arquivado) e **nenhum eixo pra categoria** da ADR. Sem um lugar pra dizer "isto é um feature-wish / uma errata", as pessoas enfiaram isso no `lifecycle` (`feature_wish`) ou inventaram `canon`. Drift é sintoma de modelo ambíguo.

Agravante: o gate append-only (`block-adr-edits`) **não tem exceção** — bloqueia QUALQUER ADR modificada, inclusive correção de schema de metadados. Append-only deve proteger a **decisão**, não impedir consertar a **etiqueta**.

## Decisão

### 1. Três eixos ortogonais (cada um responde 1 pergunta)

| Campo | Pergunta | Enum |
|---|---|---|
| **`status`** | Em que ponto está a DECISÃO? | `rascunho` · `proposto` · `aceito` · `superseded` · `deprecated` |
| **`lifecycle`** | O DOCUMENTO é fonte viva? | `ativo` · `substituido` · `arquivado` · `historical` |
| **`kind`** (novo, opcional; default `decision`) | Que TIPO de ADR é? | `decision` · `feature-wish` · `errata` · `meta` |

**Pareamentos canônicos** (status × lifecycle):
- `aceito` + `ativo` → vigente (a maioria)
- `superseded` + `substituido` → substituída (exige `superseded_by`)
- `deprecated` + `arquivado` → abandonada
- `proposto` + `ativo` → em discussão
- qualquer + `historical` → registro que nunca foi "lei" (ex: ADR HISTORICAL, feature-wish parqueado)

**"ADRs ativos"** = `lifecycle: ativo` (definição única, sem ambiguidade).

### 2. Mapa de normalização (drift → canônico)

| De | Para |
|---|---|
| status `accepted` / `aceita` | `aceito` |
| status `proposed` | `proposto` |
| lifecycle `active` / `canon` | `ativo` |
| lifecycle `feature_wish` | `historical` + `kind: feature-wish` + `status: proposto` (hipótese parqueada, ADR 0105) |

ADRs antigos em formato tabela (sem YAML frontmatter, ex 0126-vault/0128-smoke) = migração à parte (converter tabela→frontmatter), fora do escopo deste passo.

### 3. Exceção de normalização no append-only

O gate `block-adr-edits` passa a **permitir** modificação de ADR ratificada **se e somente se**:
- a PR tem o label **`adr-metadata-normalization`**, E
- o diff de cada ADR toca **APENAS** linhas de frontmatter dos campos `status`/`lifecycle`/`kind`/`authority` (nenhuma linha de corpo/decisão muda).

Append-only do **conteúdo da decisão** continua intacto (corpo, contexto, decisão = imutáveis). Só a etiqueta de metadados pode ser normalizada, sob label + diff cirúrgico verificável.

## Consequências

- ✅ "ADRs ativos" vira contável (`lifecycle: ativo`).
- ✅ `kind` dá casa pra categorias ortogonais → some o incentivo a inventar valor de lifecycle.
- ✅ Append-only fica mais inteligente: protege decisão, libera correção de schema sob controle.
- ✅ `memory-health` Check E + `adr.schema.json` viram a catraca que impede novo drift.
- ⚠️ Custo: 1 migração (PR 2, ~35 arquivos) + manter o `kind` enum. Aceitável.

## Implementação (2 PRs)
- **PR 1 (este):** ADR + patch `block-adr-edits` (exceção label) + schema `adr.schema.json` (+`kind`, lifecycle `historical`) + script `scripts/governance/normalize-adr-frontmatter.mjs`.
- **PR 2:** roda o script (normaliza ~35 ADRs frontmatter), mergeia com label `adr-metadata-normalization`. Atualiza `memory-health` Check E pro enum final.

## Refs
- Sentinela: `scripts/governance/memory-health.mjs` Check E (ADR 0256)
- Schema: `scripts/memory-schemas/adr.schema.json`
- Origem: pergunta Wagner "quantos ADRs ativos? isso está errado, quero estrutura melhor" (2026-06-07)
