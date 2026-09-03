---
date: "2026-09-02"
time: "19:20 UTC"
slug: "forja-onda4-lista-replica-pr-aberto"
tldr: "A lista de /forja/trabalho virou a réplica do forja-page.jsx (3 barras de filtro, fj-row densa, KPI que filtra, accent dark 0,70) — PR #6582 aberto, merge é [W]. A sonda pareada NÃO rodou: exige deploy, e o §11 linha 4 está 🟡, não ✅. Dois achados de método: o espelho local mede 1 filho na fj-frentebar porque o CliSeg retorna null sem o Segmented do DS (veredito invertido), e o EpicRoll nunca dispararia — epic_id é FK pra McpEpic."
prs: [6582]
us: [US-FORJA-006]
related_adrs: ["0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias"]
next_steps:
  - "Deploy e sonda pareada design-diff --probe nos dois lados (dark, mesma viewport) antes de marcar o §11 linha 4 como ✅"
  - "Conferir no CI que o contador do TrabalhoListaTest subiu de 12 para 16 casos — é o recibo, não a presença do arquivo"
  - "[W] decide as duas reconciliações: Non-Goal do pin (localStorage) e anti-hook do aria-pressed no Gantt"
---

# Forja Onda 4 — a lista do Trabalho é a do protótipo (PR aberto, merge é [W])

## Estado no fechamento

**MCP indisponível nesta sessão** — o hook de `brief-fetch` reportou *timeout do servidor* no SessionStart, e as tools `cycles-active`/`my-work`/`sessions-recent`/`whats-active` não estavam expostas. Operei pelo **fallback filesystem** que o [`how-trabalhar.md` §Fallback](../how-trabalhar.md) prevê: índice de handoffs, `PARIDADE §11`, `visual-comparison`, `git log origin/main`. Declaro porque a ausência de snapshot MCP é uma lacuna real deste handoff, não algo que eu pulei.

Verificado por git em vez de MCP:
- último da série Forja em `origin/main`: **Onda 2.1** (`a91ce0cd5c` #6563 + recibo #6565).
- **Onda 3 (Aprovações) não mergeada e sem PR aberto** — conferido em `gh pr list --state all` e `--state open`.
- durante a sessão, `origin/main` recebeu **#6569** (mudou o gerador de inconsistências) e **#6566**.

## O que fica no ar

**PR [#6582](https://github.com/wagnerra23/oimpresso.com/pull/6582)** — branch `claude/forja-onda4-lista-replica`. Substitui o **#6577**, que fechei: ele ficou `DIRTY` quando o #6569 mergeou.

A tela `/forja/trabalho` (modo lista) passa a usar o vocabulário do `forja-page.jsx`: 3 barras de filtro, `fj-row` densa, KPI que filtra (`<button>`, 17px), `--accent` dark 0,70. Backend ganhou `grupo`/`saude`/`papel` com allowlist e a separação `build()` (pool + KPIs) × `filtrar()` (recorte da lista).

## O que o próximo precisa saber para não repetir

1. **A sonda pareada NÃO rodou.** Ela exige o deploy, e o merge é ato [W]. O §11 linha 4 está **🟡**, não ✅ — e o `visual-comparison` diz isso com todas as letras. Quem retomar: deploy → `design-diff --probe` nos dois lados (dark, mesma viewport) → `--compare --check`. **Não** marcar ✅ antes.

2. **O espelho local do protótipo mente sobre uma barra.** O `.fj-frentebar` mede 1 filho porque o `CliSeg` retorna `null` sem o `Segmented` do DS, e o bundle do snapshot está truncado (44 de 55 componentes). O vivo tem 2. Comparar aquela barra contra o espelho local dá **veredito invertido**. Está registrado no `visual-comparison` da noite.

3. **`epic_id` não é hierarquia de task.** É FK pra `McpEpic` (`McpTask.php:230`). Implementei o `EpicRoll` do protótipo em cima disso e teria ficado mudo pra sempre; removi antes do merge. Se alguém reabrir sub-issues nesta tela, a hierarquia precisa de outra fonte.

4. **O `casos-gate` não alcança esta tela.** Escopo dele é `resources/js/Pages/**`; a Forja mora em `Modules/Forja/Resources/js/Pages/**`. Quem roda os UC é a lane `forja-pest.yml` (run-set explícito). O recibo de que os 4 UC novos rodaram é o **contador subir de 12 para 16**, não o arquivo existir.

5. **Force-push está barrado, e o caminho certo é branch nova.** O `block-destructive` barrou o force-push depois do rebase, e o `reset --hard` foi negado por permissão. Abri branch nova e fechei o PR antigo apontando pra ela — sem sobrescrever história de ninguém.

## Duas decisões que esperam [W] no merge

- **Non-Goal do pin**: o que entrou é `localStorage` do viewer (como o protótipo faz), não user-pref no banco. Se [W] ler o Non-Goal como abrangendo isso, é remover pin e estrela.
- **Anti-hook do `aria-pressed` no Gantt**: os três estão no mesmo segmentado, como o protótipo; o valor nunca fica `gantt` (navega na hora). Se [W] preferir o Gantt fora do segmentado, é ajuste pequeno.
