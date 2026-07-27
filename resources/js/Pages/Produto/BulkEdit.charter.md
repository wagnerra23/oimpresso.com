---
id: resources-js-pages-produto-bulk-edit-charter
page: /products/mass-edit
component: resources/js/Pages/Produto/BulkEdit.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
related_us: [US-PROD-020, US-PROD-023]
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-bulk-edit.md
related_visual_comparison: memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [BulkEdit]
  divergence_from_blueprint: "datatable multi-row edit — pattern distinto de Index Cockpit. ADR 0149 §'Casos que NÃO se qualificam — bulk-edit datatable (interação multi-row distinta de Index)'. Mantém família AppShellV2 + tokens + header pattern; diverge no conteúdo central edit-in-place"
---

# Page Charter — /products/mass-edit (DRAFT)

## Mission

Editar atributos comuns (Category/Sub/Brand/Tax/Locations + preços variations) em N produtos selecionados simultaneamente. Tabela densa edit-in-place + aviso destrutivo claro.

## Goals

- AppShellV2 + PageHeader "Edição em massa · {N} produtos"
- Banner aviso destrutivo "Estas alterações afetam {N} produtos simultaneamente"
- Tabela densa edit-in-place: 1 linha por produto + colunas editáveis
- Colunas: Categoria (select) · Sub-categoria · Brand · Tax · Locations (multi) · Variations prices (sub-rows)
- Botão "Atualizar {N} produtos" sticky topo (primary destructive)
- Multi-tenant: `business_id` scope nas queries
- Submit POST `/products/mass-update`

## Non-Goals

- ❌ Adicionar produto novo inline
- ❌ Deletar produto inline
- ❌ Editar SKU/name/type (sensíveis demais pra bulk)
- ❌ Variation builder novo inline

## UX Targets

- p95 < 1.5s (N produtos × variations)
- Larissa hesita antes confirmar — banner aviso visível
- Confirmação modal "Confirma alterar {N} produtos?" antes submit

## Anti-patterns

- ❌ `withoutGlobalScopes` sem comentário SUPERADMIN
- ❌ Submit sem confirmação destrutiva
- ❌ Edit-in-place sem feedback visual (linha dirty)

## Pest GUARD

```php
// Estáticos (grep no fonte) — tests/Feature/Produto/Wave2BulkEdit{Inertia,Baseline}Test.php
it('Page existe em Pages/Produto/BulkEdit.tsx')                 // ✅ existe
it('Page declara interface ProdutoBulkEditPageProps')           // ✅ existe (matriz products × atributos)
it('Page tem banner aviso destrutivo (UX anti-pattern)')        // ✅ existe

// Comportamento (request real, MySQL) — tests/Feature/Produto/ProdutoBulkEditContratoTest.php
it('UC-PBULK-02 · produto de outro business não entra na matriz (multi-tenant Tier 0)')
```

> 📌 **Correção factual 2026-07-26 (agent `sdd-from-source`).** Até esta data o §Pest GUARD prometia
> `it('Controller cross-tenant não inclui produtos biz=99')` — teste que **não existia**: varredura
> contada nos dois arquivos Wave2 da tela devolveu **0** ocorrências de cross-tenant/biz, e ambos só
> fazem `grep` de string no fonte (nenhum dispara request). O guard Tier 0 agora existe de fato como
> `UC-PBULK-02`. Re-medir com
> `grep -rn "it(" tests/Feature/Produto/Wave2BulkEdit*.php tests/Feature/Produto/ProdutoBulkEditContratoTest.php`.

## Divergências abertas (decisão [W] — registradas, NÃO resolvidas aqui)

> Fatos medidos em 2026-07-26 (sha `6cd0fbc4f2`) que contradizem promessas deste charter. O agente
> **não escolhe o vencedor** quando a correção exige saber o que [W] quis
> ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) Fase 2.6).

| Promessa do charter | Fato medido | Remédios possíveis (só [W] decide) |
|---|---|---|
| §Goals *"Submit POST `/products/mass-update`"* | a rota **não existe**: o literal aparece 3× no repo (este charter, o RUNBOOK e o `.tsx`) e **0×** em `routes/`; o writer real é `POST /products/bulk-update` | criar alias `mass-update` · repontar a tela pro `bulk-update` |
| §Goals *"Colunas: … Locations (multi) …"* | a tabela React **não renderiza** coluna de localização; o payload só faz round-trip de `productLocations` | construir a coluna · remover do §Goals |
| Tela existe e é MWART F3 concluído | o botão de entrada está atrás de `config('constants.enable_product_bulk_edit')` = **`false`** (`config/constants.php:84`, nota upstream *"Will be depreciated in future"*) | ligar a flag · manter desligada e declarar Non-Goal/remoção · substituir pelo `/unificado` |

Detalhe + contrato executável: [`BulkEdit.casos.md`](BulkEdit.casos.md) §"Três fatos medidos" e §Backlog.

## Refs

- Casos (contrato): [`BulkEdit.casos.md`](BulkEdit.casos.md) — UC-PBULK-01..06
- RUNBOOK: [`memory/requisitos/Produto/_telas/RUNBOOK-produto-bulk-edit.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-bulk-edit.md)
- Visual comparison: [`memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md`](../../../../memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md)
- SDD: [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §5.3 **F5.1** + §6.1 `CU-PROD-06`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
| 2026-07-26 | [CC] `sdd-from-source` | **Só fatos** (nenhuma intenção tocada): §Refs apontava pra `memory/requisitos/Inventory/…`, path que **não existe** (`ls memory/requisitos/Inventory/` = só `BRIEFING.md` + `SPEC.md`) → corrigido pros paths reais em `Produto/_telas/`, que o próprio frontmatter já usava. §Pest GUARD prometia teste cross-tenant inexistente → reconciliado com os testes que existem + o novo `UC-PBULK-02`. Nova §Divergências abertas registra 3 promessas não cumpridas **sem** escolher remédio. Link pro `casos.md` (trio). |
