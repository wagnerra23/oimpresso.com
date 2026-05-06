# CLAUDE.md novo — proposta ≤100 linhas + imports

> **Status:** 🔴 ESQUELETO — referência pra Sonnet preencher quando autorizado.
> Atual CLAUDE.md tem ~390 linhas. Alvo pós-S3: **≤100 linhas** com imports.

---

## Estrutura proposta (8 blocos, todos enxutos)

```markdown
# CLAUDE.md — Primer pra Claude Code @ oimpresso

> Sempre comece com `mcp__oimpresso__brief-fetch` (skill `brief-first` Tier A).
> Documento estável; mudanças via ADR.

## Por que existe
@memory/why-oimpresso.md

## Stack e estrutura
@memory/what-oimpresso.md

## Como trabalhar (protocolo de sessão)
1. `brief-fetch` → estado consolidado (~3k tokens)
2. `my-work` → minhas tasks
3. `charter-fetch <page>` antes de editar `.tsx` com `.charter.md` ao lado
4. Trabalhar
5. Commit com `Refs: SPRINT-N PASSO M`

@memory/how-trabalhar.md  # detalhes

## Skills Tier A (always-on)
- `brief-first` — força brief-fetch primeiro
- `mcp-first` — usar tools MCP antes de Read filesystem
- `multi-tenant-patterns` — Tier 0 isolation (business_id)
- `commit-discipline` — 1 PR = 1 intent, ≤300 linhas
- (dormente) `charter-first` — aguarda S4
- (dormente) `ads-route` — aguarda S5

## Proibições (Tier 0 — não inventar)
@memory/proibicoes.md

## Time
@memory/regras-time.md

## Constituição v2 — ler em ordem
- L7 Brief: ADR 0091
- L1 MCP CORE: ADR 0053
- (mais) [memory/decisions/](memory/decisions/)

## Como propor mudança
- ADR canon: PR + aprovação Wagner
- ADR histórico: PR opcional
- Tier 0: NÃO sem ADR mãe nova

## Onde NÃO inventar
- Tokens MCP, schema mcp_audit_log, ADRs CANON, business_id global scope, Centrifugo runtime CT 100

---
**Última atualização:** 2026-MM-DD — pós Sprint 3 (Constituição v2)
```

**Total estimado:** ~80 linhas (vs 390 atuais).

---

## Arquivos novos a criar (movendo conteúdo do CLAUDE.md atual)

### `memory/why-oimpresso.md` (~30 linhas)

Conteúdo: §1 atual ("O que é este projeto em 30 segundos") + visão de produto.

### `memory/what-oimpresso.md` (~50 linhas)

Conteúdo: stack real (Laravel/PHP/Inertia/MySQL), módulos canônicos (Jana/Repair/Project), governança ADR 0059, links pra ADRs centrais (0035, 0053, 0070, 0091).

### `memory/how-trabalhar.md` (~80 linhas)

Conteúdo: §2 atual (caminho preferido tools MCP + tabela de perguntas → tool), fluxo de session start, disciplina de contexto (`/compact`, `/clear`), skills auto-ativáveis.

### `memory/proibicoes.md` (~40 linhas)

Conteúdo: §4 atual (não fazer) + §5 (sempre fazer) reduzidas, lista de Tier 0 irrevogáveis.

### `memory/regras-time.md` (~30 linhas)

Conteúdo: §10 atual (equipe interna) — perfis, WIP, matriz quem-pode-pegar-qual-task.

---

## Validação técnica do `@imports`

Claude Code resolve imports recursivos até **5 níveis** ([Anthropic docs](https://platform.claude.com/docs/claude-code)). Validar:

- [ ] Sessão real com `claude --no-input "explica memory/how-trabalhar.md"` — verifica se Claude lê via @
- [ ] Sessão real com mudança em arquivo importado — verifica que cache invalida
- [ ] Sessão real sem brief-first acionando — verifica se `@how-trabalhar.md` ainda força tools MCP

---

## Notas pra Sonnet preencher (quando autorizado)

- Manter tom telegráfico — CLAUDE.md é primer, não tutorial
- Cada `@import` corresponde a 1 arquivo novo em `memory/`
- Mover conteúdo, não duplicar. Após S3, conteúdo só vive nos arquivos importados (single source of truth)
- Total CLAUDE.md ≤100 linhas (medir com `wc -l`)
- Total nos 5 arquivos importados ≤230 linhas (mantém info atual sem inflar)
