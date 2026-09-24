---
id: requisitos-manufacturing-sdd-tela-fabricacao-v1-0
type: sdd
module: Manufacturing
status: ativo
owner: wagner
version: 1.0.0
created: 2026-09-01
related_docs:
  - README.md (handoff_fabricacao)
  - design/README.md
  - design/LAUDO-conferencia-fabricacao.md
  - design/CHECKLIST-15D-fabricacao.md
  - resources/js/Pages/Manufacturing/Index.charter.md
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0410-text-mute-reprova-aa-em-rotulo'
  - '0411-accent-sem-versao-escura'
  - '0412-componentes-compartilhados-nao-cobrem'
  - '0413-sem-paleta-de-impressao'
plataforma_alvo: cockpit desktop >= 1280px
---

# SDD — Família de telas Fabricação (Manufacturing) · v1.0

> Documento de origem: **por que** a tela existe e **qual comportamento é obrigatório**.
> Congelado nesta versão. Mudança vira `v1.1`, não edição no lugar.
>
> Blocos marcados `<!-- derivado -->` podem ser re-lidos do código a qualquer momento.
> Blocos `<!-- curado -->` são foto datada e envelhecem.

---

## 1 · Base empírica <!-- derivado -->

O que existe hoje no codebase alvo, lido em **2026-09-01** na pasta montada `oimpresso.com/`:

| Camada | Estado |
|---|---|
| Módulo PHP | `Modules/Manufacturing/` completo: 6 controllers, 2 services (`RecipeBomService`, `ProductionService`), 3 entities (`MfgRecipe`, `MfgRecipeIngredient`, `MfgIngredientGroup`), 7 form requests, 18 testes de feature |
| Schema | `mfg_recipes`, `mfg_recipe_ingredients`, `mfg_ingredient_groups`; produção em `transactions` com `type = 'production_purchase'` + colunas `mfg_is_final`, `mfg_production_cost`, `mfg_production_cost_type` |
| Views | Blade legacy: `recipe/{index,create,add_ingredients}`, `production/{index,create,show,report}`, `settings/index` |
| Inertia | **1 página**: `resources/js/Pages/Manufacturing/Index.tsx` (315 linhas) na rota `/manufacturing/v2/production` — lista de ordens, 5 colunas, 4 KPIs. Charter em `Wave J`, status `draft` |
| Permissões | `manufacturing.access_recipe`, `manufacturing.add_recipe`, `manufacturing.edit_recipe`, `manufacturing.access_production` + gate de assinatura `manufacturing_module` |
| Cálculo | `RecipeBomService::calculateCost()` e `ManufacturingUtil::getRecipeTotal()` — dois lugares, **mesma fórmula**, paridade declarada no docblock do service |

**Lacuna que motiva este SDD:** o custo de produção existe no banco e no service, e **não existe
tela** que o mostre para quem forma preço. A única tela Inertia do módulo lista ordens já lançadas
— nada sobre receita, ingrediente, margem ou impacto de insumo. Quem precisa do número hoje abre o
Blade legacy do UltimatePOS, em inglês estrutural e sem margem.

---

## 2 · Visão geral <!-- curado -->

A família responde três perguntas de negócio, na ordem em que aparecem no dia:

1. **Quanto custa produzir isto, com o preço de insumo de hoje?** → consulta de receitas + drawer.
2. **Onde meu preço está errado?** → KPI de margem magra e de desperdício, que filtram a lista.
3. **O que acontece se o fornecedor reajustar?** → aba Insumos, impacto reverso com simulação.

E duas de operação: **lançar o lote produzido** (ordem de produção, com consumo proporcional e
custo congelado no fechamento) e **levar a ficha para a bancada** (folha de prova impressa, com ou
sem custo).

### Non-goals desta versão

- Não é fila de chão de fábrica (OS, etapas, apontamento) — isso é do módulo Oficina/Produção.
- Não é planejamento de compra (sugestão de reposição a partir de receita) — é candidato a v1.1.
- Não é ficha de processo (instruções de execução): o campo `mfg_recipes.instructions` existe no
  schema e **esta versão não o expõe**.
- Não substitui as telas Blade: coexistência, como o charter atual já determina.
- Não mexe em estoque nem em fiscal além de disparar o que o backend já dispara.

---

## 3 · Personas <!-- curado -->

| Persona | Contexto | O que precisa | O que não pode acontecer |
|---|---|---|---|
| **Larissa** · balcão/orçamento | monitor de loja, luz alta, teclado+mouse, cliente no telefone | custo unitário e margem em segundos, por nome ou SKU | ver custo desatualizado sem saber que está desatualizado |
| **Wagner** · dono | revisa semanalmente | quem está com margem magra, quem desperdiça, qual insumo concentra custo | reprecificar em massa sem entender o efeito |
| **Eliana** · produção | bancada, tablet ou papel | lista de separação do lote, sem preço de compra circulando | ver preço de compra na via de produção |

**Persona de score** (CHECKLIST): Larissa. É ela que abre a tela dez vezes por dia.

---

## 4 · Governança (Tier 0) <!-- derivado -->

| Invariante | Onde se garante |
|---|---|
| **Isolamento multi-tenant** — nenhuma query sem `business_id` | Manufacturing **não tem global scope**: o vínculo é a cadeia `mfg_recipes.variation_id → variations.product_id → products.business_id`, feita à mão em `RecipeBomService::resolveBom()` L50-58. ADR 0093 |
| **Permissão no servidor** | todo método de controller abre com `can('superadmin') \|\| hasThePermissionInSubscription($business_id, 'manufacturing_module')` **e** a permissão específica |
| **Sem `withoutGlobalScopes`** | proibido no charter do módulo (`Index.charter.md` L33) |
| **Sem `UPDATE` direto em `transactions`** | proibido no mesmo charter — o trait de FSM de Sells/Repair não cobre Manufacturing |
| **Dinheiro recalculado no servidor** | o cliente nunca é fonte de `final_total` (handoff §9) |
| **Auditoria** | `MfgRecipe`, `MfgRecipeIngredient` e `MfgIngredientGroup` já usam Spatie ActivityLog (`useLogName('manufacturing.*')`); retenção em `Config/retention.php` |

---

## 5 · Casos de uso <!-- curado -->

Lista **`[FECHADA]`** para a v1.0: 22 casos. Cada um tem o teste de aceite no handoff §17 quando é
requisito de interface.

### Consulta e leitura

| ID | Caso de uso | Persona | Requisito |
|---|---|---|---|
| CU-MFG-01 | Listar receitas do business com custo, venda e margem calculados na leitura | Larissa | R-01, R-11 |
| CU-MFG-02 | Buscar receita por nome, SKU, categoria ou subcategoria | Larissa | R-03, R-04 |
| CU-MFG-03 | Filtrar por categoria | Larissa | R-01 |
| CU-MFG-04 | Filtrar por margem abaixo de 45% | Wagner | R-05 |
| CU-MFG-05 | Filtrar por desperdício ≥ 8% | Wagner | R-05 |
| CU-MFG-06 | Ordenar por qualquer coluna, asc/desc | Larissa | R-06 |
| CU-MFG-07 | Abrir a ficha de uma receita e ver a composição por grupo, com subtotal | Larissa | R-14 |
| CU-MFG-08 | Ver o rendimento líquido e a sub-unidade de saída declarados | Eliana | R-09 |

### Cadastro da receita

| ID | Caso de uso | Persona | Requisito |
|---|---|---|---|
| CU-MFG-09 | Criar receita para uma variação, opcionalmente clonando outra | Wagner | — |
| CU-MFG-10 | Impedir duas receitas para a mesma variação | Wagner | — |
| CU-MFG-11 | Adicionar/remover ingrediente, com busca por nome ou SKU | Wagner | R-15 |
| CU-MFG-12 | Agrupar ingredientes (grupo reusável entre receitas) | Wagner | — |
| CU-MFG-13 | Lançar consumo em sub-unidade de compra, com o multiplicador aplicado ao custo | Wagner | — |
| CU-MFG-14 | Definir desperdício, custo extra (fixo / % / por unidade) e preço de venda | Wagner | R-12 |
| CU-MFG-15 | Excluir receita, com aviso do que se perde e do que sobrevive | Wagner | — |
| CU-MFG-16 | Bloquear edição de quantidade de ingrediente por configuração | Wagner | R-16 |

### Produção

| ID | Caso de uso | Persona | Requisito |
|---|---|---|---|
| CU-MFG-17 | Lançar ordem de produção com consumo proporcional calculado da receita | Eliana | R-17 |
| CU-MFG-18 | Ajustar consumo real de um ingrediente (override) na ordem | Eliana | R-17 |
| CU-MFG-19 | Avisar estoque insuficiente sem bloquear o lançamento | Eliana | R-18 |
| CU-MFG-20 | Salvar rascunho (sem movimento) ou finalizar (entrada + baixa + custo congelado) | Eliana | R-19, R-20, R-21 |

### Análise e saída

| ID | Caso de uso | Persona | Requisito |
|---|---|---|---|
| CU-MFG-21 | Ver, por insumo, quais receitas o usam, o peso dele no custo e o efeito de uma variação de preço | Wagner | — |
| CU-MFG-22 | Imprimir a ficha técnica — com custo (orçamento) ou via de produção (bancada) — inclusive em lote | Eliana | R-22, R-23 |

**Fora da v1.0, declarado:** relatório por período e configurações do módulo existem na tela e são
**portes diretos** do Blade legacy (`production/report`, `settings/index`) — não recebem CU próprio
porque não mudam de comportamento.

---

## 6 · Modelo de dados acordado <!-- derivado -->

O mapa completo campo-a-campo está no handoff §16. As cinco decisões de modelo que importam aqui:

1. **A receita não tem nome próprio.** O rótulo vem da cadeia da variação
   (`products.name` + `product_variations.name` + `variations.name` + `sub_sku`), do jeito que
   `MfgRecipe::forDropdown()` monta. **Não criar coluna `name`.**
2. **Custo de insumo é leitura de outra tela.** `variations.dpp_inc_tax` é escrito por Compras;
   Manufacturing só lê. Nenhuma tela desta família escreve preço de compra.
3. **`mfg_recipes.ingredients_cost` existe e não é verdade.** É cache, envelhece quando o insumo
   muda de preço. A leitura é sempre recalculada (é o que o legado já faz).
4. **A ordem finalizada congela o custo** em `transactions.final_total`; o rascunho não tem custo
   gravado. Os dois números convivem na tela e a diferença é exibida, nunca escondida.
5. **Desperdício mora em dois níveis** no schema: `mfg_recipes.waste_percent` (receita) e
   `mfg_recipe_ingredients.waste_percent` (ingrediente). A v1.0 usa **só o da receita**. Dívida D-2.

---

## 7 · Requisitos não-funcionais <!-- curado -->

| # | Requisito | Medida |
|---|---|---|
| RNF-1 | Consulta responde com o custo já calculado, sem segunda ida ao servidor | 1 requisição por navegação; filtros por partial reload (`only: [...]`) |
| RNF-2 | Paginação, busca e ordenação no servidor | `DataTable` com `PaginatorShape` (ADR 0412 §2) |
| RNF-3 | Nenhum cálculo de dinheiro no cliente vale como verdade | handoff §9 |
| RNF-4 | PT-BR em toda copy; `pt-BR` em número, moeda e data | handoff §16 |
| RNF-5 | Contraste AA em todo texto abaixo de 18,66px | LAUDO §4 — hoje 3 pares reprovam (ADR 0410, 0411) |
| RNF-6 | Folha impressa A4, sem chrome, com quebra por grupo de ingrediente | handoff §8 |
| RNF-7 | Sem `localStorage` de dado de domínio; se houver preferência, chave `oimpresso.manufacturing.*` com escopo `{tenant}.{user}` | handoff §13 |
| RNF-8 | Observabilidade: os spans OTel já existentes do módulo continuam valendo (`manufacturing.recipe.*`, `manufacturing.production.*`) | `Services/*.php` |

---

## 8 · Riscos e dívidas <!-- curado -->

| # | Dívida / risco | Impacto | Encaminhamento |
|---|---|---|---|
| D-1 | **Markup do "atualizar preço de venda" não decidido** (protótipo usa custo × 2) | escrita em massa no catálogo | bloqueia o botão até haver regra — handoff §18.1 |
| D-2 | **Desperdício por ingrediente** existe no schema e não na tela | conta de rendimento pode divergir do legado em receita que use o campo | medir quantas receitas do piloto têm `waste_percent` ≠ 0 no ingrediente antes de decidir |
| D-3 | **Aba Insumos sem backend** | a aba não sai sem um método novo no service | `RecipeBomService::usosDoInsumo()` + teste de tenant |
| D-4 | **Ordem de produção sem FSM** | rascunho→finalizada sem máquina de estado; charter proíbe `UPDATE` direto em `transactions` | caminho explícito antes de produção |
| D-5 | **`--text-mute` e `--accent`** reprovam contraste | acessibilidade em toda a família | ADR 0410 e 0411 (decisão do DS) |
| D-6 | **Sem paleta de impressão no DS** | cinzas literais na folha | ADR 0413 |
| D-7 | **Duas implementações da mesma fórmula** (`RecipeBomService::calculateCost` e `ManufacturingUtil::getRecipeTotal`) | divergem em silêncio se uma mudar | a tela consome **uma só**; unificar é dívida do backend |
| D-8 | Folha PT-07 **não medida** em impressão real | risco de cotas/tira saírem fora | conferir antes de liberar para a bancada |

---

## 9 · Qualidade e rollout <!-- curado -->

**Ordem sugerida** (cada etapa entrega valor sozinha):

| Onda | Entrega | Depende de |
|---|---|---|
| 1 | Consulta de receitas + drawer (leitura pura) | nada além do service atual |
| 2 | Diff da aba Ordens (8 colunas, `fix`, rodapé, `StatusBadge kind="producao"`) | ADR 0412 §1 |
| 3 | Editor de ingredientes (escrita) | requests já existentes |
| 4 | Formulário de ordem + finalizar | D-4 resolvido |
| 5 | Relatório + configurações (porte direto) | nada |
| 6 | Ficha PT-07 | D-6, D-8 |
| 7 | Aba Insumos | D-3 |

**Piloto:** biz do Wagner (`biz=1`) — nunca cliente real em smoke (ADR 0101).
**Critério de liberação por onda:** os testes de aceite do handoff §17 da onda passando, LAUDO
sem achado ALTA aberto, e Anexo A do CHECKLIST sem exceção nova e não declarada.

---

## 10 · Rastreabilidade

Os 22 CU acima aparecem no protótipo apenas como comportamento — **não há modo meta** nesta
família (a tela é só a tela). O vínculo requisito↔tela é este documento mais a tabela de requisitos
do handoff §17.
