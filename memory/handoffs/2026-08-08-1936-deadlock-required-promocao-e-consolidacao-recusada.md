---
date: "2026-08-08"
time: "1936 BRT"
slug: "deadlock-required-promocao-e-consolidacao-recusada"
tldr: "O CI não estava saturado: promover 3 jobs a required no mesmo commit que removeu o `paths:` (ADR 0370, 05/08) deixou 4 PRs esperando checks que nunca iam nascer — 2 dias de deadlock, destravados com `gh pr update-branch`. A consolidação de 65 jobs advisory que eu havia recomendado foi AVALIADA por 11 agentes e DESCARTADA por medição (ganho real −17%, não −60%; 2 bloqueadores derrubariam o required Governance Gate)."
decided_by: [W]
cycle: null
prs: []
us: []
next_steps:
  - "Conferir se os 4 PRs destravados (5077, 5068, 5255, 5119) fecharam verdes — o CI leva ~48min a partir de 2026-08-08 19:30 BRT."
  - "4 PRs em conflito precisam de resolução de conteúdo ([W]): 5397, 5304, 5071, 5069."
  - "2 PRs com required realmente vermelho: 5407 (`visual-regression`) e 5382 (`PHP / Pest (Ponto · MySQL)` — é a US-PONTO-014 da sessão irmã de 07/08)."
  - "PR 5417 está mergeável (só `dup-detector` advisory vermelho) — decisão [W]."
  - "Se houver 2ª ocorrência de required-promovido-deixando-PR-órfão, o par candidato de gate já está registrado (two-strikes ADR 0344) — não armar agora, exige FP medido."
related_adrs: [0370-module-surface-catalog-graph-required-emenda-0314, 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314, 0298-teto-de-governanca-anti-proliferacao-gates, 0344-two-strikes-cobre-processo]
---

# Handoff 2026-08-08 19:36 — o deadlock que parecia fila

## O que o [W] pediu

Nove palavras: *"os testes do github estao trancando por 2 dias ja. tem solução?"*

## O que era (causa provada)

**Não era fila.** O CI leva **48min de mediana** por PR (medido em 9 PRs / 977 check-runs), a fila
drenou sozinha de 378 → 150 durante a sessão, e o teto de concorrência **nunca saturou** (14 de 20
jobs, depois 10 de 20).

A causa é o commit **`9199a82a12f`** ([#5318](https://github.com/wagnerra23/oimpresso.com/pull/5318),
2026-08-05, [ADR 0370](../decisions/0370-module-surface-catalog-graph-required-emenda-0314.md)),
que fez **duas coisas no mesmo commit**: removeu o `paths:` de `module-surface.yml` e
`catalog-graph.yml` **e** promoveu 3 jobs deles a required.

PR aberto antes disso teve os check-runs computados quando ainda havia filtro de path → os jobs
não nasceram naquele SHA → agora são exigidos → **`Expected — waiting for status` permanente**.

Assinatura do sintoma, que engana: **PR `BLOCKED` com 0 falhas e 0 pendentes**.

## O que foi feito

| ação | resultado medido |
|---|---|
| `gh pr update-branch` em **5077, 5068, 5255, 5119** | 3 required órfãos → **0 falhas**, CI processando (93–111 checks) |
| cancelamento de 22 runs órfãos (branch sem PR) | fila 378 → 370 — **quase nada**, e isso confirmou que 93% da fila era trabalho legítimo |
| `required-always-run.mjs` | **verde** (41/41, 0 filtrados) — está certo, mede o main |

**Nenhum merge.** Nenhum arquivo de código alterado. As únicas escritas no repo são os docs deste
handoff (session log, §5, ledger, runbook).

## Estado dos PRs abertos ao fechar

| causa | PRs |
|---|---|
| destravados nesta sessão (CI rodando) | 5077 · 5068 · 5255 · 5119 |
| **conflito de merge** (`dirty`) — decisão [W] | 5397 · 5304 · 5071 · 5069 |
| **required vermelho de verdade** | 5407 (`visual-regression`) · 5382 (`Pest Ponto · MySQL`) |
| mergeável (só advisory vermelho) | 5417 |
| CI normal | 5420 · 5413 · 5404 |

## A consolidação: autorizada, executada como análise, recusada por medição

O [W] autorizou consolidar os jobs advisory em steps. Rodei **11 agentes** (6 extratores + 1
arquiteto + 4 céticos, 3,1M tokens, 0 erros). **O resultado recusou o próprio trabalho:**

- **Ganho real −17%** (148 → ~123 jobs/PR), não os −60% que eu havia prometido: `services:` e
  `strategy.matrix` são **job-level** e não existem como step — os 9 Pest e o `modules-pest` são
  intocáveis por construção.
- **2 bloqueadores duros**: `gates-advisory.yml` sem entrada no `gates-registry.json` reprova o
  **Check G**; sem `terminal`+`anchor`+`promote_by` reprova o **Check M** ([ADR 0298](../decisions/0298-teto-de-governanca-anti-proliferacao-gates.md)).
  Os dois derrubam o required **`Governance Gate`** → o PR travaria a si próprio.
- **41 required, não 40**: o `Governance Gate` só existe em **ruleset**, não na proteção clássica.

Registrado como lápide em [`proibicoes.md` §5](../proibicoes.md). O material (76 receitas + 25
achados) ficou no scratchpad da sessão — se reabrir, os bloqueadores já estão mapeados.

## O gap estrutural que ficou (sem máquina, de propósito)

`required-always-run.mjs` responde *"todo required nasce em PR novo?"* — e dava verde durante todo
o incidente, corretamente. A pergunta órfã é *"os PRs **já abertos** conseguem satisfazer o check
recém-promovido?"*.

**Antídoto** (agora no [RUNBOOK-branch-protection §Promoção de check](../requisitos/Infra/RUNBOOK-branch-protection.md)):
rodar `gh pr update-branch` nos PRs abertos **no mesmo PR da promoção**. Não virou gate —
[ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) two-strikes: 1ª ocorrência conserta,
não codifica; e gate novo exige FP medido antes.

## Meus erros (LC-08 → 59)

Três instâncias de **medir a fonte errada e chamar de verificado**: **(a)** o diagnóstico inteiro
(fila medida, causa inferida, recomendação grande em cima disso); **(b)** `ls || echo` negando um
CODEOWNERS listado uma linha acima — reincidência literal da lápide §5 de 2026-07-17;
**(c)** parser lendo `pull_request:` de valor vazio como "AUSENTE". Detalhe no
[session log](../sessions/2026-08-08-deadlock-required-promocao-0370.md) §7.

## Estado MCP no momento do fechamento

Snapshot do `brief-fetch` carregado no SessionStart desta sessão (não houve `tasks-*` nova; o
trabalho foi diagnóstico + docs, sem US):

- **Cycle ativo:** — (o brief do dia retornou cycle vazio)
- **HITL pending [W]:** 3 (`agent-corpus-counterfactual`, refinar runbook on-prem)
- **Brain B hoje:** 0% (0/50)
- **Flags:** 🟠 672 US não atribuídas (519 sem dono) · 🟡 SDD composta 55,0 (Δ+0,2) · 🟢 migration aging, PRs aguardando review, visual-regression: nada
- **Brief:** #478, gerado há ~2h no momento da abertura

Sessão irmã do dia anterior (mesma leva de promoções de 05/08, sintoma diferente):
[2026-08-07 18:40 — lanes Pest required](2026-08-07-1840-lanes-required-vermelhas-e-quarentena.md).
