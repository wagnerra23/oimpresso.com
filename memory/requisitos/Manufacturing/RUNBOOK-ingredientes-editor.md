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
| 1 | Dados do editor (`RecipeBomService::editorDaReceita`) + busca de insumo (`buscarInsumos`, 7 resultados) + a tela em **modo leitura** | este PR |
| 2 | Edição: quantidade, sub-unidade (troca o multiplicador junto, regra 4), grupos, busca de insumo e salvar no `store()`; regra 1 (≥1 ingrediente), regra 2 (quantidade travada vira texto), regra 5 (cópia; cancelar descarta) | a fazer |
| 3 | Excluir receita com a confirmação da regra 6 · trocar o link "Editar ingredientes" da lista para a tela nova | a fazer |

A janela Blade continua sendo a padrão até a etapa 3: sem `?tela=nova` nada muda para ninguém.

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
| `resources/js/Pages/Manufacturing/_lib/custo.ts` | custo ao vivo — mesma conta do `calculateCost` |
| `Modules/Manufacturing/Http/Controllers/RecipeController.php` | `addIngredients` (ramo `?tela=nova` → `telaEditor`) · `insumosParaReceita` |
| `Modules/Manufacturing/Services/RecipeBomService.php` | `editorDaReceita` · `buscarInsumos` |
| `Modules/Manufacturing/Tests/Feature/EditorIngredientesTest.php` | UC-INGRED-* |

## 5. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável |
|---|---|
| 404 ao abrir | `variation_id` de outra empresa ou inexistente (`variacaoDaEmpresa`) |
| Linha sem seletor de unidade | unidade do insumo fora da empresa (dado antigo): `subUnidades` devolve vazio em vez de derrubar a tela |
| Custo ao vivo ≠ custo da lista de Receitas | `_lib/custo.ts` divergiu do `calculateCost` — as duas têm de ser a mesma conta |

## 6. Smoke prod (R1)

Empresa 1, uma receita existente: abrir `/manufacturing/add-ingredient?variation_id=<id>&tela=nova`,
conferir grupos, linhas e o "Custo por <unidade>" contra a lista de Receitas da mesma receita.
Sem cookie: `curl -sv` deve devolver `302` para `/login`.

## 7. Rollback

Revert do PR. Nada grava nesta etapa; a janela Blade nunca deixou de ser a padrão.
