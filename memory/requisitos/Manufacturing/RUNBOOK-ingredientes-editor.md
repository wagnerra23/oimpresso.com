---
last_validated: "2026-10-08"
slug: runbook-manufacturing-ingredientes-editor
title: "RUNBOOK — /manufacturing/add-ingredient?tela=nova (Fabricação · Editor de ingredientes)"
type: runbook
module: Manufacturing
page: /manufacturing/add-ingredient
component: resources/js/Pages/Manufacturing/IngredientesEditor.tsx
status: rascunho
updated_at: 2026-10-08
version: 0.1
owner: M
---

# RUNBOOK — `/manufacturing/add-ingredient?tela=nova` (Fabricação · Editor de ingredientes)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> US-MANU-006 — o editor da ficha técnica: grupos, ingredientes, sub-unidades e o custo ao vivo.
> Fonte visual: `prototipo-ui/cowork/Wagner/manufacturing-recipe.jsx::MfgIngredientesEditor`.
> Regras: handoff `prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md` §5 (6 regras
> `[FECHADA]`), §7 (fórmulas) e §9 (o que o servidor garante).

## 1. Etapas

| Etapa | O quê | Estado |
|---|---|---|
| 0 | Servidor do salvar: custo calculado no servidor, recusa receita vazia, quantidade ≤ 0 e sub-unidade alheia; trocar todos os ingredientes não deixa os antigos | PR #9051 |
| 1 | Dados do editor (`RecipeBomService::editorDaReceita`) + busca de insumo (`buscarInsumos`, 7 resultados) + a tela em **modo leitura** | #9059 (mergeado) |
| 2 | Edição: quantidade, sub-unidade (troca o multiplicador junto, regra 4), grupos, busca de insumo e salvar no `store()`; regra 1 (≥1 ingrediente), regra 2 (quantidade travada vira texto), regra 5 (cópia; cancelar descarta). Números vão por `paraNumUf` (@/Lib/numberPtBR, promovido do Patrimônio): vírgula decimal, sem milhar, com as casas exibidas no campo — nunca cru | #9068 (mergeado) |
| 3 | Excluir receita com a confirmação da regra 6 (no editor: o protótipo não põe o botão na gaveta, e o charter de Receitas proíbe escrita na lista) · trocar o link "Editar ingredientes" da lista para a tela nova. O servidor só apaga receita da própria empresa: #9071 (UC-RECIPE-19) | #9072 (mergeado) |
| 4 | Prova de TELA das 6 regras do §5 (UC-INGRED-08..13): `tests/js/manufacturing-ingredientes-editor.test.tsx`, vitest/jsdom no componente real, na lane `manufacturing-jsdom-gate`. Cada bloco tem controle positivo; 8 mutações provaram a mordida. Fecha o registro da US-MANU-006 no SPEC | este PR |

Até a etapa 3 a janela Blade era a padrão. Desde ela, o "Editar ingredientes" da lista abre a tela nova;
o mesmo endereço sem `?tela=nova` continua servindo a janela Blade (e a lista antiga `?legacy=1` aponta para ela).

## 2. Quem abre e quem grava (regra 3)

- **Abrir:** `manufacturing.access_recipe` (o editor abre em leitura para quem só consulta).
- **Gravar:** `manufacturing.add_recipe` — é a barreira do `store()` (`StoreRecipeRequest::authorize`).
  `manufacturing.edit_recipe` não tem rota que o alcance (SPEC R-MANU-004), por isso não é usado aqui.
- Busca de insumo (`/manufacturing/editor-receita/insumos`): `add_recipe`, porque só serve para incluir.

## 3. O que a tela NÃO mostra, de propósito

- **Nome da receita** — não existe; a receita usa o nome do produto, que aparece na trilha.
- **Preço de venda / Política de preço / Natureza fiscal** (estão no protótipo) — o servidor não tem
  onde gravar nenhum dos três. E `mfg_recipes.final_price` guarda o **custo total do lote** (a janela
  Blade grava ingredientes + custo extra nele; a seed `DummyBusinessSeeder.php:1125` confirma), não o
  preço de venda. Decisão [W] pendente, registrada no corpo do #9051.
- **"Atualizar preço de venda" em massa** — SPEC US-MANU-007 §"O que NÃO vira onda".

## 4. Estrutura de arquivos

| Arquivo | Papel |
|---|---|
| `resources/js/Pages/Manufacturing/IngredientesEditor.tsx` | a tela |
| `resources/js/Pages/Manufacturing/_lib/custo.ts` | custo ao vivo — mesma conta do `calculateCost` · `montarEnvio`/`paraNumUf`: o formato do salvar |
| `resources/js/Pages/Manufacturing/_components/BuscaInsumo.tsx` | busca de insumo do "Ingrediente em <grupo>" |
| `resources/js/Pages/Manufacturing/_components/ExcluirReceitaDialog.tsx` | confirmação da regra 6 · `DELETE /manufacturing/recipe/{id}` (`RecipeController::destroy`) |
| `Modules/Manufacturing/Http/Controllers/RecipeController.php` | `addIngredients` (ramo `?tela=nova` → `telaEditor`) · `insumosParaReceita` |
| `Modules/Manufacturing/Services/RecipeBomService.php` | `editorDaReceita` · `buscarInsumos` |
| `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php` | UC-INGRED-* |

## 5. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável |
|---|---|
| 404 ao abrir | `variation_id` de outra empresa ou inexistente (`variacaoDaEmpresa`) |
| Linha sem seletor de unidade | unidade do insumo fora da empresa (dado antigo): `subUnidades` devolve vazio em vez de derrubar a tela |
| Custo ao vivo ≠ custo da lista de Receitas | `_lib/custo.ts` divergiu do `calculateCost` — as duas têm de ser a mesma conta |
| Quantidade gravada ×1000 ou ÷1000 do digitado | o salvar mandou número cru em vez de `paraNumUf` (o `num_uf` leu o ponto como milhar). UC-INGRED-06 |
| Apareceu um grupo chamado "Sem grupo" | linha sem grupo foi mandada com `ig_index` — o balde tem `sem_grupo: true` e não vai como grupo. UC-INGRED-07 |

## 6. Smoke prod (R1)

Empresa 1, uma receita existente: abrir `/manufacturing/add-ingredient?variation_id=<id>&tela=nova`,
conferir grupos, linhas e o "Custo por <unidade>" contra a lista de Receitas da mesma receita.
Sem cookie: `curl -sv` deve devolver `302` para `/login`.

## 7. Rollback

Revert do PR. Nada grava nesta etapa; a janela Blade nunca deixou de ser a padrão.
