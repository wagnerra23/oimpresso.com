---
last_validated: "2026-09-02"
slug: runbook-manufacturing-recipes
title: "RUNBOOK — /manufacturing/recipe (Fabricação · Receitas)"
type: runbook
module: Manufacturing
page: /manufacturing/recipe
component: resources/js/Pages/Manufacturing/Recipes.tsx
status: rascunho
updated_at: 2026-09-02
version: 0.1
owner: W
---

# RUNBOOK — `/manufacturing/recipe` (Fabricação · Receitas)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Porte Inertia da consulta de receitas (ficha técnica / BOM) a partir do handoff
> **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"** (2026-09-01), cuja fonte visual já está no
> espelho: `prototipo-ui/cowork/manufacturing-page.jsx` — conferido contra o ZIP e
> **idêntico** (6 arquivos `.jsx` + o `.css`, 0 linhas de diferença).
>
> **Decisão [W] 2026-09-02:** a tela nova é servida **neste endereço**, não numa rota `/v2`.
> O handoff §15.2 *propunha* `/manufacturing/v2/recipe`; a palavra do dono decide o endereço
> ([ADR 0382](../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)).
> A proibição que o handoff impõe — *"não remover nenhuma rota Blade legacy"* — foi **cumprida**:
> nenhuma linha saiu de `Routes/web.php`.

## 1. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável | Onde olhar |
|---|---|---|
| `/manufacturing/recipe` volta 403 | pacote sem `manufacturing_module` **ou** função sem `manufacturing.access_recipe` | `/superadmin/packages/{id}/edit` + `/roles/{id}/edit` |
| Tela abre vazia com "Nenhuma receita encontrada" e o business TEM receitas | JOIN de tenant não resolveu (`variations → products.business_id`) | `RecipeBomService::listRecipesWithCost` |
| Custo total aparece `R$ 0,00` em todas as linhas | ingrediente sem `variation` carregada, ou `dpp_inc_tax` zerado no insumo | ficha do produto-insumo (Compras é quem escreve o `dpp_inc_tax`) |
| Margem sempre `0%` | `mfg_recipes.final_price` vazio — a receita nunca recebeu preço de venda | editor de ingredientes legado |
| Layout sem estilo (tabela crua) | bundle não carregou | `import '../../../css/cowork-manufacturing-bundle.css'` no topo do `.tsx` |
| A impressão de OUTRA tela sai em branco | regressão do bloco `@media print` do bundle | o bloco é condicionado a `body:has(> .mfg-print-host)` — conferir que a condição não sumiu |

## 2. Estrutura de arquivos

| Papel | Arquivo |
|---|---|
| Tela | [`resources/js/Pages/Manufacturing/Recipes.tsx`](../../../resources/js/Pages/Manufacturing/Recipes.tsx) |
| Charter (lei) | `resources/js/Pages/Manufacturing/Recipes.charter.md` |
| Casos (contrato UC) | `resources/js/Pages/Manufacturing/Recipes.casos.md` |
| Ficha PT-07 | `resources/js/Pages/Manufacturing/_components/FichaPrint.tsx` |
| Tipos + formatação | `resources/js/Pages/Manufacturing/_lib/{tipos.ts,formato.ts}` |
| Bundle CSS (camada 4) | [`resources/css/cowork-manufacturing-bundle.css`](../../../resources/css/cowork-manufacturing-bundle.css) |
| Controller | `Modules/Manufacturing/Http/Controllers/RecipeController@index` |
| Service (payload + custo) | `Modules/Manufacturing/Services/RecipeBomService::listRecipesWithCost` |
| Contadores de produção | `Modules/Manufacturing/Services/ProductionService::{summary,monthSummary}` |
| Fonte de design | `prototipo-ui/cowork/manufacturing-page.jsx` + `manufacturing-page.css` |
| Teste de contrato | `Modules/Manufacturing/Tests/Feature/Wave29RecipeInertiaTest.php` |

## 3. Comandos úteis

```bash
node prototipo-ui/ancora.mjs Manufacturing/Recipes --staging prototipo-ui/cowork
```

```bash
npm run casos:report -- --tela Manufacturing/Recipes
```

```bash
npm run contrato:check -- prototipo-ui/contrato/manufacturing-recipes.contract.json
```

## 4. Smoke local

```bash
npm run build
```

```bash
curl -sv https://oimpresso.test/manufacturing/recipe 2>&1 | grep '^< HTTP'
```

```bash
curl -s https://oimpresso.test/manufacturing/recipe | grep -o '"component":"[^"]*"'
```

Esperado: `"component":"Manufacturing/Recipes"`.

## 5. Smoke prod (R1 — evidência curl, não narração)

```bash
curl -sv https://oimpresso.com/manufacturing/recipe 2>&1 | grep '^< HTTP'
```

```bash
curl -sv https://oimpresso.com/manufacturing/recipe?legacy=1 2>&1 | grep '^< HTTP'
```

Regressão adjacente — as rotas legadas do módulo **não** podem mudar de comportamento:

```bash
curl -sv https://oimpresso.com/manufacturing/production 2>&1 | grep '^< HTTP'
```

```bash
curl -sv https://oimpresso.com/manufacturing/settings 2>&1 | grep '^< HTTP'
```

Depois do 200, **screenshot obrigatório** antes de declarar pronto (proibicoes.md §"Claim sem
evidência" — merge que toca `Pages/**/*.tsx` exige Chrome MCP + print).

## 6. Rollback

Três degraus, do mais barato ao mais caro:

1. **Sem deploy:** `?legacy=1` no mesmo endereço devolve a tela Blade antiga. É a rede de
   segurança do cutover e tem teste de contrato — não é promessa de comentário.
2. **Reverter o PR** (`gh pr revert`): a rota volta a servir o Blade por default. Nada de
   schema muda, porque este PR **não tem migration**.
3. Nada a desfazer no banco: a tela é 100% leitura.

## 7. Tier 0 — invariantes que NUNCA podem quebrar

- **Isolamento multi-tenant** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):
  Manufacturing legacy **não tem global scope**. O `business_id` vem pela cadeia
  `mfg_recipes.variation_id → variations.product_id → products.business_id`, e o JOIN é a
  **única** barreira. Sem ele, receita de outro tenant aparece na lista.
- **Custo é derivado, nunca digitado** (§7 do handoff): sai de `calculateCost()`, que lê
  `variations.dpp_inc_tax` de hoje — nunca de `mfg_recipes.ingredients_cost`, coluna que
  envelhece porque o preço do insumo muda sem passar pela receita.
- **Custo unitário divide por `total_quantity`, não pelo rendimento** (§7.1 · R-11). O
  desperdício aparece como rendimento declarado, não embutido no unitário. É assim no legado.
- **Divisão por zero devolve 0**, nunca `NaN`/`Infinity` (§7.3 · R-13).
- **Nenhuma rota Blade de `Routes/web.php` foi removida** (proibição do §15.2).
- **Escrita em massa de preço NÃO foi implementada.** §18.1 do handoff diz literalmente *"Não
  implemente esse fator 2"*, e escrever em N preços é Tier 0 de VALOR (proibicoes.md §REGRA
  MESTRE — dupla prova + antes→depois + aprovação [W]).

## 8. O que esta onda NÃO entrega (declarado, não esquecido)

| Item do handoff | Por que ficou fora |
|---|---|
| Aba **Insumos** (§4.4) | §18.3: *"não tem backend"* — `usosDoInsumo` é cálculo novo no `RecipeBomService`. O handoff diz *"sem isso, a aba não sai"*. |
| **Editor de ingredientes** (§5) | segue na tela legada; o drawer aponta pra ela (`/manufacturing/add-ingredient`) |
| **Ordem de produção** (§6) | tem tela própria — `/manufacturing/v2/production` (Wave J) e o formulário legado |
| **Relatório** / **Configurações** (§4.6/§4.7) | seguem nas telas legadas; as abas navegam pra elas |
| **"Atualizar preço de venda"** na BulkBar | §18.1 + Tier 0 de valor (ver §7 acima) |

## 9. Refs

- Handoff normativo: *PROTÓTIPO OFICIAL - FABRICAÇÃO V1* (2026-09-01) — §4.2, §4.3, §7, §8, §9, §16, §17
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) MWART · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) multi-tenant
- [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) Constituição UI v2 · PT-01 Lista
- ADRs de DS abertas pelo handoff: `0410` (`--text-mute` reprova AA) · `0411` (`--accent` como texto no escuro) · `0412` (componentes compartilhados não cobrem) · `0413` (sem paleta de impressão)
