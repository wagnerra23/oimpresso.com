---
date: "2026-07-02"
hour: "17:00 BRT"
topic: "Fix code-review adversarial em Modules/Arquivos: enum arquivos_audit_log.action ampliado com signed_url_consumed + 2 bugs acoplados no detector anti-scraping"
authors: [C, W]
---

# Sessão 2026-07-02 — Fix audit signed_url_consumed (Modules/Arquivos)

**TL;DR:** O enum `arquivos_audit_log.action` não aceitava `signed_url_consumed` (que o `DownloadController` grava ao consumir signed URL) → em MySQL strict o INSERT falhava e era engolido pelo try/catch → **consumo nunca auditado** (ADR 0123 §8). Approach (b) enum ampliado + achei **2 bugs acoplados** no detector `--suspicious` (filtro na action errada + `DB::table(DB::raw(),$b)` crash no Laravel 11+). PR #3658 MERGED, provado em MySQL real no CT 100 (10/1, a 1 falha pré-existente/não-relacionada).

> Session log (o trabalho). Estado pro próximo: [handoff 1700](../handoffs/2026-07-02-1700-arquivos-audit-signed-url-consumed.md).

## Origem

Code-review adversarial (worktree balde-d @ dad0b11, código = origin/main) reportou: `DownloadController.php:46` grava audit com action `'signed_url_consumed'` que o enum da migration não aceita → INSERT engolido pelo try/catch em MySQL strict → consumo de signed URL nunca auditado.

## Investigação (contra origin/main fresco)

Confirmado o bug + mapeados os call-sites de cada action:
- **Vivos:** `upload`/`reclassify`/`signed_url_issued`/`soft_delete`/`restore` (ArquivosService), `hard_delete` (RetentionCleanupCommand), `signed_url_consumed` (DownloadController — rejeitado pelo enum).
- **Mortos no enum (nenhum call-site):** `download`, `classify`.

Descobertos **2 bugs acoplados** no detector rapid-fire do `AuditLogCommand --suspicious`:
1. filtrava `signed_url_issued` (sem IP) → nunca disparava;
2. `DB::table(DB::raw(...), [$since])` passava bindings como alias → `Expression` sem `__toString()` (Laravel 11+) → **crash** em qualquer DB real.

Ambos invisíveis no CI porque o lane "Pest Arquivos" roda SQLite (sem a tabela → arquivo de teste pula inteiro).

## Decisão (Wagner via AskUserQuestion)

- Approach **(b)** — enum ampliado (controller já correto), preserva semântica issued vs consumed.
- **Incluir** o fix do detector no mesmo PR (mesmo intent).

## Implementação

- Migration nova ampliando enum (MySQL-only, `down()` append-only guardado).
- `AuditLogCommand`: action `signed_url_consumed` + `fromRaw($sql, $bindings)`.
- 3 testes: `DownloadAuditTest` (core), `AuditLogActionEnumTest` (contrato no-orphan), rapid-fire em `AuditLogCommandTest`.

## Verificação (R1 — CT 100 MySQL, overlay git-sourced sem trocar branch da staging)

`10 passed, 1 failed`. A falha é pré-existente/não-relacionada (`--business=1` assere `| 1 |`, padding de coluna, não é leak). Staging restaurada a `main` após; enum widened deixado (superset inócuo).

## Entrega

PR #3658 MERGED (squash `cd140410`), branch + worktree limpas. CI 59/0. 3 task chips: 2 follow-ups (CI MySQL lane + assertion frágil) e 1 CHANGELOG com conflito commitado — todas iniciadas por Wagner.

## Lições

Ver handoff §"Lições catalogadas" (CI SQLite mascara bug MySQL-only; `DB::table(DB::raw(),$b)` armadilha; enum-como-contrato no-orphan).
