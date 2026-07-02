---
date: "2026-07-02"
time: "23:40 BRT"
slug: arquivos-audit-signed-url-consumed-pr-duplicada
tldr: "Bug de audit signed_url_consumed do Modules/Arquivos (report G5) já estava resolvido em main por #3658 (Opção A) quando abri #3659 (Opção B) — fechei a minha como superada. Nada meu mergeou. Lição: checar PRs abertos no mesmo bug, não só base fresca."
prs: [3659, 3658]
related_adrs: [0123-modules-arquivos-backbone]
next_steps: ["Nenhum — bug resolvido em main via #3658 (cd140410db)"]
---

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo** em COPI.
- `my-work` (@wagner): 30 tasks (8 REVIEW · 8 BLOCKED · 14 TODO) — **nenhuma** relacionada a este bug (foi report ad-hoc, não task rastreada).
- HEAD `origin/main` no fechamento: `cd140410db` (#3658, já contém o fix).

## O que aconteceu

Report de refutação adversarial G5 (backfill de âncoras): `DownloadController.php:46` emitia a ação `signed_url_consumed`, ausente do enum estrito `arquivos_audit_log.action` → INSERT rejeitado em MySQL strict, engolido pelo try/catch → **gap silencioso de auditoria de download** (LGPD Art. 37).

Validei o bug contra `origin/main` fresco (base do worktree estava −4622). Decidi **Opção B** (emitir `download`, membro do enum já usado no fixture de `AuditLogCommandTest`, sem DDL) e abri **[#3659](https://github.com/wagnerra23/oimpresso.com/pull/3659)** com fix + 3 camadas de Pest. Checks que exercitam o código passaram verde (Pest Arquivos, PHPStan, Tier-0 guards).

O `<ci-monitor-event>` de **conflito de merge** revelou que uma **sessão paralela já tinha mergeado o mesmo fix ~1h antes** — **[#3658](https://github.com/wagnerra23/oimpresso.com/pull/3658)** (`cd140410db`), via **Opção A**: migration append-only `2026_07_02_000001_widen_arquivos_audit_log_action_enum.php` amplia o enum com `signed_url_consumed` + mantém o controller + conserta o detector anti-scraping (insight melhor que o meu: o filtro `IP IS NOT NULL` zerava porque só o evento *consumed* carrega `{ip}` no payload; `issued` só tem `{expires_minutes}`).

Fechei **#3659 como superada** (não force da Opção B — reverter a migration mergeada seria re-litígio destrutivo de decisão fechada). Deletei o branch remoto, removi meu worktree (sem junction `vendor` — safe).

## Artefatos gerados

- **Nenhum em canon.** #3659 fechado sem merge; branch `claude/arquivos-download-audit-action` deletado; worktree removido.
- Este handoff + linha no índice.

## Lições catalogadas

1. **Checar PRs abertos no mesmo bug ANTES de começar**, não só partir de `origin/main` fresco. O stale-base guard disparou e eu parti de main fresco, mas o fix paralelo landeou DEPOIS de eu ramificar. Um `gh pr list --search "arquivos audit"` no início teria pego #3658. Reforça o padrão já catalogado de **sessões paralelas** (Wagner replica prompt em 2-3 sessões — `how-trabalhar.md` + `whats-active`).
2. A "correção" que fiz ao report (a regra de scraping usava `signed_url_issued`, não `signed_url_consumed`) era, na verdade, **a segunda metade real do bug** — #3658 diagnosticou melhor (dependência do IP no payload).
3. Opção A (ampliar enum) venceu Opção B (reusar `download`) por chegar primeiro; ambas eram válidas. Decisão fechada e mergeada = não re-abrir destrutivamente.

## Pointers detalhados

- Fix canônico: `cd140410db` / [#3658](https://github.com/wagnerra23/oimpresso.com/pull/3658) — controller + migration widen-enum + AuditLogCommand rule #3.
- Contexto do módulo: [ADR 0123](../decisions/0123-modules-arquivos-backbone.md) §8 (audit append-only).
