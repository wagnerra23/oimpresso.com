---
id: requisitos-jana-runbook-components
slug: jana-runbook-components
title: "Jana — Runbook dos componentes compartilhados (Pages/Jana/components/)"
type: runbook
module: Jana
owner: W
status: ativo
date: "2026-08-07"
last_validated: "2026-08-10"
---

# RUNBOOK — componentes compartilhados da Jana

> **Tipo:** runbook de manutenção — **não** é receita de migração de tela
> **Cobre:** `resources/js/Pages/Jana/components/**`
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0271](../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) · SPEC `US-COPI-148`

## Por que este arquivo existe

O hook [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs) — o **único** enforcement de RUNBOOK desde a ADR 0271 onda 2 — exempta `Pages/<Mod>/_components/**` e qualquer subpasta `_*` ("helpers não são telas migráveis"). A Jana tem `_components/` e `_shared/` exemptos **e** uma pasta `components/` **sem** underscore, que não é exempta: o hook resolve o candidato pelo kebab do subdir e passa a exigir `RUNBOOK-components.md`.

Os arquivos de lá **não são telas** — são compartilhados entre as telas da Jana, sem rota, sem `store()`, sem charter próprio. _(Eram 3 até 2026-08-10; hoje são **2** — ver a lápide do `JanaCockpitV2` abaixo.)_ Por isso este RUNBOOK não segue as 5 fases do MWART (que descrevem migrar uma tela Blade→Inertia): ele documenta **como mexer neles sem quebrar quem os consome**.

> Medido antes de escrever, com bite-test sobre `decide()` do próprio hook e controle
> negativo: **sem** este arquivo os 3 bloqueiam e `Memoria.tsx` também; **com** ele os 3
> passam e `Memoria.tsx` **continua** bloqueando. Ou seja, ele destrava a pasta, não o
> módulo — que é exatamente o alcance pretendido.

## O que vive aqui, e quem consome

| Arquivo | Consumidores (medido 2026-08-10, `git grep` de import real) | Cuidado ao mexer |
|---|---|---|
| `JanaAreaHeader.tsx` | `Chat.tsx` · `Index.tsx` · `Memoria.tsx` | header de **todas** as telas Jana — muda 1, muda 3 |
| `FabJana.tsx` | `Index.tsx` · `Memoria.tsx` | botão flutuante que leva à Conversa |

> **Errata 2026-08-10 — `Cockpit.tsx` saiu da coluna do `JanaAreaHeader`.** A onda 4 da
> `US-COPI-148` apagou aquela Page em 2026-08-07 e esta tabela não acompanhou. São **3**
> consumidores, não 4.

### 🪦 `JanaCockpitV2.tsx` — REMOVIDO em 2026-08-10 (estava morto; o canon dizia o contrário)

Este arquivo (633 ln) foi deletado. **A seção anterior mandava não apagá-lo, e estava errada** —
fica registrada aqui a lápide, porque a afirmação falsa custou o arquivo ter sobrevivido à
onda 4 que o teria limpado.

**O que o canon afirmava** (aqui, no `RUNBOOK-cockpit.md`, no `SPEC.md` 2×, no `SUPERFICIE.md`,
em 2 session logs e 1 handoff): *"quem o consome é o `Sells/Index.tsx:55` (tab Insights de
vendas) — apagar quebra a aba Insights de `/sells`"*.

**O que foi medido em 2026-08-10:**

| Prova | Resultado |
|---|---|
| `git grep` de import real, repo inteiro | **0 imports** — as 3 "referências" eram **comentário** |
| `Sells/Index.tsx:53-55` | comentário histórico dizendo que a tab **SAIU** dali |
| `Sells/Index.tsx:486 · 1078 · 1092` | *"tab bar Dashboard \| Insights Jana removida"* |
| `SellsInsightsView.tsx` (o componente da tab) | **não existe** — deletado |
| `Inertia::render('Jana/components/...')` em PHP | **nenhum** |
| `tsc --noEmit` antes × depois da deleção | **372 = 372** — zero erro novo |
| `npm run build:inertia` | passa · **0** chunks `JanaCockpitV2*` (1 chunk do `_components/JanaCockpit` vivo) |

A raiz é a classe **LC-08** já lapidada em [§5 2026-08-08](../../proibicoes.md): *reportei "16 telas
usando X" com `git grep -l`, que casou menções em **COMENTÁRIO***. Aqui o mesmo erro nasceu uma
vez e foi **copiado por 7 documentos**, cada um citando o anterior — nenhum reabriu o `Sells/Index.tsx`
pra ver que a linha 55 é comentário.

**O que continua verdadeiro e não muda:** o irmão `_components/JanaCockpit.tsx` é a bifurcação
PT-04 (`US-COPI-146`) que alimenta o Painel, e a regra **R7** do `ui:lint` segue proibindo bundle
paralelo dentro da Jana. ⚠️ O [`UiLintR7BundleParaleloTest.php`](../../../tests/Feature/Architecture/UiLintR7BundleParaleloTest.php)
**não citava o V2** — ele usa fixtures sintéticas sobre `Jana/Index.tsx`; a frase *"citando a tela
nominalmente"* desta seção se referia ao `Index`, não ao V2.

**Resíduo declarado, NÃO resolvido:** `resources/css/sells-cowork-insights.css` (**776 ln**,
importado globalmente em `inertia.css`) ficou com **zero** consumidor JS — o V2 era o único (83
ocorrências). Não foi removido junto porque os `it()` **vivos** de
[`SellsTabsViewModeTest.php`](../../../tests/Feature/Sells/SellsTabsViewModeTest.php) guardam sua
existência e o escopo das classes, e o prefixo `.vd-*` é partilhado com o bundle do Financeiro
(138 classes, com teste de contagem). Retirá-lo exige aposentar o guarda — ato de governança,
decisão [W], não faxina.

## Antes de editar

1. **Contar consumidores** — `rg --hidden -g '!.git/**' -n "components/<Arquivo>" resources/`. Header e FAB são multi-tela; a conta define o raio de smoke.
2. **Ler o charter da tela afetada**, não deste arquivo — componente compartilhado não tem charter próprio.
3. **Se mexer em navegação** (href, rota, label de aba), lembrar que os ghosts vêm do **`DataController` (PHP)**, não do React: o `JanaSubNav` só lê `shell.menu`. Mudar aba é mudar PHP.

## Depois de editar

| Passo | Prova |
|---|---|
| Build | **`npm run build:inertia`** passa |
| Lint DS | `npm run ui:lint` (R1 cor crua · R7 bundle paralelo) |
| Smoke real | abrir as **3** telas com header (`/ia` · `/ia/conversa` · `/ia/memoria`) e conferir nas 3 (R1 do PROTOCOLO-WAGNER) |

> **Errata 2026-08-10 — duas correções de instrumento nesta tabela:**
> **(a)** era `npm run build`, que aponta pro `vite.config.js` do **Tailwind** — ele transforma
> **1 módulo** e emite só `tailwind.css`, sem tocar num `.tsx` sequer. Mudança em componente React
> se prova com **`build:inertia`** (`vite.inertia.config.mjs`), o único que compila as Pages.
> **(b)** era *"as 4 telas"*: `Pro.tsx` cita o `JanaAreaHeader` **em comentário** (`:43`) e não o
> importa — mesma armadilha comentário-≠-uso da lápide acima, no mesmo arquivo.

## Armadilhas catalogadas

- **`JanaSubNav` pega a PRIMEIRA entry com `group === 'ia'`.** Existem **duas** — Jana (`order(90)`) e KB (`order(91)`). Hoje a Jana vence, e o KB não declara `ghosts`: duas razões independentes para não estar quebrado. Dar ghosts ao KB, ou reordenar, acorda a mesma falha que derrubou o Financeiro em produção (WR2, 2026-06-16), consertada pelo `pickFinanceiroEntry`.
- **O tipo `JanaAreaTab` acumula membro morto.** Cada ghost removido do `DataController` deixa o membro no union, e o TypeScript aceita `active="kb"` produzindo uma aba que não renderiza. Ao remover ghost, remover o membro **no mesmo PR**.
