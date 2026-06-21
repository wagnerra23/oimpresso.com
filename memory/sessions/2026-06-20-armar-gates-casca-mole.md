---
date: "2026-06-20"
topic: "Armar a casca-mole: 2 PRs (memory-health Check K + adr-index double-supersede), item 1 já estava armado, itens 2/3/5 reservados com bloqueador concreto."
authors: [C, W]
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0258-processo-adr-estado-arte-indice-gerado-supersede-atomico", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0294-metodo-dual-track-shapeup-catraca"]
prs: [3084, 3085]
us: []
---

# Armar a casca-mole — backlog de armamento do adversário da convergência

Continuação do diagnóstico [`2026-06-20-adversario-convergencia-sistema.md`](2026-06-20-adversario-convergencia-sistema.md)
(sistema "super-construído e sub-armado"). Objetivo: fechar buracos da camada 2 (casca-mole)
**armando o que já existe**, sem travar o CI de ninguém.

## O que landou (2 PRs, gates de governança verdes)

- **[#3084](https://github.com/wagnerra23/oimpresso.com/pull/3084) — `memory-health` Check K** (item 4): session log >30d com marcador de
  decisão (`## Decisão`/`US-`/`rollout`/`### Passo`) **sem** link pra ADR aceito nem BRIEFING → `WARN`.
  É o detector dos "planos perdidos". Advisory (nunca bloqueia — `warns` não dão exit 1). Repo real: 4 logs.
  Não duplica o Check J (planos vivos, ADR 0294); alvo aqui é o session log histórico. +meta-teste físico (ADR 0258).
- **[#3085](https://github.com/wagnerra23/oimpresso.com/pull/3085) — `adr-index` double-supersede** (item 6): `>1` ADR herdando o mesmo número →
  `supWarn` (gate duro no `--check`). **0 conflitos hoje** (301 ADRs, `--check` verde) → arma o futuro sem travar o CI atual. +meta-teste.

Ambos: 1 PR = 1 intent, base `origin/main` atual, `meta-teste vitest` + `Governance Gate` verdes no CI.

## Decisões da sessão

- **Item 1 (hook R10 `block-pr-without-approval`) NÃO refeito** — já estava armado em `origin/main`
  (PRs #3058 registrou + #3065 endureceu: cobre `Bash`/`PowerShell`/`UserPromptSubmit`). O `settings.json`
  "sem o hook" que o adversário viu era a cópia stale de uma branch atrás de main. R10 morde hoje.
- **Itens de promoção-a-required reservados pro Wagner** (rule d — convenção de governança via ADR + ok):
  - **Item 2** anchor-lint→required: **15 anchors mortos** (full-tree). Bloqueador canônico = ADR 0275 §5 **A10** (`anchor_coverage` = 100% estrito + 14d advisory FP<5% ANTES do flip) — não "trava o CI" genérico: existe caminho seguro **diff-only** (lint só os SPECs tocados). Backfill SA-A4/A5 destrava.
  - **Item 3** SDD scorecard→required: **2 métricas armadas** (próxima = `anchor_coverage`, faltam 2 medições válidas). ⚠️ **Correção pós-adversário 2026-06-20:** a regra de armamento é ADR 0275 **§3 (3 medições consecutivas POR métrica)**, NÃO "§5 ≥3 métricas armadas" — essa regra **não existe** no ADR (repeti do enunciado por engano). §5 é o calendário de gates **individuais** (R1/A10/…); a composta v2 só com 10/10 armadas. Não se "promove o scorecard" — promove-se cada gate pelo seu critério §5.
  - **Item 5** `strict=true` branch protection: confirmado `strict:false` (18 required). Muda merge global → precisa ADR + ok (risco de thrash de fila com sessões paralelas).
- **Não duplicar item 7** (plans-index generator) — é o workstream ADR 0294 em voo (#3082).

## Lição de método (rule c, verificada)

Antes de adicionar regra a gate JÁ required (`supWarn` do `adr-index --check`), **contei as violações
atuais primeiro** (0 double-supersede) — só então armei. Adicionar condição de fail a gate required
sem checar o estado atual = travar o CI de todos. Mesmo princípio dos itens 2/3 (por isso ficaram reservados).

## Higiene

Trabalhei numa worktree limpa off `main` (`.claude/worktrees/arm-gates`) porque a `frosty-greider-83ab2f`
(cwd da sessão) está **órfã/vazia** (não está no `git worktree list`) — bate com a nota de cleanup pendente.
Nada commitado nela.
