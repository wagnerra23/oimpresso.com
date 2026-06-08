# Sub-agent prompt — Dimensão 3: ADR lifecycle index

> Prompt canônico do sub-agent #3 da skill `audit-constituicao`.
> Output PT-BR. Limite: ≤800 palavras no diagnóstico final.
>
> ⚠️ **Audit mais caro (~89k tokens).** Varre 119+ ADRs frontmatter. Rodar isolado se short on credits.

## Missão

Auditar consistência do índice de lifecycle das ADRs em `memory/decisions/`:
- Toda ADR tem frontmatter com `lifecycle:` válido (`active`, `historical`, `superseded`, `draft`)?
- `_INDEX-LIFECYCLE.md` (se existir) reflete o estado real de todas?
- Cadeias `supersedes:` / `superseded_by:` são íntegras (pares simétricos, sem órfãs)?
- Toda ADR tem `owner:` declarado?
- ADRs `draft` antigas (>30d) — abandonadas?

## O que fazer (passo a passo)

1. `Glob memory/decisions/*.md` → lista completa.
2. Ler frontmatter de cada (apenas frontmatter, não corpo) — usar Read com `limit: 30` é suficiente.
3. Tabular: number, slug, lifecycle, owner, supersedes, superseded_by, created_at.
4. Validar:
   - Frontmatter válido? (yaml parsable, campos canônicos)
   - `lifecycle` setado e valor permitido?
   - Se `supersedes: [N]` declarada, ADR N existe E tem `superseded_by: [esta]` recíproca?
   - Se `superseded_by: [N]`, lifecycle deveria ser `superseded` (não `active`)?
   - Owner declarado?
   - `draft` com `created_at` >30d atrás?
5. Se `memory/decisions/_INDEX-LIFECYCLE.md` existir, comparar índice com o estado real e listar dessincronias.

## Como entregar

```markdown
# Dimensão 3 — ADR lifecycle index

## Saúde: 🟢/🟡/🔴
## Headline (1 frase): <ex: "117 de 119 ADRs íntegras; 2 supersedes órfãs em 0066+0102">

## Métrica
- Total ADRs: <N>
- active: <N>
- historical: <N>
- superseded: <N>
- draft: <N>
- sem lifecycle: <N>
- sem owner: <N>
- supersedes broken: <N>
- _INDEX dessincronias: <N>

## Achados detalhados (top 15)

### Sem lifecycle
| ADR | Slug | Razão | Ação sugerida |
|---|---|---|---|
| NNNN | <slug> | frontmatter não declara | adicionar `lifecycle: active` se canônica ativa |

### Supersedes broken
| ADR A | Declara supersedes | ADR B | Estado B | Problema |
|---|---|---|---|---|
| 0102 | supersedes: [0099] | 0099 | active | B deveria ser superseded |

### Draft stale (>30d)
| ADR | created_at | Idade | Owner | Ação |
|---|---|---|---|---|
| NNNN | YYYY-MM-DD | XXd | wagner | promover a active OU mover pra historical |

### _INDEX dessincronias (se _INDEX-LIFECYCLE.md existe)
| ADR | Estado real | Estado no índice | Ação |
|---|---|---|---|

## Recomendação 3-tiers

- **Tier A (safe agora):** adicionar `lifecycle:` em ADRs que claramente são `active` mas só faltou frontmatter; sincronizar _INDEX
- **Tier B (precisa ADR):** se reclassificação de status muda o canon (ex: ADR active → historical) — discussão Wagner antes
- **Tier C (backlog):** revisão geral semestral; criar tooling MCP `adr-validate` se ainda não existe
```

## Heurística de saúde

- 🟢 100% ADRs com lifecycle válido + 0 supersedes broken + _INDEX sincronizado
- 🟡 1-5 ADRs sem lifecycle OU 1-2 supersedes broken
- 🔴 >5 sem lifecycle OU >2 supersedes broken OU _INDEX dessincronizado >10% OU ADR canônica órfã (referenciada por outras mas não existe)

## Restrições

- NÃO editar ADR diretamente (append-only — `proibições.md`). Só listar gap.
- Pra ADR canônica (mãe — ex: 0094), gap é Tier 0 e deve aparecer em "Achados Tier 0" do consolidador.
- Output deve caber em ~800 palavras — usar tabelas truncadas (top N) com nota "ver completo em <link>".
- Se Glob retornar >150 ADRs, reportar contagem mas amostrar 50 representativas (10 mais recentes + 10 mais antigas + 30 aleatórias) pra economia de tokens.
