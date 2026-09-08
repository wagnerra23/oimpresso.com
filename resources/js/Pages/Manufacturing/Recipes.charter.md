---
page: /manufacturing/recipe
component: resources/js/Pages/Manufacturing/Recipes.tsx
owner: wagner
status: draft
parent_module: Manufacturing
related_prototype: prototipo-ui/cowork/manufacturing-page.jsx
related_us: [US-MANU-001]
runbook: memory/requisitos/Manufacturing/RUNBOOK-recipes.md
casos: resources/js/Pages/Manufacturing/Recipes.casos.md
alcance:
  rota: /manufacturing/recipe
  rota_nome: recipe.index                 # Route::resource('/recipe', RecipeController) — Routes/web.php
  permission: manufacturing.access_recipe # RecipeController@index + DataController::user_permissions
  menu_hook: Modules/Manufacturing/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: manufacturing_module            # superadmin_package
tier: B
charter_version: 1
smoke: "2026-09-03 — render prod OK (Chrome MCP, sessão WR2 Sistemas): /manufacturing/recipe (tela nova, KPIs + 1 receita listada) e /manufacturing/recipe?legacy=1 (rollback Blade) renderizam a tela certa, 0 erro no console. Regressão adjacente OK: /manufacturing/production e /manufacturing/settings (Blade legacy) inalterados. curl -sv sem cookie: 302→/login nas 4 rotas (RUNBOOK-recipes.md §5)."
---

# Page Charter — Manufacturing/Recipes (DRAFT · PT-01 Lista)

> Porte Inertia da consulta de receitas a partir do handoff **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"**
> (2026-09-01), que é o documento **normativo** desta tela. A fonte visual está no espelho
> (`prototipo-ui/cowork/manufacturing-page.jsx`) e é **idêntica** ao ZIP — conferido arquivo a
> arquivo, 0 linhas de diferença nos 6 `.jsx` + o `.css`.
>
> **A rota é decisão [W] 2026-09-02**, textual: *"coloque a tela de Fabricação em produção no
> endereço https://oimpresso.com/manufacturing/recipe"*. O handoff §15.2 propunha `/v2/recipe`;
> o endereço é do dono ([ADR 0382](../../../../memory/decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)).
> A proibição do §15.2 — *"não remover nenhuma rota Blade legacy"* — foi cumprida: `Routes/web.php`
> não perdeu uma linha, e `?legacy=1` devolve a tela antiga no mesmo endereço.

## Mission

Responder, com o preço de insumo de **hoje**, quanto custa produzir cada item fabricado — e
apontar as três consequências disso: qual receita está com margem magra, qual desperdiça, e
quanto o custo mudou desde a última compra.

## Goals — Features (faz)

- Lista as receitas do business com custo **recalculado na leitura** (nunca `ingredients_cost`)
- 4 KPIs: custo médio/unidade · margem < 45% (filtra) · desperdício ≥ 8% (filtra) · produção do mês
- Busca por nome, SKU, categoria e subcategoria; chips de categoria; ordenação por 7 colunas
- Drawer de leitura: grupos de ingredientes com subtotal, quadro de custo e a nota que explica
  por que o número muda sozinho
- Ficha técnica impressa PT-07 em duas variantes: **com custo** (orçamento) e **via de produção**
  (bancada, sem nenhum valor de compra) — avulsa ou em lote
- Aponta pro CRUD legado (nova receita · editar ingredientes · produzir) em vez de duplicá-lo
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

- ❌ **Não escreve nada.** A tela é 100% leitura — nenhum POST, PATCH ou DELETE parte daqui.
- ❌ **Não atualiza preço de venda em massa.** O protótipo tem o botão usando `custo × 2`; §18.1
  do handoff diz literalmente *"Não implemente esse fator 2"*, a regra de markup real não foi
  decidida, e escrever em N preços é Tier 0 de VALOR.
- ~~❌ **Não traz a aba Insumos.**~~ — **SUPERADO em 2026-09-04.** A própria razão escrita
  aqui era a ausência de backend (§18.3: *"sem isso, a aba não sai"*), e o backend saiu na
  US-MANU-005 (`usosDoInsumo` no `RecipeBomService`). Sem a premissa, o Non-Goal deixa de
  descrever uma intenção e passa a instruir regressão — a tela ficaria inalcançável.
  ⚠️ Non-Goal é território [W]: se ele discordar, o lugar de barrar é este PR.
- ❌ **Não edita ingredientes nem lança produção** — as duas têm tela própria.
- ❌ **Não remove nem renomeia rota Blade legacy** do módulo.
- ❌ **Não calcula custo no cliente.** Todo número vem derivado do servidor (§9).

## Automation Anti-hooks (o que agente nenhum pode fazer aqui)

- ❌ Consultar receita **sem** o JOIN `mfg_recipes → variations → products.business_id`. Não há
  global scope em Manufacturing; o JOIN é a única barreira de tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- ❌ Servir `mfg_recipes.ingredients_cost` como verdade — a coluna envelhece.
- ❌ Dividir o custo unitário pelo **rendimento**. Divide por `total_quantity` (§7.1 · R-11).
- ❌ Aceitar `production_cost_type` fora de `{fixed, percentage, per_unit}`.
- ❌ Deixar `NaN`/`Infinity` chegar à tela — divisão por zero é `0` (§7.3 · R-13).
- ❌ Colocar qualquer valor de compra na **via de produção** (R-22).

## UX Targets

- Cabe em 1280px sem scroll horizontal fora do `.mfg-tablewrap` (monitor da Larissa/ROTA LIVRE);
  abaixo disso a tabela rola dentro do próprio container, como declara o §3.1
- Linha de tabela ≥ 44px; cabeçalho sticky
- §11 medido: `--text-mute` **não** é usado em texto pequeno (reprova AA nos dois temas — ADR
  `0410`), e `--accent` como **texto** vira `--accent-2` no tema escuro (ADR `0411`)

## Acento visual — por que `--color-primary` e nunca `--accent`

Medido em produção 2026-09-04 ([M] apontou; `getComputedStyle` no navegador, não leitura de CSS):

| | Valor |
|---|---|
| aba ativa em prod | `oklch(0.76 0.15 220)` — **azul** |
| protótipo declara | `oklch(0.55 0.15 295)` — **roxo** |
| `--color-primary` | `oklch(70% .15 295)` — o roxo do DS, intacto |
| `localStorage` | `oimpresso.cockpit.tweaks.accentHue = 220` |

**Causa:** o `AppShellV2` reescreve `--accent` em runtime a partir de `accentHue`, um seletor de
matiz salvo no navegador. Amarrar a identidade da tela nisso faz a preferência de **um** navegador
mandar no Design System — cada pessoa veria uma cor diferente.

**Regra:** as regras do bundle consomem `var(--color-primary)`. Quem "normalizar" de volta pra
`--accent` reintroduz o bug.

### O empate de especificidade do cabeçalho ordenado

`.mfg-th.sort.act` (0,3,0) **empata** com `.mfg-root .mfg-th.sort` (0,3,0) do bloco §11
(`--text-dim`), e no empate vence quem vem **depois** no arquivo — que é o §11.

Até 2026-09-04 quem ganhava era o override de tema escuro, com especificidade maior. Ao
removê-lo (por ser redundante), o cabeçalho **perdeu o acento e virou texto apagado** —
medido em produção depois do deploy: matiz **90** (`oklch(0.72 0.005 90)`) em vez de 295.

A regra `.mfg-root .mfg-th.sort.act` (0,4,0) resolve o empate sem ressuscitar o override.
Quem mexer no bloco §11 precisa manter essa ordem de força.

⚠️ **A 1ª tentativa foi `.mfg-root{--accent:var(--color-primary)}` e o `foundation-guard`
reprovou, com razão:** bundle de módulo **consome** token, nunca **define** (ADR UI-0013 — token
só mora na fundação). Trocar o consumidor é o caminho, e é o que o precedente do Produto
(2026-08-24) já tinha feito. A ADR 0401 registra que *"o azul/ciano da aba ativa NÃO EXISTE na
paleta do DS"*.

## Refs

- Handoff normativo *PROTÓTIPO OFICIAL - FABRICAÇÃO V1* — §4.2 · §4.3 · §7 · §8 · §9 · §16 · §17
- RUNBOOK (F1 PLAN): `memory/requisitos/Manufacturing/RUNBOOK-recipes.md`
- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
- ADRs de DS abertas pelo handoff: `0410` · `0411` · `0412` · `0413`
