---
title: WR2 rewrite histórico — purga de valores BRL Tier 0 + regra perene proibições [E]
date: 2026-06-08
time: "19:00"
owner: Eliana [E]
slug: wr2-rewrite-historico-valores-redacted
related_session: memory/sessions/2026-06-08-wr2-backfill-recurring-2026.md
related_handoff: memory/handoffs/2026-06-08-1800-wr2-backfill-recurring-2026.md
prs: [2435, 2439]
---

# Handoff continuação — rewrite histórico git removendo valores BRL

## TL;DR

Continuação do handoff [1800](2026-06-08-1800-wr2-backfill-recurring-2026.md). Wagner explicitou regra retroativa: Felipe/Maiara/Luiz têm acesso a git canon, então **NUNCA pode haver valor BRL em commit** — eles veem QUE migrou (contagens, escopo) mas não CONTEÚDO (valores monetários).

Vazei em PRs anteriores (#2433/#2434). Wagner autorizou ("faz isso") rewrite histórico. Executado via `git filter-repo` em 5.033 commits + force push origin main.

## Catalisador

Wagner literal 2026-06-08: *"maiara felipe e luiz não podem ter acesso a nada de valores no git. pode saber o que migrou mas não saber o conteudo (valores)"*

## Estado MCP no momento do fechamento

```
cycles-active: CYCLE-08 Receita Onda A (29% decorrido, sem mudança nesta sessão)
my-work (Eliana [E]): 16 TODOs — esta sessão é meta-trabalho de governança, não US
my-inbox: 4 unread (US-FIN-026/027/028) — sem ataque ainda
decisions-search: nada novo desde handoff parent 1800
```

## Operação rewrite (8 passos)

| # | Ação | Resultado |
|---|---|---|
| 1 | **Forward redact** PR #2435 (substitui valores por `[redacted Tier 0]` no estado canon) | 4 arquivos limpos no main |
| 2 | `pip install git-filter-repo` (não estava instalado) | OK 2.47.0 |
| 3 | Bare clone `https://github.com/wagnerra23/oimpresso.com.git` em `/tmp/oimpresso-rewrite.git` | OK (workaround filename-too-long em prototipo-ui/_BACKUP) |
| 4 | `git filter-repo --replace-text patterns.txt` — regex `R\$\s?\d[\d.,]*` → `R$ [redacted Tier 0]` | 5.033 commits processados em ~44s |
| 5 | `gh api PUT branches/main/protection` com `allow_force_pushes=true` | Branch protection habilitada |
| 6 | `git push --force origin main` | SHAs novos (4ebacd333/8eb70debd/6d2adcc10) |
| 7 | `gh api PUT branches/main/protection` com `allow_force_pushes=false` | Branch protection restaurada |
| 8 | Cleanup: 7 branches WR2 deletadas (remote + local) + Hostinger re-sync + gc agressivo local | Tudo sincronizado |

## Validação final

```
raw.githubusercontent.com/.../sessions/2026-06-08-wr2-backfill-recurring-2026.md:
  - "Volume financeiro: redacted Tier 0 — só Wagner/Eliana têm acesso"
  - cobrancas_valor_total_brl: [redacted Tier 0]
  - [valores redacted Tier 0]

git log origin/main -S 'R$ 743': 0 hits ✅
git log origin/main -S '37.111,26': 0 hits ✅
git log origin/main -S '445.335': 0 hits ✅
```

## Regra perene catalogada

Em `memory/proibicoes.md` §Memória/governança — nova entrada `⛔ NUNCA commitar valores BRL` cobrindo:
- O que: valores monetários R$/MRR/totais em memory/*, *.md canon, PR body, commit message
- Por que: time op (Felipe/Maiara/Luiz) tem acesso git mas não a valores via UI (role Operacional#1)
- Receita recovery (caso reincida): filter-repo + force push
- Sugestão defesa mecânica: hook PreToolUse `block-brl-values-in-memory.ps1`

## Custo da operação

| Item | Valor |
|---|---|
| Tempo recovery | ~30min |
| PRs adicionais | 2 (#2435 forward + #2439 docs) |
| Branch protection window | ~5min aberta |
| Force push em main | 1 (autorizado Wagner explícito) |
| Risco residual | mínimo — branches deletadas + gc agressivo |

## Aviso pra devs Felipe/Maiara/Luiz

Quem já tinha clone local antes de 2026-06-08 18:30 (BRT) precisa:

```bash
git fetch origin
git reset --hard origin/main
```

Histórico foi reescrito — SHAs antigos viraram outros. `git pull` normal falhará (divergent history).

## Pendente

- [ ] (P3) Implementar hook `block-brl-values-in-memory.ps1` PreToolUse pra prevenir reincidência mecanicamente
- [ ] Próxima sessão Eliana: atacar US-FIN-026/027/028 (inbox 5d unread) e/ou US-NFSE-013 (deploy produção)

## Refs

- Session log: [2026-06-08-wr2-backfill-recurring-2026](../sessions/2026-06-08-wr2-backfill-recurring-2026.md)
- Handoff parent: [2026-06-08 18:00 WR2 backfill recorrência 2026](2026-06-08-1800-wr2-backfill-recurring-2026.md)
- PR forward: [#2435](https://github.com/wagnerra23/oimpresso.com/pull/2435)
- PR docs final: [#2439](https://github.com/wagnerra23/oimpresso.com/pull/2439)
- Proibição perene: `memory/proibicoes.md` §Memória/governança
