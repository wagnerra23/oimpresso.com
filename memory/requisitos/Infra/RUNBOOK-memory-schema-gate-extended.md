---
title: "RUNBOOK — Memory schema gate EXTENDED (SPEC/Session/Handoff)"
module: Infra
owner: W
status: ativo
last_validated: "2026-05-15"
preconditions:
  - "GitHub Actions habilitado no repo"
  - "Python 3.x disponível no runner (ubuntu-latest tem default)"
  - "Workflow `.github/workflows/memory-schema-gate-extended.yml` em main"
steps:
  - "Detectar arquivos memory/ modificados no PR"
  - "Rodar `validate-memory-schema.sh <type> <file>` por categoria"
  - "Postar comment PR com violations se houver erros"
  - "Bloquear merge se exit 1"
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0094-constituicao-v2-7-camadas-8-principios"
---

# RUNBOOK — Memory schema gate EXTENDED

> **Origem:** D6 #4 da auditoria `memoria-senior` 2026-05-15. Score afetado: +1pp (86 → 87).
> **Complementa:** workflow existente `memory-schema-gate.yml` (que valida só YAML frontmatter via AJV).
> **Owner:** Wagner (até time MCP entrar e Felipe assumir).

## O que este gate valida

O gate EXTENDED roda em PRs que mexem em `memory/requisitos/**/SPEC.md`, `memory/sessions/**/*.md` ou `memory/handoffs/**/*.md`. São **3 jobs paralelos**:

### Job 1 — `validate-spec-schema`

Valida `memory/requisitos/<Mod>/SPEC.md`:

| Check | Tipo | Mensagem se falhar |
|---|---|---|
| Frontmatter YAML presente | ❌ erro | "SPEC sem frontmatter YAML" |
| Campo `module` (PascalCase) | ❌ erro | "campo obrigatório ausente: 'module'" |
| Campo `last_updated` (YYYY-MM-DD) | ❌ erro | "fora do formato YYYY-MM-DD" |
| Campo `version` (vN.N.N ou N.N.N) | ❌ erro | "fora do formato vN.N.N" |
| Campo `owner` (ou `owners` array) | ❌ erro | "campo obrigatório ausente: 'owner'" |
| Seção `## US ativas` (ou `Backlog ativo`/`User stories`) | ❌ erro | "sem seção '## Backlog ativo'..." |
| Seção `## Histórico` | ⚠️ warning | "sem seção '## Histórico' (recomendado)" |
| Seção `## Referências` | ⚠️ warning | "sem seção '## Referências' (recomendado)" |
| US format `US-<MOD>-<NNN>` (MOD 2-8 letras UPPER, NNN 3-4 dígitos) | ❌ erro | "US malformadas" |

### Job 2 — `validate-session-schema`

Valida `memory/sessions/<filename>.md`:

| Check | Tipo | Mensagem se falhar |
|---|---|---|
| Filename `^YYYY-MM-DD-<slug-kebab>.md$` | ❌ erro | "filename fora do regex" |
| Frontmatter (opcional pra legacy, mas se presente) | ⚠️ warning | "sem frontmatter YAML (legacy aceito)" |
| Campo `date` se frontmatter presente | ❌ erro | "campo 'date' obrigatório ausente" |
| Campo `topic` se frontmatter presente | ❌ erro | "campo 'topic' obrigatório ausente" |
| Seção `## TL;DR` ou `## Resumo executivo` ou `## Contexto` | ❌ erro | "sem TL;DR nem Resumo executivo nem Contexto" |

### Job 3 — `validate-handoff-schema`

Valida `memory/handoffs/<filename>.md` (ADR 0130):

| Check | Tipo | Mensagem se falhar |
|---|---|---|
| Filename `^YYYY-MM-DD-HHMM-<slug-kebab>.md$` | ❌ erro | "filename fora do regex (ADR 0130)" |
| `diff-filter=A` apenas (append-only) | implícito | jobs ignoram modify (M) — ADR 0130 |
| Frontmatter `date`/`slug`/`tldr` se presente | ❌ erro | "campo obrigatório ausente" |
| Seção `## Estado MCP no momento do fechamento` | ❌ erro | "sem '## Estado MCP no momento do fechamento' (ADR 0130 §6)" |
| Seção `## TL;DR` | ⚠️ warning | "sem '## TL;DR'" |

## Como criar SPEC/Session/Handoff válido

### SPEC.md novo
1. Copiar [`memory/requisitos/_TEMPLATE_SPEC.md`](../_TEMPLATE_SPEC.md)
2. Cole em `memory/requisitos/<NomeModulo>/SPEC.md`
3. Substituir `{{PascalCase}}` pelo nome real
4. Preencher 4 campos frontmatter obrigatórios (`module`, `last_updated`, `version`, `owner`)
5. Manter pelo menos 1 seção `## US ativas` ou `## Backlog ativo`
6. Recomendado: `## Histórico` + `## Referências`

### Session log novo
1. Copiar [`memory/sessions/_TEMPLATE.md`](../../sessions/_TEMPLATE.md)
2. Filename: `YYYY-MM-DD-<slug-kebab>.md` (Windows/macOS: filename case-sensitive no GHA Linux)
3. Frontmatter `date` + `topic` (recomendado; legacy sem frontmatter ainda passa com warning)
4. Pelo menos `## TL;DR` ou `## Resumo executivo`

### Handoff novo
1. Copiar [`memory/handoffs/_TEMPLATE.md`](../../handoffs/_TEMPLATE.md)
2. Filename: `YYYY-MM-DD-HHMM-<slug-kebab>.md` (HHMM 4 dígitos, sem `:`)
3. ANTES de escrever: rodar MCP checklist (`cycles-active`/`my-work`/`sessions-recent limit:3`/`decisions-search since:<data>` + `whats-active` se paralelo)
4. Frontmatter `date`/`slug`/`tldr` (recomendado)
5. Seção `## Estado MCP no momento do fechamento` OBRIGATÓRIA — cole o output das tools MCP (NÃO promessa, prova)

## Como rodar localmente antes do push

```bash
# Linux/WSL/macOS
chmod +x .github/scripts/validate-memory-schema.sh

# Validar SPEC específico
.github/scripts/validate-memory-schema.sh spec memory/requisitos/Jana/SPEC.md

# Validar todos SPECs modificados em relação a main
git diff --name-only main -- 'memory/requisitos/**/SPEC.md' \
  | xargs .github/scripts/validate-memory-schema.sh spec

# Validar session que vai commitar
.github/scripts/validate-memory-schema.sh session memory/sessions/2026-05-15-feature.md

# Validar handoff
.github/scripts/validate-memory-schema.sh handoff memory/handoffs/2026-05-15-2030-fechamento.md
```

```powershell
# Windows nativo (Git Bash recomendado)
bash .github/scripts/validate-memory-schema.sh spec memory/requisitos/Jana/SPEC.md
```

Exit code:
- `0` = sem erros (warnings podem aparecer)
- `1` = erros bloqueantes (corrija antes do push)
- `2` = erro de uso (type inválido)

## Override emergencial — schema-allowlist

Quando inevitável (legacy migrando, doc histórico congelado), adicione no INÍCIO do arquivo:

```markdown
<!-- schema-allowlist: legacy de 2024-12 — Wagner aprovou skip 2026-05-15 -->
```

Efeito: validação pulada, registra warning em `violations.json`. **Use com parcimônia** — se >5 arquivos com allowlist, revisar política.

## Troubleshooting

### "SPEC sem frontmatter YAML"
Causa: arquivo começa com `# Title` em vez de `---\n...\n---`.
Fix: adicionar bloco frontmatter com `module` + `last_updated` + `version` + `owner` no topo. Ver [`_TEMPLATE_SPEC.md`](../_TEMPLATE_SPEC.md).

### "module 'foo' não é PascalCase válido"
Causa: nome em lowercase ou kebab-case. Fix: usar `Jana`, `NfeBrasil`, `RecurringBilling` (cada palavra capitalizada).

### "US malformadas"
Causa: alguma menção tipo `US-x-1` ou `US-foo-99`.
Fix: usar `US-<MOD>-<NNN>` com MOD 2-8 letras MAIÚSCULAS e NNN 3-4 dígitos: `US-COPI-001`, `US-NFE-042`, `US-SELL-008`.

### "Session filename '...' fora do regex"
Causa: nome com maiúscula, underscores, data em formato errado.
Fix: renomear pra `YYYY-MM-DD-slug-em-kebab.md` (tudo lowercase, separador `-`).

### "Handoff filename '...' fora do regex"
Causa: faltou HHMM (4 dígitos) entre data e slug.
Fix: renomear pra `YYYY-MM-DD-HHMM-slug.md`, ex: `2026-05-15-2030-transicao-wagner.md`.

### "Handoff sem '## Estado MCP no momento do fechamento'"
Causa: faltou seção obrigatória ADR 0130.
Fix: adicionar seção com snapshot das tools MCP (cycles-active/my-work/sessions-recent/decisions-search/whats-active). NÃO promessa — paste do output real.

### Python missing local
Causa: `python3` não no PATH no Windows.
Fix: script detecta `python3`/`python`/`py` automaticamente. Se nenhum, instalar Python 3.x ou usar WSL.

### "falha ao LER o frontmatter pro campo 'X' — NÃO é ausência"
Causa: o extractor não conseguiu **medir** o campo — arquivo ilegível, YAML inválido (ex: `tldr: [1, 2` sem fechar), ou frontmatter que não é um mapa. A razão vem no stderr da linha acima.
Fix: consertar o YAML. **Não** é o mesmo que "campo obrigatório ausente" — essa mensagem diz que o campo não está lá; esta diz que ninguém conseguiu olhar. Os dois eram indistinguíveis até 2026-09-15, quando o extractor passou a separá-los por exit code (0 existe · 1 ausente · 3 não-consegui-ler).

### Falso "campo obrigatório ausente" num campo que existe (Windows, até 2026-09-15)
Causa (histórica): o extractor imprimia o valor com `print()`, que usa o encoding do console — cp1252 no Windows. Valor com `→`/`≥`/`↔` estourava `UnicodeEncodeError`, o `2>/dev/null || true` engolia o crash e o campo PRESENTE era acusado de ausente. Medido no corpus: atingia 167 dos 456 handoffs com frontmatter (36,6%) e 79 dos 559 session (14,1%).
Fix: corrigido no script (escrita por `sys.stdout.buffer` em UTF-8 explícito). Se reaparecer, o bite-test que cobre isso é a PERNA 4 de `scripts/tests/memory-schema-detect.test.mjs`, que força `PYTHONIOENCODING=cp1252` pra reproduzir o Windows em qualquer plataforma.

## Sugestões evolução (futuro)

- **Validar links internos quebrados** via `markdown-link-check` (links pra `memory/decisions/NNNN-slug.md` que não existem)
- **Validar relação Filename↔frontmatter.date** (filename `2026-05-15-X.md` deve ter `date: 2026-05-15`)
- **Validar handoff anexa ao índice `memory/08-handoff.md`** (1 linha no topo)
- **Modo strict** (var `MEMORY_SCHEMA_STRICT=true`) onde warnings viram erros
- **Hook pre-commit local** chamando o script (evitar gastar minutos GHA)

## Histórico

| Data | Quem | O que mudou |
|---|---|---|
| 2026-05-15 | W+C | Criação D6 #4 audit `memoria-senior` |
| 2026-09-15 | C | Extractor deixou de colapsar "não consegui ler" em "campo ausente" (exit 0/1/3 + escrita por `sys.stdout.buffer`). Zerou o falso "campo obrigatório ausente" no Windows — 39 FPs numa amostra de 79 arquivos reais, 0 erro legítimo perdido. +2 entradas de Troubleshooting; ponteiro morto do `-extended.yml` atualizado. |

## Referências

- [ADR 0130 — Handoff append-only + MCP-first](../../decisions/0130-handoff-append-only-mcp-first.md)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [Workflow `memory-schema-gate.yml`](../../../.github/workflows/memory-schema-gate.yml) — frontmatter via AJV **e** os sub-checks do corpo deste RUNBOOK. O `memory-schema-gate-extended.yml` que esta linha apontava até 2026-09-15 não existe mais: foi fundido aqui pela ADR 0314 F2, e o ponteiro ficou morto para trás.
- [Script `validate-memory-schema.sh`](../../../.github/scripts/validate-memory-schema.sh)
- Templates:
  - [`_TEMPLATE_SPEC.md`](../_TEMPLATE_SPEC.md)
  - [`memory/sessions/_TEMPLATE.md`](../../sessions/_TEMPLATE.md)
  - [`memory/handoffs/_TEMPLATE.md`](../../handoffs/_TEMPLATE.md)
