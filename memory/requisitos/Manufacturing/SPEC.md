---
id: requisitos-manufacturing-spec
module: Manufacturing
owner: wagner
version: "1.0"
last_updated: "2026-09-02"
na_justified:
  D6.a: "Manufacturing usa pattern Blade legacy + 1 página Inertia v2 (Wave J Onda 1) — Inertia::defer aplicado parcialmente."
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-MANU-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-MANU-001 · Quanto custa produzir, com o preço de insumo de hoje

**Como** Larissa (balcão/orçamento) e Wagner (dono)
**Quero** ver, numa tela só, o custo de cada receita recalculado a partir do preço de compra
atual dos ingredientes — com margem, desperdício e a ficha técnica imprimível
**Para** formar preço sem consultar planilha, e enxergar qual receita ficou com margem magra
depois que um insumo subiu

> Escrita em 2026-09-02 a partir do handoff normativo **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"**
> (§2 *"O que a tela faz"* e §17 *Requisitos com teste de aceite*) e do pedido de [W] nomeando o
> endereço. Antes disso era placeholder `[TODO]` — não havia US escrita neste módulo.

**Implementado em:** `resources/js/Pages/Manufacturing/Recipes.tsx` ·
`Modules/Manufacturing/Http/Controllers/RecipeController.php` (`@index`) ·
`Modules/Manufacturing/Services/RecipeBomService.php` (`listRecipesWithCost`)

**Definition of Done:**
- [x] O custo é **recalculado na leitura** a partir de `variations.dpp_inc_tax` — nunca servido de
      `mfg_recipes.ingredients_cost`, coluna que envelhece (§9 do handoff)
- [x] O custo unitário divide por `total_quantity`, **não** pelo rendimento (R-11)
- [x] As três fórmulas de `production_cost_type` dão três resultados distintos (R-12)
- [x] Divisão por zero devolve `0`, nunca `NaN`/`Infinity` (R-13)
- [x] Isolamento de tenant pela cadeia `mfg_recipes → variations → products.business_id` — o JOIN
      é a única barreira, porque o módulo não tem global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- [x] Ficha técnica PT-07 em duas variantes; a **via de produção** não mostra nenhum valor de
      compra (R-22)
- [x] Nenhuma rota Blade legada removida; `?legacy=1` devolve a tela antiga no mesmo endereço
- [ ] Smoke real em prod (`curl` + screenshot) — só existe depois do deploy; receita no
      [RUNBOOK-recipes.md](RUNBOOK-recipes.md) §5

**Fora do escopo desta US** (declarado, com a razão):
- Atualizar preço de venda em massa — §18.1 do handoff proíbe o `custo × 2` do protótipo e a regra
  de markup real não foi decidida; escrever em N preços é Tier 0 de valor
- Aba Insumos — §18.3: `usosDoInsumo` é cálculo novo sem backend
- Editor de ingredientes, ordem de produção, relatório e configurações — têm tela própria

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-MANU-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Manufacturing
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _lacuna — Modules/Manufacturing/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-MANU-002 · Autorização Spatie `manufacturing.access_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_recipe')`  
**Testado em:** _lacuna — Modules/Manufacturing/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-MANU-003 · Autorização Spatie `manufacturing.add_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.add_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.add_recipe')`  
**Testado em:** _lacuna — Modules/Manufacturing/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-MANU-004 · Autorização Spatie `manufacturing.edit_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.edit_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.edit_recipe')`  
**Testado em:** _lacuna — Modules/Manufacturing/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_

### R-MANU-005 · Autorização Spatie `manufacturing.access_production`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_production`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_production')`  
**Testado em:** _lacuna — Modules/Manufacturing/Tests/Feature/PermissionsTest não existe (stub pendente; reconciliação 2026-07-01, cobertura a criar)_
