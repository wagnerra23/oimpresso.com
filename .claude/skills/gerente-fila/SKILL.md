---
name: gerente-fila
description: ATIVAR quando Wagner pedir "gerencie o merge", "gerente da fila", "cuida da fila de PRs", "placar dos playbooks", "qual o placar", "despacha as próximas threads", "continua a gerência", "/gerente-fila", OU ao abrir sessão que sucede um handoff `gestao-fila-merges-*`. Gerente da fila de merges + despacho de threads de playbook EM LOTES, com custo de tokens mínimo — uma varredura por rodada (`npm run gerente:fila`), ação em bloco por lote, nunca PR a PR. Tier B.
trust_level: L1
owner: wagner
tier: B
related_adrs: [0409, 0411, 0427, 0344]
---

# Gerente da fila (em lotes)

Mandato padrão do [W] (handoffs `gestao-fila-merges-*`): *"gerencie o merge de todos, decida"*.
**Ficam com o [W]:** valor/estoque fora do molde, migration destrutiva, cutover (ligar flag),
apagar Blade/legado, regravar baseline visual (ADR 0409/0411 — nunca regravar VRT pra check passar).

## A rodada (o único comando que lê a fila)

```bash
npm run -s gerente:fila            # lotes da fila (~30 linhas)
npm run -s gerente:fila -- --placar   # + placar dos playbooks e threads "próximo" por módulo
```

Exit **2 = NÃO MEDI** (gh/baseline) — nunca leia como fila vazia. Não abra `gh pr view` PR a PR:
o script já cruzou required (baseline ∪ rulesets), mergeable_state, arquivos e o vermelho do main.

| Lote | Ação em bloco |
|---|---|
| `MAIN` | o required falha também no main → consertar o main **primeiro** (1 PR), depois `gh pr update-branch` só nos que estavam vermelhos por ele |
| `MERGE` | `gh pr merge N --squash` em todos da lista (já vem 1 por módulo) |
| `CONFLITO regenerar` | merge do main + regenerar o derivado com o gerador dono (SUPERFICIE: `module-surface`) + push sem force |
| `CONFLITO real` / `VERMELHO` | ler **só** o log do required citado (`gh run view --log-failed`); conserto pequeno = push; fora do escopo = 1 comentário no PR |
| `W` | não mergear; acumular numa lista única pro [W] no fim |
| `ESPERA`, `MERGE·fila` | nada — próxima rodada |

## Economia de tokens (as regras que pagaram)

1. **Cadência ≥ 300 s** (`ScheduleWakeup`/`Monitor`). Vigia de 60 s esgotou a GraphQL duas vezes (13/10). O script só usa REST e cacheia arquivos por head SHA em `.claude/run/`.
2. **Um vigia, não N.** Nunca um processo por PR.
3. **Não dar `update-branch` em pilha F2/F3**: muda o head e invalida a lane. Se o de baixo entrou e o de cima segue mergeável, mergeie no head já provado. Head mudou só em SUPERFICIE/lista/`.tsx`? `git diff <testado> <head>` — o run PHP anterior vale.
4. **1 PR por módulo por rodada** (o lote já serializa): dois do mesmo módulo geram ciclo de conflito no SUPERFICIE.md.
5. Lanes advisory só rodam em opened/reopened; `workflow_dispatch` na mesma ref cancela o anterior (`cancel-in-progress`) — um dispatch por branch.
6. Antes de mergear lote com 2+ commits por PR, lembrar: advisory que não re-rodou **some** do `gh pr checks` (proibicoes §Sempre fazer, emenda 2026-09-08). Required não tem esse buraco.

## Despacho de threads (lote de sessões)

O `--placar` lista threads `próximo` agrupadas por módulo. **1 sessão por módulo**, threads do
módulo em sequência dentro dela (é o que evita o conflito do item 4). Antes de despachar:

- `próximo` com "sem recibo" pode já estar no main — ler o recibo/handoff antes (13/10: Crm 11–17 entregues, placar ainda `próximo` até o recibo descer do Cowork).
- `dup-detector --path <arquivos>` contra PRs abertos (§5 2026-09-30) e `ListAgents` por sessão com o número da thread no título (§5 2026-10-05).
- Threads de cutover (`C*`, "Liga …") e de apagar legado são do [W].

## Fechar a gerência

Handoff `memory/handoffs/YYYY-MM-DD-HHMM-gestao-fila-merges-<slug>.md` com: placar antes→depois,
PRs mergeados, pendências [W] (lista do lote `W`), e o que ficou em voo. Linha no topo do `08-handoff.md`.
