# `.claude/rules/` — instruções path-scoped do oimpresso

> **Feature Anthropic 2026** ([docs](https://code.claude.com/docs/en/memory#organize-rules-with-claude/rules/)) — markdown files com frontmatter `paths:` que **só carregam quando Claude lê arquivos matchando o glob**. Mesma prioridade de `CLAUDE.md`, escopo cirúrgico.

## Por que usamos

`CLAUDE.md` raiz (95 linhas + imports) carrega em **toda sessão**. Boa pra: princípios globais (Tier 0 multi-tenant, Constituição v2, skills Tier A). Custo: ~3-4k tokens iniciais.

Rules path-scoped carregam **só quando** Claude toca arquivos do path declarado. Ideal pra: regras específicas de área (Modules/, Pages/, Migrations/, routes/, Commands/). Economia estimada: ~10-15k tokens iniciais por sessão típica.

Ver dossier: [`memory/sessions/2026-05-15-arte-claude-rules-path-scoped.md`](../../memory/sessions/2026-05-15-arte-claude-rules-path-scoped.md) e ADR proposta [`memory/decisions/proposals/claude-rules-path-scoped.md`](../../memory/decisions/proposals/claude-rules-path-scoped.md).

## Rules ativas (Fase 3 — 2026-05-15)

| Arquivo | Paths | Tema |
|---|---|---|
| `modules.md` | `Modules/**/*.php` | Pré-flight + multi-tenant + commit-discipline |
| `pages.md` | `resources/js/Pages/**/*.tsx` | Charter + MWART + Inertia::defer |
| `migrations.md` | `**/Database/Migrations/*.php` | Idempotência + business_id + down() |
| `routes.md` | `routes/web.php`, `routes/*.php` | FQCN obrigatório (sem string legacy) |
| `commands.md` | `app/Console/Commands/**/*.php`, `Modules/**/Console/**/*.php` | `--detail` em vez de `--verbose` |

## Convenções internas oimpresso

- **PT-BR** em todo conteúdo (consistente com codebase + time MCP brasileiro)
- **≤30 linhas** por rule (focada — Anthropic recomenda "1 preocupação por arquivo")
- **Link ADR canônica** sempre que possível
- **Rule não substitui skill** — skills são interativas (description matching), rules são contexto passivo

## Quando criar rule nova

Use rule path-scoped se:

- ✅ Regra aplica a **path específico** (não a todo projeto)
- ✅ Conteúdo NÃO precisa estar em contexto quando Claude trabalha em outras áreas
- ✅ Time inteiro precisa ver (versionada no git)

Use skill em vez de rule se:

- ❌ Workflow multi-step interativo (rule é só leitura passiva)
- ❌ Trigger por intenção do usuário, não por path

Use `CLAUDE.md` raiz se:

- ❌ Princípio global Tier 0 (multi-tenant, Constituição v2, Tier A skills list)
