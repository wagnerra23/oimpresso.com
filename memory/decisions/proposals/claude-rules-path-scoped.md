---
status: proposal
title: Adoção de `.claude/rules/` path-scoped (Anthropic 2026)
proposed_by: Wagner + Claude
proposed_at: 2026-05-15
relates_to:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0061-conhecimento-canonico-git-mcp-zero-automem
---

# PROPOSAL — Adoção de `.claude/rules/` path-scoped

> **Status:** `proposal` — Wagner promove pra ADR aceita (próximo número canônico) após validação prática 2-4 semanas.

## Contexto

Anthropic lançou em 2026 a feature `.claude/rules/` — markdown files com frontmatter `paths:` que **só carregam quando Claude lê arquivos matchando o glob**. Mesma prioridade de `CLAUDE.md`, escopo cirúrgico.

[Dossier audit memória 2026-05-15](../../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) §G3 identificou a feature como gap não-explorado do oimpresso — nota memory infra 87/100 com gap de ~3pp endereçável.

## Decisão proposta

Adotar `.claude/rules/` pra regras path-scoped que NÃO precisam estar em todo contexto:

| Path | Rule file | Tema |
|---|---|---|
| `Modules/**/*.php` | `modules.md` | Pré-flight + multi-tenant + commit-discipline |
| `resources/js/Pages/**/*.tsx` | `pages.md` | Charter + MWART + Inertia::defer |
| `**/Database/Migrations/*.php` | `migrations.md` | Idempotência + business_id + down() |
| `routes/*.php` | `routes.md` | FQCN obrigatório |
| `app/Console/Commands/**/*.php` | `commands.md` | `--detail` em vez de `--verbose` |

## Consequências

**Positivo:**
- `CLAUDE.md` raiz mantém-se enxuto (~100 linhas) com princípios globais Tier 0
- Economia estimada **~10-15k tokens por sessão típica** (rules específicas só carregam quando relevantes)
- Time MCP (Felipe/Maiara/Eliana/Luiz) vê regras path-scoped quando trabalha na área dele — não precisa pingar Wagner
- Versionadas no git → consistência cross-dev

**Negativo:**
- Mais 1 lugar pra manter (5 arquivos novos em `.claude/rules/`)
- Devs precisam entender que rules são contexto passivo, NÃO skills interativas

**Mitigação:**
- README em [.claude/rules/README.md](../../../.claude/rules/README.md) explicita quando criar rule vs skill vs CLAUDE.md
- Audit canon `module-completeness-audit` pode verificar rules ativas vs gaps

## Validação proposta

Felipe (próximo dev MCP entrante) testa o efeito quando começar a trabalhar em `Modules/Sells/` ou `Modules/Crm/`:
- Sessão típica deve consumir menos tokens iniciais
- Edit em `Modules/X/*.php` deve ter rule `modules.md` carregada (verificável via menu Claude Code "view loaded memory")

Após 2-4 semanas de uso real, Wagner promove pra ADR aceita ou rejeita com lessons learned.

## Refs

- [Anthropic docs — claude/rules/ memory](https://code.claude.com/docs/en/memory#organize-rules-with-claude/rules/)
- [Dossier 2026-05-15 §G3](../../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md)
- [ADR 0094 — Constituição v2](../0094-constituicao-v2-7-camadas-8-principios.md) §princípio 1 "Context as a product"
- [.claude/rules/README.md](../../../.claude/rules/README.md)
