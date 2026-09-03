---
date: "2026-09-02"
time: "19:15 UTC"
slug: forja-onda3-aprovacoes-view-hoje
tldr: "Onda 3 da PARIDADE §11 entregue no PR #6571 — /forja/aprovacoes virou a view `hoje` do protótipo, com backend novo pra faixa ao-vivo e placar. Falta o merge [W] e, só depois dele, o compare por sonda que fecha a linha 3."
prs: [6571]
us: [US-FORJA-006]
next_steps:
  - "[W] revisar e mergear o #6571 (merge de .tsx é humano — ADR 0283)"
  - "Pós-deploy: design-diff --probe nos dois lados, mesma viewport, dark nos dois → --compare --check, e apensar a tabela D2/D4/D6/D8 no forja-cockpit-visual-comparison.md"
  - "Pós-deploy: screenshot de prod (R1) antes de qualquer declaração de pronto"
  - "Só então marcar ✅ a linha 3 da tabela de ondas do §11"
  - "Decisão [W] pendente: criar vínculo papel→usuário (campo novo) pra destravar as 2 colunas do placar que hoje mostram '—'"
related_adrs:
  - 0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias
  - 0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias
  - 0368-funil-admissao-feature-pesquisa-propoe-w-admite
  - 0283-handoff-loop-zero-paste
---

# Handoff — Forja Onda 3: Aprovações vira a view `hoje`

Continuação direta do [handoff das 13:50](2026-09-02-1350-forja-onda2-replica-primeiro-no-ar.md)
(Onda 2 + 2.1 no ar). Narrativa completa no
[session log](../sessions/2026-09-02-forja-onda3-aprovacoes-view-hoje.md).

## O que está feito

`/forja/aprovacoes` deixou de ser 2 KpiCards + empty-state e virou o markup da view `hoje` do
protótipo: herói, faixa "Ao vivo no MCP", mesa (fila + artefato + decisões) e placar por papel.
Classes do bundle da Onda 1 — **zero CSS novo**. Backend novo (`aoVivo`, `placar`,
`handoffsComProblema`) fechou os 2 itens que o `casos.md` guardava desde 2026-08-08 como
*"ainda sem backend"*.

## O que o próximo precisa saber antes de tocar nisso

1. **A âncora do charter estava errada e foi corrigida.** A view mora em `forja-aprova.jsx`, não
   em `forja-page.jsx` (que só a monta). Se você for comparar ou re-copiar, é o `forja-aprova`.
2. **A Page não é o `Cockpit.tsx`.** É `Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx`.
   O `Cockpit` não tem aba `aprovacoes`.
3. **`Modules/**` NÃO é typechecado** pelo `tsc -p tsconfig.json` (o `include` só cobre
   `resources/js/**`). Pra typechecar de verdade, um config temporário que estenda o real e inclua
   o arquivo — foi assim que apareceu um `TS2532` que o comando padrão não veria.
4. **Duas colunas do placar mostram "—" de propósito.** Sessões-hoje e custo/quota são por usuário
   e não há vínculo papel→usuário no schema. Não "conserte" preenchendo: é decisão [W].
5. **A baseline visual na branch é válida pro HEAD atual** — provado pela 2ª run, que respondeu
   "Baselines já em dia — nada a commitar". Não precisa re-despachar.

## Estado no fechamento (fallback filesystem — MCP indisponível)

⚠️ **As tools MCP não responderam nesta sessão** (o hook do `brief-fetch` registrou timeout do
servidor no SessionStart, e `ToolSearch` não encontra as tools do oimpresso). O protocolo de
fechamento pede o snapshot de `cycles-active` + `my-work` + `sessions-recent` + `decisions-search`;
**nenhum desses pôde ser consultado**. Usei o fallback de filesystem que a
[how-trabalhar.md §Fallback](../how-trabalhar.md) autoriza — e registro a ausência em vez de
omitir, porque handoff sem o snapshot e sem dizer por quê vira promessa, não prova.

O que deu pra medir, direto do repo:

| | |
|---|---|
| branch | `claude/forja-onda3-hoje` (4 commits) |
| PR | [#6571](https://github.com/wagnerra23/oimpresso.com/pull/6571) — aberto, **CI em fila** (118 checks enfileirados, 0 falha, 0 concluído até 19:10 UTC) |
| baseline visual | cherry-pickada; PR automático #6574 fechado |
| handoffs anteriores | `2026-09-02-1350-forja-onda2-replica-primeiro-no-ar` · `2026-09-02-0804-fiscal-onda0-e-consertos-de-gates` |
| gates locais | foundation · conformance · css-size · stylelint · layout · casos · deadlink · ds-guard --report · tsc · eslint-baseline — **todos exit 0** |

## O que NÃO foi provado

A comparação prod×protótipo por sonda **não rodou** e não podia: a produção não tem este código.
A linha 3 do §11 ficou **em andamento**, não ✅. A conferência de estrutura que fiz foi **no
protótipo** — prova que a cópia saiu fiel, não que os dois lados batem.

O CI também não terminou dentro da sessão: 118 checks ficaram na fila do repositório. **0 falha
até aqui não é "verde"** — é "ainda não rodou". Quem retomar confere com
`gh pr checks 6571` antes de propor merge.
