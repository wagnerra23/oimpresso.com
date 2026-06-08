# scripts/memory-schemas — JSON Schemas canônicos pra frontmatter

> **ONDA 5 S1 — Schema rígido CI** ([ONDA-5-DOSSIER §5](../../memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md))
>
> Schema validation per-type pros .md de `memory/` + Charters em `resources/js/Pages/`.
> Híbrido **A + C**: AJV via GitHub Actions matrix (gate PR) + `php artisan jana:validate-memory` (gate local + cron daily 06:30 BRT).

## Mapa file glob → schema

| Glob | Schema | Tipo |
|---|---|---|
| `memory/decisions/*.md` (exceto `_*.md`) | `adr.schema.json` | ADR Nygard |
| `memory/requisitos/*/SPEC.md` | `spec.schema.json` | Spec por módulo |
| `memory/requisitos/**/RUNBOOK*.md` | `runbook.schema.json` | Runbook procedural |
| `memory/sessions/*.md` (exceto `_*.md`, `README.md`) | `session.schema.json` | Session log diário |
| `memory/handoffs/*.md` (exceto `_*.md`) | `handoff.schema.json` | Handoff append-only |
| `resources/js/Pages/**/*.charter.md` | `charter.schema.json` | Page Charter (Tier A) |

## Grace period 14d (ENV `JANA_VALIDATE_MEMORY_STRICT`)

| ENV value | Comportamento CI | Comportamento artisan |
|---|---|---|
| `false` (default 14d) | continue-on-error (warning) | exit 0, warning no stdout |
| `true` (após Wagner sign-off) | bloqueia merge | exit 1 |

## Como manter

1. **Adicionar campo novo:** edite o `*.schema.json`, rode `php artisan jana:validate-memory` local; se passar, PR.
2. **Adicionar tipo novo:** crie `<tipo>.schema.json` + adicione glob no [.remarkrc.json](../../.remarkrc.json) + matrix de [.github/workflows/memory-schema-gate.yml](../../.github/workflows/memory-schema-gate.yml) + case no `JanaValidateMemoryCommand::detectSchemaForPath()`.
3. **Mudar required:** lembrar grace period — campo novo deve ser opcional por default até backfill rodar.

## Decisão arquitetural

- **A. AJV (Node)** — gate PR, padrão indústria, plugin remark.
- **C. PHP `justinrainbow/json-schema` 5.3.4** (já em composer.lock como transitive dep) — gate local + cron.

Rejeitados: B (`frontmatter-json-schema-action` magro), D (pre-commit não enforce em devs externos), E (`cassarco/markdown-tools` sem JSON Schema oficial).

## Histórico

- **2026-05-13** — Schemas criados em ONDA 5 S1 (agent schema-validator-expert).
