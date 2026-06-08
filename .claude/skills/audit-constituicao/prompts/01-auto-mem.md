# Sub-agent prompt — Dimensão 1: Auto-mem MEMORY.md vs ADR 0061

> Prompt canônico do sub-agent #1 da skill `audit-constituicao`.
> Output PT-BR. Limite: ≤500 palavras no diagnóstico final.

## Missão

Auditar a auto-memória privada do Wagner em `~/.claude/projects/D--oimpresso-com/memory/MEMORY.md` (e qualquer arquivo `*.md` na mesma pasta) contra a regra IRREVOGÁVEL da [ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md): **zero auto-mem privada — todo conhecimento canônico vive em git/MCP, não em ~/.claude**.

## O que fazer (passo a passo)

1. Ler `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\MEMORY.md` na íntegra (e listar outros .md na mesma pasta).
2. Pra cada bullet/entry da auto-mem, classificar em uma das 4 categorias:
   - **CANON-OK** — fato refletido em git (ADR, SPEC, README, memory/*.md) e auto-mem é só atalho/index.
   - **STALE** — refere path/módulo/conceito que não existe mais (ex: `Modules/Copiloto/` renomeado pra `Modules/Jana/`, link quebrado, ADR superseded).
   - **ÓRFÃO** — fato existe SÓ na auto-mem; nenhum git/MCP equivalente. Risco: viola ADR 0061.
   - **PII-LEAK** — auto-mem contém CPF/CNPJ/dado de cliente real (Larissa/ROTA LIVRE detalhado com PII). Tier 0.
3. Validar paths citados (ex: `feedback_*.md`, `project_*.md`, `reference_*.md` na auto-mem) contra arquivos git correspondentes em `memory/`.

## Como entregar

Output em markdown:

```markdown
# Dimensão 1 — Auto-mem MEMORY.md vs ADR 0061

## Saúde: 🟢/🟡/🔴
## Headline (1 frase): <achado principal>

## Métrica
- Total entries: <N>
- CANON-OK: <N>
- STALE: <N>
- ÓRFÃO: <N>
- PII-LEAK: <N>

## Top achados (≤8)

| Categoria | Entry curta | Path/ref | Ação sugerida |
|---|---|---|---|
| STALE | "Modules/Copiloto = arquitetura X" | aponta pra módulo renomeado pra Jana | atualizar entry ou deletar |
| ÓRFÃO | "Cliente X usa fluxo Y custom" | sem ADR/SPEC equivalente | criar ADR `lifecycle: historical` ou apagar |
| PII-LEAK | "<exemplo redatado>" | linha N MEMORY.md | redact + tier 0 |
| ... | | | |

## Recomendação 3-tiers (input pro consolidador)

- **Tier A (safe agora):** <ações triviais — apagar entries claramente stale>
- **Tier B (precisa ADR):** <migrações que mudam canon — promover fato órfão a ADR>
- **Tier C (backlog):** <revisões opcionais>
```

## Heurística de saúde

- 🟢 0 PII-LEAK + 0 ÓRFÃO crítico + ≤2 STALE
- 🟡 0 PII-LEAK + 1-3 STALE OU 1 ÓRFÃO não-crítico
- 🔴 ≥1 PII-LEAK OU ≥1 ÓRFÃO crítico (decisão arquitetural só na auto-mem) OU >3 STALE

## Restrições

- NÃO sugerir bulk-delete da auto-mem (Wagner decide).
- NÃO editar `~/.claude/projects/.../MEMORY.md` (hook `block-automem.ps1` bloqueia Write/Edit lá; só leitura).
- Se entry referencia ADR superseded, marcar STALE + sugerir apontar pra ADR atual.
- PII reais (CPF/CNPJ) NUNCA citar literal no output — usar `[REDACTED]`.
