---
name: memory-schema-preflight
description: ATIVAR ANTES de Write/Edit em `memory/requisitos/**/SPEC.md`, `memory/requisitos/**/RUNBOOK*.md`, `memory/decisions/*.md`, `memory/sessions/*.md`, `memory/handoffs/*.md`, `resources/js/Pages/**/*.charter.md`, OU antes de `git commit` que tocar esses paths. Carrega regras de schema canônico extraídas de `scripts/memory-schemas/*.schema.json` (status enum estrito, version como string quoted, dates como string quoted, related_adrs como list de slugs `^[0-9]{4}-[a-z0-9-]+$`, owner RUNBOOK enum letras únicas `W/F/M/L/E`, seções obrigatórias por tipo) e roda validator local antes de commit pra zerar o loop CI fail (~10min/iteração). Cobre também o campo anchor `**Implementado em:**` + key opcional `anchor_format` do fluxo novo de SPEC (ADR 0273, lint advisory `anchor-lint.mjs`). Origem 2026-05-25 — 4 PRs (#1568/#1569/#1570/#1579) bloqueados em memory-schema-gate por erros previsíveis.
tier: B
trigger: description-matching
parent_adr: 0094
related_adrs: [0094, 0095, 0273]
---

# memory-schema-preflight — Tier B auto-trigger

> **Quando ativar:** ANTES de qualquer Write/Edit em `memory/requisitos/**/SPEC.md`, `memory/requisitos/**/RUNBOOK*.md`, `memory/decisions/*.md`, `memory/sessions/*.md`, `memory/handoffs/*.md`, `resources/js/Pages/**/*.charter.md`. Também antes de `git commit` que tocar esses paths.

## Origem (custo capturado)

Sessão **2026-05-25** — 4 PRs criados (#1568/#1569/#1570/#1579) bloquearam em `memory-schema-gate` CI por erros previsíveis e repetitivos:

| Erro | Quantidade |
|---|---|
| `version` numérico (`0.2`) em vez de string quoted (`"0.2"`) | 3 |
| `status: proposed` em vez de enum allowed (`ativo`) | 3 |
| `last_updated` date type em vez de string quoted | 3 |
| `related_adrs` integers em vez de slug strings | 3 |
| `owner: wagner` em vez de enum RUNBOOK `W/F/M/L/E` | 1 |
| SPEC sem seção `## User stories` | 1 |

Cada CI fail = ~10min loop (push, wait CI, fix, push). Skill captura regras + roda validator local antes de commit pra zerar essas iterações.

## Schemas canônicos (fonte: `scripts/memory-schemas/*.schema.json`)

### SPEC.md — `memory/requisitos/<Mod>/SPEC.md`

```yaml
---
slug: <kebab>
title: "Especificação funcional — <Modulo>"
type: spec
module: <Modulo>
status: ativo                       # enum: ativo|aceito|rascunho|arquivado|historical
version: "X.Y.Z"                    # STRING quoted (nunca numero)
last_updated: "YYYY-MM-DD"          # STRING quoted (nunca date type)
updated_at: "YYYY-MM-DD"            # idem
owner: wagner                       # SPEC aceita user string
pii: false                          # boolean
related_adrs:                       # LIST de slugs, NUNCA integers
  - "NNNN-kebab-slug"               # pattern ^[0-9]{4}-[a-z0-9-]+$
parent_adr: "NNNN-kebab-slug"       # opcional, mesmo pattern
related_proposals:                  # opcional, lista de slugs proposal
  - "kebab-slug"
anchor_format: "v1"                 # opcional (ADR 0273) — enum SÓ "v1"; SPEC novo nasce com ela; 57 legados sem a key OK (grace-period)
---
```

**Seções obrigatórias** (pelo menos uma — schema-extended check):
- `## User stories`
- OR `## Backlog ativo`
- OR `## US ativas`

**Recomendadas** (warnings, não bloqueiam):
- `## Histórico`
- `## Referências`

**Campo anchor `**Implementado em:**` (ADR 0273 · `anchor_format: "v1"`) — fluxo NOVO:**

- A key `anchor_format: "v1"` no frontmatter é OPCIONAL (enum só `"v1"`). Grace-period: os 57 SPECs legados SEM a key continuam válidos (regra "campo novo opcional até backfill" do [README memory-schemas](../../../scripts/memory-schemas/README.md)). SPEC novo nasce com ela — já vem no [`_TEMPLATE_SPEC.md`](../../../memory/requisitos/_TEMPLATE_SPEC.md).
- Toda US ganha **1 linha** `**Implementado em:**` no corpo — no mínimo `_pendente_` enquanto a tela não existe (sentinela de **1ª classe**, NÃO é dívida de anchor). Gramática quando construída:

  ```
  **Implementado em:** `path/relativo.tsx` [· `Símbolo@metodo`] · verificado@<sha7> (<YYYY-MM-DD>)
  **Implementado em:** _parcial_ · `path` · verificado@<sha7> (<data>) — o que falta
  **Implementado em:** _pendente_ — justificativa opcional
  ```

  `sha7` = commit de `origin/main` onde o path foi verificado (proveniência). NUNCA `_[TODO]_` / `_[path]_` / `(a criar)` — placeholder legado conta como **não-coberto**.
- Divisão de responsabilidade: `spec.schema.json` valida só a **key** do frontmatter; o **corpo** (`**Implementado em:**`) é validado pelo `anchor-lint.mjs` (advisory na fase F1 — não bloqueia merge).

### RUNBOOK.md — `memory/requisitos/<Mod>/RUNBOOK*.md`

```yaml
---
title: "RUNBOOK — <título descritivo ≥5 chars>"
type: runbook
owner: W                            # ENUM ESTRITO W/F/M/L/E (NÃO wagner)
last_validated: "YYYY-MM-DD"        # STRING quoted, format date
status: ativo                       # enum: rascunho|ativo|arquivado|historical
---
```

Códigos de owner:
- **W** = Wagner
- **F** = Felipe
- **M** = Maiara
- **L** = Luiz
- **E** = Eliana

Opcionais comuns: `module`, `tela`, `us`, `last_updated`, `preconditions` (array string), `steps` (array string), `related_adrs` (array slug).

### ADR — `memory/decisions/NNNN-kebab.md`

Filename pattern: `^[0-9]{4}-[a-z0-9-]+\.md$`

Frontmatter Nygard:
```yaml
---
adr: NNNN
title: "Título Nygard-style curto"
status: proposed|accepted|deprecated|superseded|accepted-historical
date: "YYYY-MM-DD"
supersedes: [NNNN]                  # se aplicável
superseded_by: NNNN                 # se aplicável
---
```

Seções Nygard obrigatórias:
- `## Context`
- `## Decision`
- `## Consequences`

### Session log — `memory/sessions/YYYY-MM-DD-slug.md`

Filename: `^[0-9]{4}-[0-9]{2}-[0-9]{2}-[a-z0-9-]+\.md$`

```yaml
---
date: "YYYY-MM-DD"
type: session
tldr: "Resumo em 1 sentença"
---
```

Seção obrigatória: `## TL;DR`

### Handoff — `memory/handoffs/YYYY-MM-DD-HHMM-slug.md`

Filename: `^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{4}-[a-z0-9-]+\.md$`

```yaml
---
date: "YYYY-MM-DD"
time: "HH:MM"
type: handoff
estado_mcp: cycles-active
---
```

Seção obrigatória: `## Estado MCP`

### Charter — `resources/js/Pages/<Mod>/<Tela>.charter.md`

```yaml
---
type: charter
page: "<Mod>/<Tela>"
mission: "1 frase do que a tela existe pra fazer"
status: live|draft|apodrecido
---
```

Seções obrigatórias: `## Mission`, `## Goals`, `## Non-Goals`, `## UX targets`, `## Anti-hooks`.

## Auto-fix patterns (do CI fail message → fix)

| Mensagem CI | Fix exato |
|---|---|
| `/version must be string` | `version: 0.2` → `version: "0.2"` |
| `/status must be equal to one of the allowed values` | `status: proposed` → `status: ativo` |
| `/status must be equal to one of allowed values` (com texto inline) | `status: em-implementacao (Onda 4)` → `status: ativo` (mover info pra `## Histórico`) |
| `/last_updated must be string` | `last_updated: 2026-05-25` → `last_updated: "2026-05-25"` |
| `/related_adrs/N must match pattern "^[0-9]{4}-[a-z0-9-]+$"` | `[0093, 0094]` → list de slugs `- "0093-multi-tenant-isolation-tier-0"` |
| `/related_adrs/N must be string` | integer `0093` → string `"0093-multi-tenant-isolation-tier-0"` |
| `/owner must be equal to one of allowed values` (RUNBOOK) | `owner: wagner` → `owner: W` |
| `/ must have required property 'last_validated'` (RUNBOOK) | adicionar `last_validated: "YYYY-MM-DD"` |
| SPEC sem `## User stories \| ## Backlog ativo \| ## US ativas` | renomear seção existente OU criar nova com `## User stories — <contexto>` |
| SPEC sem `## Histórico` ou `## Referências` | warning, não bloqueia merge (mas adicionar é boa prática) |
| `/anchor_format must be equal to one of the allowed values` | `anchor_format: v2` → `anchor_format: "v1"` (único valor; AST v2 é evolução futura do ADR 0273) |
| anchor-lint `placeholder` / `anchored_dead` numa US | trocar `_[TODO]_` / path-morto por `_pendente_` (não construída) OU path real + `verificado@<sha7> (<data>)` — NUNCA inventar path (advisory, não bloqueia) |

## Validador local — rodar ANTES de commit

```bash
cd D:/oimpresso.com

# Tipo SPEC (sections + frontmatter custom)
echo "memory/requisitos/<Mod>/SPEC.md" | xargs -r .github/scripts/validate-memory-schema.sh spec

# JSON Schema via AJV (CI usa o mesmo)
npx ajv validate -s scripts/memory-schemas/spec.schema.json -d memory/requisitos/<Mod>/SPEC.md
npx ajv validate -s scripts/memory-schemas/runbook.schema.json -d memory/requisitos/<Mod>/RUNBOOK*.md
npx ajv validate -s scripts/memory-schemas/adr.schema.json -d memory/decisions/NNNN-kebab.md

# Anchor spec↔código (ADR 0273) — corpo `**Implementado em:**`, advisory, node puro <0.1s, sem deps
node scripts/governance/anchor-lint.mjs memory/requisitos/<Mod>/SPEC.md   # diff-aware (só o SPEC passado)
```

**Batch validar tudo modificado vs main antes de PR:**

```bash
cd D:/oimpresso.com
git diff --name-only origin/main HEAD | while read f; do
  case "$f" in
    memory/requisitos/*/SPEC.md)
      npx ajv validate -s scripts/memory-schemas/spec.schema.json -d "$f"
      .github/scripts/validate-memory-schema.sh spec "$f"
      ;;
    memory/requisitos/**/RUNBOOK*.md)
      npx ajv validate -s scripts/memory-schemas/runbook.schema.json -d "$f"
      ;;
    memory/decisions/*.md)
      npx ajv validate -s scripts/memory-schemas/adr.schema.json -d "$f"
      ;;
    memory/sessions/*.md)
      npx ajv validate -s scripts/memory-schemas/session.schema.json -d "$f"
      ;;
    memory/handoffs/*.md)
      npx ajv validate -s scripts/memory-schemas/handoff.schema.json -d "$f"
      ;;
    resources/js/Pages/**/*.charter.md)
      npx ajv validate -s scripts/memory-schemas/charter.schema.json -d "$f"
      ;;
  esac
done
```

## Workflow do skill

### Pre-Write/Edit (PROATIVO)

Antes de `Write` ou `Edit` em arquivo memory/*:

1. Identificar tipo (SPEC/RUNBOOK/ADR/Session/Handoff/Charter)
2. Carregar schema canon (lista acima) — match estrito
3. Slug de ADR sempre via Glob `memory/decisions/NNNN-*.md` (NUNCA inventar)
4. Strings/dates sempre quoted no YAML
5. Status: usar enum exato (consultar `scripts/memory-schemas/<tipo>.schema.json` se dúvida)
6. RUNBOOK: owner é letra única (W/F/M/L/E)
7. SPEC novo: já inclui seção `## User stories` mesmo se vazia

### Pre-commit (REATIVO)

Antes de `git commit` em PR que toca paths `memory/*`:

1. Rodar validador local (snippet batch acima) nos arquivos staged
2. Se erro: fix antes commit (evita CI fail loop ~10min/iteração)
3. Se passing: commit + push tranquilo

## Casos especiais aprendidos (2026-05-25)

### Slugs duplicados ADR

Alguns números têm 2 ADRs com slugs diferentes:
- `0101-tests-business-id-1-nunca-cliente` (tests)
- `0101-sistema-charter-capterra-governanca-escopo` (charter)
- `0119-paralelismo-sessoes-whats-active-tier-1` vs `0119-migration-factory-capacidade-institucional`
- `0141-skill-migracao-blade-react` vs `0141-agents-tool-use-pattern-claude-code`
- `0170-paymentgateway-extracao-camada-cobranca` vs `0170-onda5-simplificada`

Escolher o slug relevante ao contexto (ler o `## Context` do ADR pra confirmar).

### PaymentGateway/SPEC.md (scaffold novo)

Quando criar SPEC.md scaffold pra módulo novo, **incluir seção `## User stories` mesmo se vazia**:

```markdown
## User stories — Onda <N> <contexto>

(Backlog inicial — US-XYZ-001 a US-XYZ-NNN abaixo)

### US-XYZ-001 · <título>
...
```

Não usar só `## Onda Audit Sênior` ou `## Backlog inicial` — o schema gate procura por uma das 3 palavras-chave.

### `owners: ["@user"]` plural

Quando RUNBOOK pre-existente tinha `owners: ["@wagner"]` plural, ADICIONAR `owner: W` singular (mantendo `owners` plural pra compat com tools legacy que podem ler):

```yaml
---
owner: W                  # NOVO singular pro schema
owners: ["@wagner"]       # legacy preservado
---
```

### Frontmatter "status com comentário inline"

❌ Errado:
```yaml
status: em-implementacao (Onda 4 P0 — US-COPI-107..109)
```

✅ Certo (mover info pra Histórico):
```yaml
status: ativo
```

E adicionar no corpo:
```markdown
## Histórico
- v3.0.0 (2026-05-20) — Onda 4 P0 (US-COPI-107..109) entregue.
```

## ROI mensurado

- **Sessão 2026-05-25:** 4 PRs (#1568/#1569/#1570/#1579) bloqueados em CI fail loop = ~40min total perdido (push → wait CI 60-90s → fix → push → repeat)
- **Skill ativa:** 0 iterações CI fail pra schema = ~10min economizados por PR com SPEC/RUNBOOK/Charter novo/modificado
- **Frequência:** ≥10 PRs/semana tocam memory/* em sessões ativas (Audit Sênior + implementações)
- **Economia estimada:** ~100min/semana (1.7h)

## Refs

- `scripts/memory-schemas/spec.schema.json` — SPEC JSON Schema AJV
- `scripts/memory-schemas/runbook.schema.json` — RUNBOOK JSON Schema (owner enum)
- `scripts/memory-schemas/adr.schema.json`
- `scripts/memory-schemas/session.schema.json`
- `scripts/memory-schemas/handoff.schema.json`
- `scripts/memory-schemas/charter.schema.json`
- `.github/workflows/memory-schema-gate.yml` — CI gate único (AJV/frontmatter + sub-checks do corpo · FUNDIDO ADR 0314 F2; absorveu o ex-`memory-schema-gate-extended.yml`)
- `.github/scripts/validate-memory-schema.sh` — script bash extra (SPEC/Session/Handoff sections), invocado pelos jobs `validate-*-schema` do gate fundido
- `scripts/governance/anchor-lint.mjs` — lint do corpo `**Implementado em:**` (advisory F1) + `.github/workflows/anchor-drift.yml`
- Sessão 2026-05-25 — origem do skill (4 PRs blocked + batch fix)
- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (memory artifacts canônicos)
- [ADR 0095](../../../memory/decisions/0095-skills-tiers-convencao-interna.md) — Skills tiers convenção interna
- [ADR 0273](../../../memory/decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) — formato anchor spec↔código + sentinela `_pendente_` + key `anchor_format` (fluxo novo de SPEC)

## Pegadinhas validadas em CI real (2026-06-11 — 2 handoffs mergeados com gate vermelho)

Reds não-required que viram CRUFT permanente (append-only proíbe fix-forward de handoff mergeado):

| Doc | Campo | Regra EXATA (ajv) | Erro real pego |
|---|---|---|---|
| handoff | `prs` | array de **INTEIROS** — `prs: [2547, 2549]`, NUNCA `["2547"]` | `/prs/0 must be integer` (handoff 14:30) |
| handoff | `date`/`slug`/`tldr` | os 3 são `required` — `hour_brt`/`topic` NÃO substituem | handoff 12:05 sem slug/tldr |
| runbook | `title`/`owner`/`last_validated` | required; `owner` enum `W/F/M/L/E`; `status` enum `rascunho/ativo/arquivado/historical` (`ready-for-execution` é INVÁLIDO) | RUNBOOK Crm no #2539 |
| todos | datas | SEMPRE string quoted `"2026-06-11"` | — |

**Regra de ouro:** handoff é append-only DEPOIS do merge — validar é ANTES do push ou nunca. O gate roda só em arquivos changed, então o red é 1× por PR, mas fica pra sempre no histórico do PR.
