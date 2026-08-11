---
date: "2026-08-11"
time: "17:45 UTC"
slug: a-bagunca-que-era-decisao-e-a-nota-da-memoria
tldr: "[W] apontou pastas que 'não deveriam existir' em memory/requisitos. Quase todas eram decisão registrada — 4× a medição derrubou meu plano antes de eu apagar trabalho dele. O que sobrou de real: um cron verde há 18 dias sem entregar, 5 máquinas sem invocador, 6 refs de código apontando pro stub, e um alarme gritando há 21 dias sobre pendência inexistente. A grade deu 7,1 na memória e nomeou o custo: o §5 é 84% do proibicoes.md e entra em TODA sessão."
decided_by: [W]
prs: [5589, 5590, 5595, 5596, 5598, 5599, 5601]
us: []
next_steps:
  - "Ratificar a declaração do #5595 (flipar status: proposal + preencher as 44 classes que exigem julgamento) — é o único ato que fecha o incômodo original do [W]"
  - "Dar número canônico ao 2026-08-03-incorporar-boost-guidelines-skills.md (status: recusado, único A:1 restante) — precedente 0290-fidelity-lock-v0-recusado"
  - "Declarar o teto de contexto do §5: o lapide-recheck agora imprime o número toda corrida, mas teto_declarado segue null de propósito (ato [W], não de agente)"
  - "Decidir os 108 testes sem lane (44 em Modules/OficinaAuto/Tests + 64 em tests/Feature/Cliente) — as 2 lanes foram deletadas em 27/07 por estarem vermelhas e os testes ficaram"
---

# A bagunça que era decisão — e a primeira nota da memória

## O pedido

[W] abriu com `memory/modulos` + `memory/requisitos` e a frase: *"ainda tem módulos que não existem mais, isso é ruim. tem mais alguma coisa quebrada?"*. Terminou em *"pode arrumar?"* e *"coloque um adversário pra ver se a memória está nota 9"*.

## O que era decisão, não bagunça (4 reversões medidas)

Esta é a parte que a próxima sessão precisa saber antes de tocar em qualquer pasta:

| plano | o que a medição mostrou |
|---|---|
| usar `SCOPE.md` pra separar área legítima de órfã | **0 de 72** pastas têm — ele ainda mora em `Modules/<X>/`; poder discriminante zero |
| "7 pastas mortas, limpar" | **4 têm conteúdo vivo**: `Dashboard` = RUNBOOK da tela `/home`; `Copiloto` = plano do Jana Pro; `LaravelAI` = camada A da stack (ADR 0035) |
| consolidar `Copiloto`→`Jana`, `PontoWr2`→`Ponto`, `LaravelAI`→`Jana` | **já feito em 2026-06-15** (E1 · frente KL) e 07-01. São lápides-redirect deliberadas |
| mover as 2 "ADRs presas em `proposals/`" | ambas têm `promoted_to: 0345` — mover teria **duplicado a ADR 0345** |

**Padrão:** em todas, o que separou conserto de estrago foi abrir o arquivo antes de obedecer o alarme.

E os "31 arquivos soltos" na raiz de `requisitos/` também não são lixo: **22 já são lápide/redirect** (lote documentado em `_TRIAGEM-IDENTIDADE-2026-06.md`), 8 são docs de área com prefixo `_`, 1 já está `deprecated`. Publiquei o contrário no #5595 e corrigi com ERRATA antes do merge.

## O que estava quebrado de verdade

- **`mv-metabolismo` rodava verde e não entregava havia 18 dias.** Todos os steps `success`, `Auto-PR` = `skipped`: o PR só abria com batch, então nas noites sem batch o `vital-signs.json` regenerado era descartado com o runner. O `cron-watchdog` não pega — mede liveness+conclusion, ambos verdes. É o vão *"roda ≠ entrega"* que a §5 de 2026-07-29 deixou declarado. → **#5589**
- **5 máquinas sem invocador** (3 `.test.mjs` + 2 `--selftest` embutidos). → **#5590**
- **6 refs de código** apontando pro stub de 12 linhas em vez da verdade viva de 422; uma delas pra arquivo inexistente. → **#5596**
- **`adr-proposto-parado` gritava há 21 dias** sobre 2 docs já promovidos — a lista de marcadores era anterior ao campo `promoted_to`. A:3 → A:1. → **#5599**
- **108 testes sem lane** (44 OficinaAuto + 64 Cliente): as 2 lanes foram deletadas em 27/07 *porque estavam vermelhas* e os testes ficaram. **Não consertado — decisão [W].**

## A grade da memória: 7,1

Rodada `full-parcial` da dimensão `memoria-conhecimento` (23 agentes, 6,27M tokens, base fresca). Retrato em `memory/reguas/retratos.json`. → **#5598**

Placar: **0 acima-de-categoria** · 3 diferencial-de-integração · 1 empatada · 3 refutadas · 1 `REFUTADO_TB`. Anti-Goodhart: 2 canários plantados, **2 derrubados**, goodhart=0.

⚠️ **7,1 não é queda dos 7,5 de 18/07** — conjunto de fraquezas diferente; as 2 que reapareceram **subiram**. Comparar como Δ é o que a regra 12 do método proíbe.

**O achado estrutural:** 5 das 8 fraquezas já tinham máquina viva que a pesquisa não achou. Causa nomeada: o dossiê dos pesquisadores (`reguas-do-sistema.js:470`) não inclui `MAQUINAS-INVENTARIO.md` — falso-negativo por construção.

**A mais baixa (F5, 5,5) virou máquina** → **#5601**: o `lapide-recheck` agora mede o custo do §5 (336k chars = 84% do arquivo, 475 palavras/lápide, 1,55 lápide/dia, conformidade das 3 partes com emenda fora do denominador). Sem nota agregada (C9), sem presence-gate, `teto_declarado: null` de propósito.

## Estado MCP no momento do fechamento

**MCP indisponível nesta sessão.** O hook `SessionStart` registrou fallback explícito: *"[brief-fetch hook] FALLBACK ATIVADO — motivo: servidor MCP não respondeu no tempo (timeout)"*. Logo `cycles-active` / `my-work` / `sessions-recent` / `decisions-search` **não foram consultados** — o checklist MCP-first do ADR 0130 não pôde ser cumprido, e registro isso em vez de omitir.

Estado apurado por git (substituto declarado, não equivalente):

- `origin/main` em `0b058f8a5cb` no início da grade; 6 PRs desta sessão mergeados até o fechamento.
- `adr-proposto-parado`: **A:1 · B:0 · C:2** (era A:3).
- `lapide-recheck`: 104 lápides · 76 intactas · 16 sem âncora · 9 citação não resolvida · **3 `revisar`**.
- `selftest-registry-check`: 🟢 zero órfãos nas duas categorias (era 3+2).
- `reguas-ledger-check --check`: rc=0, nenhuma violação nova.

## Armadilhas que custaram tempo (pra próxima sessão não pagar de novo)

- `/tmp` do node (Windows) **≠** `/tmp` do bash (MSYS) — script escrito por um, lido pelo outro, não se acha.
- `if grep -q <arquivo-inexistente>` devolve "não achou" e vira **falso OK**: cheque existência antes de interpretar ausência.
- `git cat-file -e origin/main:<path>` sofre **MSYS mangling** no `:` — use `git ls-tree` com `--`.
- `node -e` misturando `require` e top-level `await` → `ERR_AMBIGUOUS_MODULE_SYNTAX`; use `--input-type=module` com `import`.
- Expressão `${{ }}` com `: ` dentro precisa de **aspas duplas** no YAML, senão vira mapping (o validador `js-yaml` pegou).

## Worktree deixado de pé

`D:/oimpresso.com/.claude/worktrees/reguas-base-20260811` (detached em `origin/main`) — base fresca da grade. Se for remover, seguir a receita das proibições (junction primeiro, alvo conferido, só então `git worktree remove`).
