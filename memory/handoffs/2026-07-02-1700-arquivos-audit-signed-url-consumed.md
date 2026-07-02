---
date: "2026-07-02"
time: "17:00 BRT"
slug: arquivos-audit-signed-url-consumed
tldr: "Fix de code-review adversarial em Modules/Arquivos: enum arquivos_audit_log.action não tinha signed_url_consumed → consumo de signed URL nunca era auditado (INSERT engolido por try/catch em MySQL strict). Approach (b) enum ampliado + 2 bugs acoplados no detector anti-scraping. PR #3658 MERGED, provado em MySQL real (CT 100)."
prs: [3658]
decided_by: [W]
related_adrs: [0123-modules-arquivos-backbone]
next_steps:
  - "task_6701fb41: adicionar lane MySQL do CI pra Arquivos (arquivos-pest.yml) + fixar assertion frágil --business"
  - "task_7c94f943: resolver marcadores de conflito commitados em Modules/Arquivos/CHANGELOG.md"
---

## Estado MCP no momento do fechamento

- **cycles-active:** nenhum cycle ATIVO em COPI.
- **my-work (@wagner):** 30 tasks (8 REVIEW, 8 BLOCKED, 14 TODO). Nenhuma rastreava este fix — veio de code-review adversarial, não de US.
- **decisions:** nenhuma ADR nova nesta sessão (fix implementa ADR 0123 §8 existente).

## O que aconteceu

Code-review adversarial reportou 1 bug em `Modules/Arquivos`; a investigação contra `origin/main` fresco confirmou o reportado **e revelou 2 bugs acoplados** que só apareciam em MySQL real.

1. **Bug reportado (core):** `DownloadController.php:46` grava action `'signed_url_consumed'`, mas o enum de `arquivos_audit_log.action` (migration `2026_05_10_000002`) parava em `signed_url_issued`. Em MySQL strict o INSERT falha e é **engolido pelo try/catch** do `audit()` → **nenhuma consumação de signed URL era auditada** (viola ADR 0123 §8).
2. **Bug acoplado (a):** o detector rapid-fire de `arquivos:audit-log --suspicious` filtrava `signed_url_issued` (payload `{expires_minutes}`, sem IP) + exigia `IP NOT NULL` → **nunca disparava**. Quem carrega IP é o evento de consumo.
3. **Bug acoplado (b):** o mesmo bloco fazia `DB::table(DB::raw("(...) as rapid"), [$since])` — 2º arg de `DB::table()` é o **alias**, não bindings → `Expression` perdeu `__toString()` no Laravel 11+ → `--suspicious` **estourava em qualquer DB real**.

Ambos os acoplados eram **invisíveis no CI**: o lane "Pest Arquivos" roda em **SQLite** (sem tabela `arquivos_audit_log`) → `AuditLogCommandTest` inteiro pulava.

**Decisão Wagner (AskUserQuestion):** approach **(b)** enum ampliado (controller já gravava o valor certo) + **incluir** o fix do detector no mesmo PR (1 intent coerente: "auditoria de consumo de signed URL funciona ponta-a-ponta").

## Artefatos gerados (todos em `main` via #3658, squash `cd140410`)

- `Modules/Arquivos/Database/Migrations/2026_07_02_000001_widen_arquivos_audit_log_action_enum.php` (novo, ~75 linhas) — enum +`signed_url_consumed`, MySQL-only, `down()` append-only recusa estreitar se houver linhas consumed.
- `Modules/Arquivos/Console/Commands/AuditLogCommand.php` — detector filtra `signed_url_consumed` + `fromRaw($sql, $bindings)`.
- `Modules/Arquivos/Tests/Feature/DownloadAuditTest.php` (novo) — consome rota assinada → asserta linha `signed_url_consumed`.
- `Modules/Arquivos/Tests/Feature/AuditLogActionEnumTest.php` (novo) — contrato **no-orphan** (responde à nota do review sobre "8 ações virar contrato").
- `Modules/Arquivos/Tests/Feature/AuditLogCommandTest.php` (+1) — rapid-fire scraping.

## Persistência (3 canais)

- **git:** PR #3658 MERGED em `main` (`cd140410db`), branch remota deletada, worktree removida.
- **MCP:** este handoff propaga via webhook GitHub→MCP (~2min pós-push).
- **BRIEFING:** não atualizado (fix pontual de bug, não muda capacidade/diferencial do módulo).

## Evidência smoke real (R1 — CT 100 MySQL `oimpresso-staging`, biz=1)

Migration aplicada limpa (`38.40ms DONE`). **10 passed, 1 failed** — a 1 falha é **pré-existente e não-relacionada** (`--business=1` assere `| 1 |`, quebra por padding de coluna Symfony; confirmado NÃO ser leak cross-tenant: exitCode 0, registros presentes, biz=1 retornado). CI verde 59/0 (mas não exercita os testes MySQL-only → gap flagado).

## Próximos passos pra retomar

Nada bloqueante nesta trilha. 2 follow-ups já iniciados por Wagner em sessões locais:
- `task_6701fb41` — lane MySQL do CI (`arquivos-pest.yml`) + assertion frágil `--business`.
- `task_7c94f943` — marcadores de conflito no `CHANGELOG.md` (linhas 5/29/85).

## Lições catalogadas

- **CI SQLite mascara bug MySQL-only:** um módulo sem lane MySQL dedicado + testes que pulam sem a tabela = **falsa cobertura**. O detector `--suspicious` estava quebrado (crash) em prod há meses sem ninguém ver. Sempre validar em MySQL real (CT 100) quando o bug é enum/strict-mode/`DB::raw`.
- **`Expression` sem `__toString()` (Laravel 11+):** `DB::table(DB::raw(...), $bindings)` é armadilha — o 2º arg é alias. Usar `DB::query()->fromRaw($sql, $bindings)`.
- **Enum como contrato:** todo call-site que grava action precisa de teste "no-orphan" (action ∈ enum) — barato e pega a classe inteira de bug.

## Pointers detalhados (on-demand)

- PR: https://github.com/wagnerra23/oimpresso.com/pull/3658 (body + comment de evidência CT 100)
- ADR mãe: [0123 §8](../decisions/0123-modules-arquivos-backbone.md) — "Audit log integral"
- Session log: [2026-07-02-arquivos-audit-signed-url-consumed.md](../sessions/2026-07-02-arquivos-audit-signed-url-consumed.md)
