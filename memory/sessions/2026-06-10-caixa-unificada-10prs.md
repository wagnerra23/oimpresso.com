---
date: "2026-06-10"
topic: "Caixa Unificada completa — 9 PRs merged + PR-10 gate W + incidente 500 resolvido (brief CC, mandato aplicar todas)"
authors: [W, C]
tags: [caixa-unificada, atendimento, brief-cowork, mandato-aplicar-todas, incidente-prod]
---

# Sessão 2026-06-10 — Caixa Unificada completa (brief [CC], mandato [W] "aplicar todas")

## Resultado

9 PRs MERGED + PR-10 aberto (gate [W]) + 2 hotfixes de incidente — tudo no dia.

| # | PR | Conteúdo |
|---|---|---|
| #2504 | sync | protótipos V2 → prototipo-ui/prototipos/caixa-unificada/ |
| #2503 | PR-1 | US-WA-302 assignee picker (Tier 0 cross-tenant 422) |
| #2506 | PR-2 | US-WA-303 composer: templates por provider + macros `/` (backend US-WA-048 REUSADO — §10.4 evitou tabela duplicada) + variáveis com preview |
| #2507 | PR-3 | US-WA-301 filas DB (ADR 0267, seed lazy, QueuesSheet CRUD) |
| #2509 | PR-4 | US-WA-305 queue_override (vence heurística; slug órfão cai no fallback) |
| #2511 | PR-5 | US-WA-304 ChannelsDrawer (zero backend novo) |
| #2512 | PR-6 | US-WA-307 nova conversa (find-or-create reabre; msg inicial reusa send) |
| #2514 | PR-7 | US-WA-306 broadcast FASE 1 (ADR 0268; opt-in LGPD; disparo = fase 2) |
| #2517 | PR-8 | polish V2 7/8 (SLA pill, cheat-sheet, lightbox, mobile tabs, favoritos LS, transcript, apresentação; cmd-K = TODO honesto) |
| #2518 | PR-9 | IA real na thread (laravel/ai InboxAssistAgent; PiiRedactor; dry_run) |
| #2513 | PR-10 | cutover: charter Inbox → historical — ABERTO, NÃO MERGEAR sem OK [W] |

## Incidente prod (16:5x BRT) — "carregando canais erro 500"

- Causa: deploy parcial (auto-pull do main sem migrate na janela) → `buildQueuesAdminPayload` sem guard derruba o grupo `Inertia::defer` INTEIRO (sintoma: spinner "Carregando canais…" preso + 500 no partial reload).
- Fix: #2515 hotfix degrade gracioso (try/catch → lista vazia + warning; princípio duro 8) + #2516 workflow `debug-caixa-logs.yml` (one-shot read-only: commit deployado, migrate:status, hasTable, ERRORs do log).
- Estado final verificado via workflow: prod = main, `whatsapp_queues` [176] Ran, tabelas 1/1/1, ZERO production.ERROR.
- **Lição (vira reflexo):** payload deferred novo SEMPRE nasce com try/catch — deploy-ordering (código chega antes do migrate) é o caso normal, não exceção; um payload sem guard derruba todos os irmãos do grupo defer.

## Outras lições da sessão

- Stacked-merge: PR só entra na fila com baseRefName=main confirmado; na cadeia local, `git rebase --onto origin/main HEAD~1` por PR (squash quebra ancestry — re-rebase da cadeia inteira conflita com versões antigas dos próprios commits).
- `git stash pop` em worktree compartilhado aplicou stash ALHEIO (charter-gate de outra sessão, com conflitos UU) — preferir commit WIP a stash; o stash alheio ficou preservado (pop com conflito não dropa).
- Gates que só o CI vê (ui:lint PHP, ADR module vocab lowercase, kind enum, PII literal em fixtures de teste) custaram 1 rodada extra por PR — checklist local desta sessão: layout:check + lint:baseline:check + conformance:check + tsc grep.
- Charter schema: enum de status não tem `historical` (usar `deprecated` + semântica no corpo); `related_adrs` como slugs completos (ints YAML com zero à esquerda viram octal/string e falham o schema).
- Flaky de infra no CI (composer clone Azure 503, docker pull timeout) ≠ falha de diff — conferir o log antes de "consertar" o que não quebrou.

## Pendências (gate [W])

1. **PR-10 #2513** — OK do Wagner pra mergear (depois: PR de remoção física de `Pages/Atendimento/Inbox/`).
2. **Broadcast fase 2** — extrair dispatch do `send()` pra Service + Job rate-limited anti-ban (ADR 0268).
3. **cmd-K estendido** — convs/contatos no palette global PMG-002 (US cross-módulo).
4. ADRs 0267/0268 `status: proposto` → `aceito` (flip de 1 linha após ratificação [W], regra do gate append-only).
