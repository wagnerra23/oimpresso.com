---
id: requisitos-jana-runbook-components
slug: jana-runbook-components
title: "Jana — Runbook dos componentes compartilhados (Pages/Jana/components/)"
type: runbook
module: Jana
owner: W
status: ativo
date: "2026-08-07"
last_validated: "2026-08-07"
---

# RUNBOOK — componentes compartilhados da Jana

> **Tipo:** runbook de manutenção — **não** é receita de migração de tela
> **Cobre:** `resources/js/Pages/Jana/components/**`
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0271](../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) · SPEC `US-COPI-148`

## Por que este arquivo existe

O hook [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs) — o **único** enforcement de RUNBOOK desde a ADR 0271 onda 2 — exempta `Pages/<Mod>/_components/**` e qualquer subpasta `_*` ("helpers não são telas migráveis"). A Jana tem `_components/` e `_shared/` exemptos **e** uma pasta `components/` **sem** underscore, que não é exempta: o hook resolve o candidato pelo kebab do subdir e passa a exigir `RUNBOOK-components.md`.

Os três arquivos de lá **não são telas** — são compartilhados entre as telas da Jana, sem rota, sem `store()`, sem charter próprio. Por isso este RUNBOOK não segue as 5 fases do MWART (que descrevem migrar uma tela Blade→Inertia): ele documenta **como mexer neles sem quebrar quem os consome**.

> Medido antes de escrever, com bite-test sobre `decide()` do próprio hook e controle
> negativo: **sem** este arquivo os 3 bloqueiam e `Memoria.tsx` também; **com** ele os 3
> passam e `Memoria.tsx` **continua** bloqueando. Ou seja, ele destrava a pasta, não o
> módulo — que é exatamente o alcance pretendido.

## O que vive aqui, e quem consome

| Arquivo | Consumidores | Cuidado ao mexer |
|---|---|---|
| `JanaAreaHeader.tsx` | `Chat.tsx` · `Index.tsx` · `Memoria.tsx` · `Cockpit.tsx` | header de **todas** as telas Jana — muda 1, muda 4 |
| `FabJana.tsx` | `Index.tsx` · `Memoria.tsx` | botão flutuante que leva à Conversa |
| `JanaCockpitV2.tsx` | **`Sells/Index.tsx`** (tab Insights) | ⛔ não é da Jana na prática — ver abaixo |

### ⛔ `JanaCockpitV2.tsx` — não apague, e não use como base do Painel

Mora na Jana por herança, mas quem o consome é o **`Sells/Index.tsx:55`** (tab Insights de vendas). Ele carrega o bundle CSS paralelo `.sells-cowork .vd-insights-*` e é a **tela-dona legítima** desse bundle.

O irmão `_components/JanaCockpit.tsx` é a bifurcação PT-04 (US-COPI-146) criada **justamente para tirar o bundle paralelo de dentro da Jana** — é ele que alimenta o Painel.

Duas consequências que já custaram retrabalho (o pedido original da fusão trazia os dois invertidos):

- Apagar o `JanaCockpitV2` **quebra a aba Insights de `/sells`**.
- Usá-lo como base do Painel **reintroduz** o bundle paralelo que a regra **R7** do `ui:lint` proíbe — há teste de arquitetura dedicado, [`UiLintR7BundleParaleloTest.php`](../../../tests/Feature/Architecture/UiLintR7BundleParaleloTest.php), citando a tela nominalmente.

## Antes de editar

1. **Contar consumidores** — `rg --hidden -g '!.git/**' -n "components/<Arquivo>" resources/`. Header e FAB são multi-tela; a conta define o raio de smoke.
2. **Ler o charter da tela afetada**, não deste arquivo — componente compartilhado não tem charter próprio.
3. **Se mexer em navegação** (href, rota, label de aba), lembrar que os ghosts vêm do **`DataController` (PHP)**, não do React: o `JanaSubNav` só lê `shell.menu`. Mudar aba é mudar PHP.

## Depois de editar

| Passo | Prova |
|---|---|
| Build | `npm run build` passa |
| Lint DS | `npm run ui:lint` (R1 cor crua · R7 bundle paralelo) |
| Smoke real | abrir as **4** telas Jana e conferir o header nas 4 (R1 do PROTOCOLO-WAGNER) |

## Armadilhas catalogadas

- **`JanaSubNav` pega a PRIMEIRA entry com `group === 'ia'`.** Existem **duas** — Jana (`order(90)`) e KB (`order(91)`). Hoje a Jana vence, e o KB não declara `ghosts`: duas razões independentes para não estar quebrado. Dar ghosts ao KB, ou reordenar, acorda a mesma falha que derrubou o Financeiro em produção (WR2, 2026-06-16), consertada pelo `pickFinanceiroEntry`.
- **O tipo `JanaAreaTab` acumula membro morto.** Cada ghost removido do `DataController` deixa o membro no union, e o TypeScript aceita `active="kb"` produzindo uma aba que não renderiza. Ao remover ghost, remover o membro **no mesmo PR**.
