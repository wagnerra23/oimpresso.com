---
date: "2026-07-02"
time: "20:45 BRT"
slug: arquivos-pest-mysql-lane
tldr: "Follow-up de #3658: criada a lane MySQL do CI pra Modules/Arquivos (arquivos-pest.yml). Ao ser criada, ela imediatamente pegou um bug real — o comando arquivos:audit-log --suspicious crashava em qualquer MySQL (DB::table(DB::raw,[bind]) → Expression sem __toString). Também trocou a assertion frágil --business por contrato multi-tenant robusto. PR #3666 MERGED. Handoff escrito post-hoc (a sessão irmã não registrou)."
prs: [3666]
decided_by: [W]
related_adrs: [0123-modules-arquivos-backbone]
next_steps:
  - "Nenhum pendente nesta trilha — os 3 gaps de #3658 fechados; guardas de audit rodam VERDES em MySQL real no CI."
---

## Estado MCP no momento do fechamento

- **cycles-active:** nenhum cycle ATIVO em COPI.
- **my-work (@wagner):** 30 tasks (inalterado vs handoff 1700 — este follow-up não era US rastreada).
- **decisions:** nenhuma ADR nova (fecha os follow-ups de ADR 0123 §8).

## O que aconteceu

Fechamento post-hoc de **PR #3666** (`1f3c7b7bfb`, merged 20:40 UTC) — a sessão irmã spawnada de `task_6701fb41` mergeou o follow-up de [#3658](../handoffs/2026-07-02-1700-arquivos-audit-signed-url-consumed.md) mas **não escreveu handoff próprio**. Wagner "mysql feito, pode salvar tudo" → registro aqui pra não deixar o canon com buraco.

Os 3 gaps que #3658 flagou, agora fechados num intent coerente ("guardas de regressão do audit rodam VERDES em MySQL real no CI"):

1. **Gap 1 — falsa cobertura CI:** `Modules/Arquivos` não tinha lane MySQL. A `modules-pest` genérica roda SQLite `:memory:` sem migrate → `AuditLogCommandTest` / `DownloadAuditTest` / `AuditLogActionEnumTest` davam `markTestSkipped` (guardas **decorativos**). **Fix:** `.github/workflows/arquivos-pest.yml` espelhando `jana-pest.yml`/`financeiro-pest.yml` (reusa composite `pest-mysql-setup`, seed biz=1) + entry em `scripts/governance/gates-registry.json`.
2. **Gap 2 — assert frágil** `AuditLogCommandTest:165`: `toContain('| 1 |')` quebrava no MySQL real (padding da coluna Symfony `| 1   |`, não leak). Trocado por contrato Tier 0: **9903 (biz=1) presente E 9904 (biz=2) ausente**.
3. **Gap 3 — DESCOBERTO PELA PRÓPRIA LANE:** a 1ª run (9 passed / 2 failed) expôs que `arquivos:audit-log --suspicious` **crashava em MySQL** — `DB::table(DB::raw("(...) as rapid"), [$since])` jogava o binding na posição do alias → `Expression` sem `__toString()` (Laravel 11+) → estourava acima do try/catch, matando os 3 detectores da ferramenta LGPD. Trocado por `DB::query()->fromRaw($expr, $bindings)`.

> Nota de coerência: o Gap 3 (fromRaw) já tinha sido corrigido no meu #3658; a versão de #3666 venceu no merge (mesmo fix correto). `main` verificado: `fromRaw` presente, **zero** `DB::table(DB::raw(...))` real (só menção em comentário).

## Artefatos (todos em `main` via #3666, `1f3c7b7bfb`)

- `.github/workflows/arquivos-pest.yml` (novo) — lane Pest MySQL.
- `Modules/Arquivos/Console/Commands/AuditLogCommand.php` — `fromRaw`.
- `Modules/Arquivos/Tests/Feature/AuditLogCommandTest.php` — assert multi-tenant robusto.
- `scripts/governance/gates-registry.json` — registra o gate.

## Persistência (3 canais)

- **git:** #3666 MERGED em `main` (`1f3c7b7bfb`).
- **MCP:** este handoff propaga via webhook GitHub→MCP (~2min pós-push).
- **BRIEFING:** não aplicável (infra CI + fix de bug, não muda capacidade do módulo).

## Próximos passos pra retomar

Nada bloqueante. Trilha Arquivos-audit encerrada: bug corrigido (#3658) + cobertura MySQL real no CI (#3666) + CHANGELOG destravado (task_7c94f943, 0 marcadores no main).

## Lições catalogadas

- **A lane MySQL se pagou na 1ª run:** criar o lane MySQL faltante imediatamente pegou um crash de compliance LGPD (`--suspicious`) que estava mascarado há meses porque o CI só rodava SQLite. Prova concreta de "CI SQLite mascara bug MySQL-only = falsa cobertura".
- **Sessão spawnada precisa fechar o próprio loop:** #3666 mergeou sem handoff → o canon ficou com buraco até este registro post-hoc. Task chip que mergeia código deveria também cumprir R12 (ou o pai reconcilia no fechamento, como aqui).

## Pointers detalhados (on-demand)

- PR: https://github.com/wagnerra23/oimpresso.com/pull/3666 (body com os 3 gaps detalhados)
- Handoff irmão (o bug original): [2026-07-02-1700-arquivos-audit-signed-url-consumed.md](2026-07-02-1700-arquivos-audit-signed-url-consumed.md)
- ADR mãe: [0123 §8](../decisions/0123-modules-arquivos-backbone.md)
