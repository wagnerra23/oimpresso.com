---
date: "2026-09-03"
time: "11:20 BRT"
slug: "forja-onda11-revogacao-parcial"
tldr: "Ondas 3-10 não existiam, então revoguei só onde o receptor estava no ar: 7 das 8 telas de /project-mgmt, 32 rotas → 5. Roadmap/Index fica pela ADR 0367 D7 e os 3 _components pela Onda 3, que é o que destrava o resto. PR #6617, merge humano."
decided_by: ["W"]
prs: [6617]
related_adrs: ["0367-cockpit-unico-forja-project-mgmt-morre", "0024-instalacao-1-clique-modulos", "0283-handoff-loop-zero-paste"]
next_steps:
  - "Onda 3 (Aprovações vira a landing) — destrava os 3 _components de uma vez"
  - "smoke pós-deploy do Infra Contract: as 7 revogadas viram 301, roadmap e /forja/* seguem 302"
  - "esperar conflito de merge em SCOPE.md e PARIDADE com as sessões de Forja em voo"
---

# Forja Onda 11 — revogação parcial, e o que a próxima sessão precisa saber

**PR:** [#6617](https://github.com/wagnerra23/oimpresso.com/pull/6617) (aberto, **merge é humano** — R10 / ADR 0283)
**Branch:** `claude/forja-onda-11-revogacao-9e15b8` · 6 commits por família (telas · controllers · rotas · testes · docs · integridade)

## Estado no momento do fechamento

⚠️ **Sem snapshot MCP.** O `brief-fetch` do SessionStart caiu em fallback ("servidor MCP não
respondeu no tempo") e as tools MCP não responderam nesta sessão. O protocolo de fechamento
([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)) pede `cycles-active` + `my-work` +
`sessions-recent` + `decisions-search`. **Não consegui rodá-los, e não vou fabricar o snapshot** —
o que segue é medido em git/gh, que responderam.

- `origin/main` = `1d5ca20c88`; a branch saiu dele com 0/0 no rebase.
- Ondas da Forja mergeadas: **0, 1, 2, 2.1** + recibo. **3-10 não existem** (0 commit, 0 PR).
- **Outras sessões de Forja ativas AGORA:** `claude/forja-onda9-changelog-r2` e
  `claude/forja-1280-prod-medida` (vistas nas runs de CI). Não rodei `whats-active` no início —
  deveria ter. Elas não colidem no código que toquei, mas `SCOPE.md` e
  `PARIDADE-area-forja-diagnostico-e-ondas.md` são superfície comum: **espere conflito de merge lá**.

## O que ficou, e o que destrava cada coisa

| ficou | por quê | destrava com |
|---|---|---|
| `Forja/Roadmap/Index.tsx` + rota `project-mgmt.roadmap.index` | ADR 0367 **D7** condiciona a saída a *"o Gantt provar que substitui (filtro por cycle efetivo + volume domado)"* | **Onda 6** (Trabalho · gantt) |
| `_components/Forja{Backlog,Quadro,Triage}` | `Cockpit.tsx:19-21` importa os três; `ForjaTriage` serve `/forja` — a **landing** e o alvo do botão "Novo issue" (`ForjaHub.tsx:136`) | **Onda 3** (Aprovações vira a landing) |
| 4 rotas `project-mgmt.install.*` | **ADR 0024**: sem elas o botão Install em `/manage-modules` fica sem ação. Não são tela | — (ficam) |

## Armadilhas que a próxima sessão herda

1. **Não apague os 3 `_components` antes da Onda 3.** `/forja` renderiza `ForjaTriage`; sem ele a
   landing do módulo cai. A Onda 3 é o pré-requisito, não uma preferência de ordem.
2. **A navegação tem TRÊS superfícies.** `menus/topnav.php` (a viva) · `DataController::modifyAdminMenu()`
   (morta atrás de um `return` incondicional na L116) · `ForjaHub.FORJA_TABS` (o protótipo, Onda 2).
   Mexer numa e esquecer as outras é o erro fácil aqui.
3. **`DataController` inteiro é código morto** depois da L116 — inclusive o dropdown que aponta
   `/forja` + `/team-mcp`. Se alguém tirar aquele `return`, a sidebar volta a renderizar. Fundir os
   dois dropdowns é **decisão [W]**, e o próprio arquivo diz isso desde 2026-07-31.
4. **Os baselines de CSS não foram commitados de propósito.** `.conformance-baseline.json` e
   `.fontramp-baseline.json` acumulam drift de arquivos que esta onda não tocou (`fin-output`,
   `sells-cowork*`, `cowork-arquivos`, `manufacturing`, `venda-v3`). Quem for regenerá-los, que seja
   num PR que **assuma** essa dívida — não de carona.

## Verificação pendente (não feita, e não pode ser fingida)

O **smoke pós-deploy** do Infra Contract. O "antes" está medido no PR (as 8 rotas dão hoje
`302 → /login`); o "depois" só existe depois do merge + deploy. O flip esperado é: as 7 revogadas
viram **301** pro receptor, e `roadmap`/`forja/*` seguem **302**. Enquanto isso não rodar, **ninguém
deve declarar a onda "no ar"** (R1).

## Próximo passo recomendado

**Onda 3** (Aprovações vira a landing e absorve a Triagem). Ela destrava os 3 `_components` de uma
vez e é o único caminho pro §11 fechar de fato — o resto da revogação está preso nela.
