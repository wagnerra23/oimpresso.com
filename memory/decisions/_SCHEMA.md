# Schema canônico de ADRs — frontmatter YAML obrigatório

> **MEM-KB-3 / F1 (ADR 0053 → 0059).** Toda ADR em `memory/decisions/*.md` deve abrir com bloco YAML entre `---`. CI rejeita PR sem frontmatter válido. Validador formal: [`_schema.json`](_schema.json).

---

## Por que existe

Sem frontmatter tipado, a coluna `metadata` em `mcp_memory_documents` vira lixeira de strings. Ferramentas MCP (`decisions-search`, `decisions-fetch`) não filtram por `status:aceito` ou `module:copiloto` porque o dado não está estruturado. UI `/copiloto/admin/memoria` mostra filtro só por `type` (hardcoded) — nada além disso é queryable.

Frontmatter YAML resolve em uma jogada: dado vai pra colunas tipadas no DB, filtros viram WHERE clauses reais, Claude consegue descartar ADR superseded automaticamente.

---

## Estrutura mínima (obrigatória)

```yaml
---
slug: 0053-mcp-server-governanca-como-produto
number: 53
title: "MCP server da empresa: governança como produto, não overhead"
type: adr
status: aceito              # rascunho | proposto | aceito | deprecated | superseded
authority: canonical        # canonical | reference | exploratory
lifecycle: ativo            # ativo | arquivado | substituido
decided_by: [W]
decided_at: 2026-04-29
---
```

8 campos obrigatórios. Se algum faltar, o linter Pest e o workflow GH Actions falham.

---

## Estrutura completa (opcionais)

```yaml
---
# ═══ obrigatórios ═══
slug: 0053-mcp-server-governanca-como-produto
number: 53
title: "MCP server da empresa: governança como produto, não overhead"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-04-29

# ═══ classificação ═══
module: core                       # copiloto | financeiro | pontowr2 | core | infra | null
quarter: 2026-Q2                   # quando a decisão foi tomada (YYYY-Qn)
tags: [mcp, governanca, lgpd]

# ═══ relações ═══
supersedes: []                     # [slug, ...] — esta ADR substitui inteiras
supersedes_partially:              # esta ADR substitui parte
  - 0036-replanejamento-meilisearch-first
superseded_by: []                  # [slug, ...] — ADR(s) que substituiu esta
related:                           # links semânticos não-substituidores
  - 0026-posicionamento-erp-grafico-ia
  - 0046-chat-agent-gap-contexto-rico
cites: []                          # ADRs externas/referências citadas

# ═══ governança ═══
pii: false                         # true se o corpo contém PII redactada
review_triggers:                   # condições que reabrem esta ADR
  - manutencao_acima_1d_mes_por_3m
  - oauth_multitenant_consolidado
scope_required: null               # Spatie permission (null = pública pra autenticados)
admin_only: false
---
```

---

## Vocabulário controlado

### `status`
| Valor | Significado |
|---|---|
| `rascunho` | Em escrita, não validada com Wagner |
| `proposto` | Pronta pra revisão, aguarda aprovação |
| `aceito` | Decisão vigente |
| `deprecated` | Não substituída mas obsoleta (não aplicar) |
| `superseded` | Substituída — ver `superseded_by` |

### `authority`
| Valor | Significado |
|---|---|
| `canonical` | Source-of-truth — Claude deve seguir |
| `reference` | Material de apoio, não vinculante |
| `exploratory` | Brainstorm/análise, não é decisão |

### `lifecycle`
| Valor | Significado |
|---|---|
| `ativo` | Em uso |
| `arquivado` | Fora do escopo atual mas histórico relevante |
| `substituido` | ADR mais nova tomou o lugar (ver `superseded_by`) |

### `decided_by`
Iniciais do TEAM.md: `[W]`, `[F]`, `[M]`, `[L]`, `[E]`, ou combinações `[W, F]`.

### `module`
`copiloto` · `financeiro` · `pontowr2` · `memcofre` · `cms` · `officeimpresso` · `connector` · `grow` · `core` · `infra` · `null` (transversal).

---

## Mapeamento pra colunas DB

A migration `2026_05_*_add_typed_cols_to_mcp_memory_documents` (F1) cria estas colunas em `mcp_memory_documents`. O resto continua em `metadata` JSON.

| Frontmatter | Coluna DB | Tipo |
|---|---|---|
| `status` | `status` | `varchar(20)` indexed |
| `authority` | `authority` | `varchar(20)` indexed |
| `lifecycle` | `lifecycle` | `varchar(20)` indexed |
| `quarter` | `quarter` | `varchar(10)` indexed |
| `decided_at` | `decided_at` | `date` indexed |
| `decided_by` | `decided_by` | `json` |
| `tags` | `tags` | `json` |
| `supersedes` + `supersedes_partially` | `supersedes` | `json` |
| `superseded_by` | `superseded_by` | `json` |
| `related` | `related` | `json` |
| `pii` | `has_pii` | `boolean` |

---

## Convenções

- **Aspas em strings com `:`**, ex.: `title: "MCP: governança como produto"`.
- **Listas vazias explícitas:** `supersedes: []` (não omitir se quiser tornar explícito que nada é substituído).
- **Datas em ISO:** `decided_at: 2026-04-29`. Strings, não objetos.
- **Slug bate com nome do arquivo** sem `.md`. CI verifica.
- **`number` é o prefixo numérico** do filename (`0053-...` → `53`).

---

## Fluxo de criação de ADR nova

1. Copiar [`_TEMPLATE.md`](_TEMPLATE.md) (gerado em F1).
2. Preencher os 8 obrigatórios + relevantes.
3. Commit. Pre-commit hook valida YAML.
4. Push → workflow GH Actions valida contra `_schema.json`.
5. Webhook GitHub → MCP server reindexar (<60s).
6. Tools MCP filtram a nova ADR.

---

## Migração das 60 ADRs antigas (0001-0061 sem 0012)

PR único, batch. Claude infere os 8 obrigatórios a partir do conteúdo (status/data/decisor/módulo extraídos do header existente; `authority` default `canonical`; `lifecycle` default `ativo` ou `substituido` se houver "Supersede"). Wagner revisa e aprova.

Backup automático antes do PR: `memory/decisions/_pre-frontmatter.tar.gz` em commit dedicado.
