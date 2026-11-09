---
slug: 0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica
number: 448
title: "Fabricação é cadastro, Produção é execução e product_bom é a fonte única da composição: decisões D1 a D6 e a ordem de unificação"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-13"
module: pcp
tags: [manufacturing, producao, pcp, composicao, estoque, custo, multi-tenant]
supersedes: []
supersedes_partially:
  - 0431-pcp-sai-do-desejo-modules-producao
  - 0436-os-por-item-producao-dona-da-os-do-pedido
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0152-modules-pcp-feature-wish
  - 0431-pcp-sai-do-desejo-modules-producao
  - 0436-os-por-item-producao-dona-da-os-do-pedido
pii: false
review_triggers:
  - "uma empresa passar a ter linhas em pcp_ordens ou product_bom antes da etapa 2 (o backfill deixa de ser trivial)"
  - "um cliente precisar de fórmula que as 6 regras da D6 não cobrem"
  - "a ordem para estoque precisar existir fora de Modules/Producao"
---

# ADR 0448 — Fabricação × Produção: D1 a D6 e a ordem de unificação

> **Status: `proposto`.** D1 a D6 já são decisão do [W] (abaixo, com data e citação). O que esta
> ADR acrescenta é o desenho das 4 perguntas que ele exigiu e a ordem de unificação. **O merge é
> a ratificação desse desenho.** O merge não responde as perguntas da seção final: elas seguem
> abertas até o [W] responder.
>
> Só documento. Nada de código muda neste PR.

## Contexto

Em 2026-10-09, na revisão do #9086, o [W] pediu Fabricação e Produção em lugares diferentes. A
pesquisa e as 6 decisões estão em
[`proposals/2026-10-09-fabricacao-vs-producao-decisao-w.md`](proposals/2026-10-09-fabricacao-vs-producao-decisao-w.md).
Nenhum código pode seguir a D2 sem esta ADR: o §6 da proposta e o `Producao/SPEC.md` (US-PCP-034,
"Bloqueado") dependem dela.

Respostas do [W], na ordem:

| Data | Citação | Onde |
|---|---|---|
| 2026-10-13 | *"D1 sim · D2 product_bom · D3 a D6 recomendado"* | proposta §3 |
| 2026-10-21 | *"Aprovado"*, sobre a prévia da Fabricação e as 4 perguntas obrigatórias da ADR | proposta §6 |
| 2026-10-23 | Desperdício só na Produção; existe ordem sem venda, criada na Produção; a ordem impressa leva custo (repassado por [F]) | proposta §7 |

Um registro intermediário destoa: em 2026-10-15 o `_DECISOES-W-2026-10-15.md` diz *"Fabricação
(receitas) fica como fonte de material"*. Vale o mais recente: em 2026-10-21 o [W] aprovou que o
contrato passe a ler do `product_bom`. Daí a emenda parcial da 0431. A 0436 é emendada só no
ponto "uma OS por item da venda", porque a ordem para estoque não tem venda.

### As decisões

| # | Decisão aceita | Estado do código em 2026-11-09 |
|---|---|---|
| **D1** | Fabricação é cadastro; a ordem nasce em Produção; a aba de ordens vira consulta; US-MANU-007 cancelada | **Não feito.** A ordem é criada aqui (`ProductionController.php:234`), e `Index.tsx:82` ainda aponta para `/manufacturing/production/create` |
| **D2** | `product_bom` é a fonte única da composição; o editor grava nela e `mfg_recipes` deixa de ser fonte | **Não feito.** Duas composições, recibo abaixo |
| **D3** | A ordem pode existir sem material; iniciar e concluir é que bloqueiam | **Parcial.** Só na OS da venda (US-PCP-032), chave `producao_aguarda_material` desligada por padrão. A ordem da Fabricação não tem esse estado |
| **D4** | Desperdício sai da composição; perda real vai para a ordem | **Parcial.** Saiu das telas em 2026-10-25. `waste_percent` segue gravado e somado pela ordem Blade (`ProductionController.php:339,465`) |
| **D5** | A OS da CV gera ordem por item e usa a mesma composição | **Não feito.** CV segue com `comvis_os` e `cv_ordens_producao`, e com catálogo próprio de materiais |
| **D6** | A regra de consumo vem da unidade do insumo, trocável na linha, sem editor de fórmula; prévia com medida de exemplo; modelos prontos | **Não feito.** `product_bom` não tem regra; `OrcamentoCalculator` ignora `comvis_materiais.unidade` |

### Recibos

Código em `origin/main` `9bc7978aee`. Produção lida em 2026-11-09 por tinker, só leitura e só
contagem, no commit `93c8b4b87f`.

| Fato | Número | Como medir |
|---|---|---|
| Fabricação ainda cria a ordem | 29 menções a `production_purchase` no controller | `git grep -c production_purchase -- Modules/Manufacturing/Http/Controllers/ProductionController.php` |
| `product_bom` no módulo | **0** arquivos de `Modules/Manufacturing` e 0 `.tsx` da tela (controle: `mfg_` em 82) | `git grep -l product_bom -- Modules/Manufacturing` |
| Composição 1: `mfg_recipes` | reserva e falta da Produção, e a baixa da ordem Blade | `git grep -n mfg_recipes -- Modules/Manufacturing/Services/InsumosDaReceitaService.php` |
| Composição 2: `product_bom` | `ReservarEstoque` no FSM da venda, via `BomResolver` | `git grep -l product_bom -- app/Domain` |
| Linhas em produção | `pcp_ordens` **0** · `product_bom` **0** · `mfg_recipes` 4 · `mfg_recipe_ingredients` 13 · `pcp_bobinas` 0 · `pcp_roteiros` 0 | `DB::table('<tabela>')->count()` |
| Ordens antigas | 7 `production_purchase` (3 finais, 4 rascunhos), a mais recente de 2024-12-26 | `DB::table('transactions')->where('type','production_purchase')` |

Ressalva sobre "duas composições baixando estoque": é verdade **nos caminhos do código**, não nos
dados de hoje. Em produção o `product_bom` está vazio, e o `ReservarEstoque` depende de um
`payload.items` que a tela não manda (`CatalogoProcessos.php:74`). A divergência é latente, e
o momento barato de resolver é este, com 13 linhas para migrar.

## Decisão

### 1. Onde grava a regra de consumo (D6)

Na linha do `product_bom`, com a padrão vinda da unidade do insumo.

- `units.tipo_medida` (`area`, `comprimento`, `contagem`, `folha`; nulo permitido) guarda de onde
  vem a regra. É coluna explícita porque o nome não serve: no legado "ml" e "ML" só diferem por
  maiúscula.
- `product_bom.regra_consumo`, nula por padrão, é a troca na linha. Regra efetiva = a da linha,
  senão a da unidade do insumo, senão `unidade` (N por peça, o comportamento de hoje).
- Valores: `area`, `perimetro`, `unidade`, `aproveitamento`, `area_taxa`, `perimetro_passo`. Sem
  editor de fórmula: no banco de CV medido só 4 de 1.041 linhas eram fórmula de verdade (§2b).
- `qty_required` passa a ser a taxa da regra (2,5 ml por m² em `area_taxa`; N por peça em
  `unidade`). `product_bom.passo_cm` serve ao "um a cada N cm" do `perimetro_passo`.

Outras colunas que a D2 exige:

- `product_bom.por_quantidade` (decimal 22,4, padrão 1): `qty_required` vale para essa quantidade
  do produto pai. Assim o rendimento da receita não vira divisão gravada, que perderia precisão.
- `sub_unit_id` e `ingredient_group_id`: 9 das 13 linhas de produção usam grupo.
- O desperdício **não** é copiado (D4).

`mfg_recipes` continua como cabeçalho da receita (instruções, rendimento, custo extra). O que
deixa de ser fonte são as linhas: `mfg_recipe_ingredients` congela depois da etapa 4.

### 2. Contrato `InsumosDaReceita`

A interface fica; a implementação muda de lugar e de fonte. A assinatura passa a ser:

```php
paraQuantidade(int $businessId, int $variationId, string $quantidade,
    ?MedidasDaPeca $medidas = null, array $opcoes = []): array
```

- A implementação sai do Manufacturing e vai para o núcleo, em `app/Domain/Inventory`, junto do
  `product_bom`. A direção de dependência (`DependencyDirectionTest`) permanece correta, e a
  Produção deixa de depender do módulo Manufacturing estar ligado.
- Os 3 chamadores (`FaltaDeMaterialDaOs`, `MateriaisDaEtapa`, `ReservaDeInsumoDaOs`) só passam a
  informar medidas e opções.
- O `BomResolver` serve para a recursão, mas não atende o contrato como está. Cinco diferenças
  que a implementação tem de tratar: devolve `float` (o contrato é DECIMAL de 4 casas) · produto
  sem composição devolve ele mesmo (o contrato devolve vazio) · cai no combo legado · não filtra
  `enable_stock` · ignora `is_optional` (0 ocorrências no arquivo).
- Regra que precisa de medida, sem medida informada: a linha volta marcada `sem_medida` e
  quantidade zero, nunca a `qty_required` crua. Mesmo critério do `incompleto` da `CustoDoFabricado`.
- Medida é da peça final: só o primeiro nível a usa; submontagem recebe a quantidade resultante.
- A OS copia `largura_m`, `altura_m` e as opções ao nascer, como já copia o roteiro. Hoje a
  `CriarOsDaVenda` não lê orçamento nenhum (`git grep -c orcamento` = 0), logo esse vínculo é trabalho
  da etapa 5.
- **Reserva única:** item com OS reserva pela OS; o `ReservarEstoque` do FSM não reserva de novo.
  Hoje as duas reservas não se encontram; com a mesma fonte, passariam a se encontrar.

Mexe em estoque e custo: REGRA MESTRE inteira (dupla prova, antes→depois, aprovação [W]).

### 3. Tamanho físico da mídia

`pcp_bobinas` vira o cadastro único. Não nasce um segundo.

- Ganha `comprimento_m` (nulo = bobina contínua; preenchido = chapa ou folha) e `variation_id`.
- A chave hoje é `material_id`, que aponta para `comvis_materiais`, enquanto o `product_bom` aponta
  para `variations`. Sem esse vínculo a regra `aproveitamento` não acha a mídia (pergunta P5).
- A Fabricação lê por contrato novo em `app/Contracts/Producao`, no molde do `HorasDosPostos`. Sem o
  módulo Produção, a regra responde `sem_medida`.
- Em produção são 0 linhas, então a mudança não migra dado.

### 4. Chave única para material opcional e etapa opcional

A chave é o texto de `opcional_por`, a mesma coluna nos dois lados, comparado normalizado (sem
acento, sem caixa, sem espaço nas pontas).

- `pcp_roteiro_etapas.opcional_por` já existe. `product_bom` ganha a mesma coluna.
  `is_optional` só é tocado pelo CRUD da API, tem 0 linhas e sai depois da etapa 4.
- O lado do pedido é `comvis_orcamento_itens.acabamentos_json`, copiado para a OS como `opcoes`.
  Etapas e materiais com `opcional_por` só entram se a chave estiver nas opções da OS. Hoje
  `ProgramacaoDoRoteiro` e `PrazoPelaFila` as excluem sempre, por `whereNull('opcional_por')`.
- Os dois editores escolhem a chave de uma lista (os acabamentos de `cv_acabamentos` e as chaves já
  usadas), para não virar digitação livre. Um teste de contrato cobre chave sem correspondente.
- Em produção, 0 etapas têm `opcional_por`.

### 5. Como as duas ordens se unificam

**Uma ordem só: `pcp_ordens`.** `production_purchase` deixa de ser ordem e passa a ser o lançamento
de entrada no estoque que a conclusão gera. Mantém-se o tipo porque relatórios, lucros e perdas e a
API já o conhecem.

- `pcp_ordens.transaction_id` e `transaction_sell_line_id` passam a aceitar nulo, e entra `origem`
  (`venda`, `estoque`). A unicidade por linha da venda continua valendo para as ordens com venda.
- **Entrada da ordem para estoque** (o §7 deixou para esta ADR): na conclusão, um
  `production_purchase` final com a quantidade boa, `ref_no` `OS-<id>`, no local da ordem, dentro
  da mesma transação. O custo é calculado no servidor, pela `CustoDoFabricado`, e nunca vem do
  navegador (o `final_total` do cliente é defeito herdado da Blade, sem dono desde o cancelamento
  da US-MANU-007).
- A baixa continua pelo apontamento: `production_sell` por consumo, como já faz o
  `ConsumoMaterialService`.
- A OS de venda não dá entrada: o item sai vendido, como hoje.
- **Quem vê custo** (idem): tela e ordem impressa com custo atrás de `producao.desempenho.ver`, a
  permissão que já guarda os custos da OS. A via de produção, sem custo, não exige essa permissão.
- Na Fabricação sobra o atalho "Nova ordem para estoque", que abre a Produção.
- As 7 ordens antigas ficam como histórico. Não viram `pcp_ordens`.

### 6. Ordem de execução

Cada etapa é um PR. Etapas 2, 3, 5 e 6 passam pela REGRA MESTRE.

| # | Etapa | Efeito em estoque ou custo | Depende de |
|---|---|---|---|
| 0 | Lotes 0 e 1 do Manufacturing: segurança, valor e estoque da ordem Blade enquanto ela existe | Corrige defeito | P2 (só os PRs 1.2, 1.3 e 1.5) |
| 1 | Colunas novas em `product_bom`, `units`, `pcp_bobinas`, `pcp_ordens`. Ninguém lê ainda | Nenhum | esta ADR |
| 2 | Comando idempotente, por empresa, copia `mfg_recipe_ingredients` para `product_bom`, com dry-run e antes→depois das 13 linhas | Nenhum enquanto ninguém lê | 1 |
| 3 | O contrato passa a ler `product_bom`, com reserva única | Sim | 2 |
| 4 | O editor da Fabricação grava em `product_bom`; os demais leitores trocam de fonte, sem mudar o que respondem; `mfg_recipe_ingredients` congela | Custo mostrado | 3 |
| 5 | D6: regra, prévia com medida, medidas e opções na OS; conserto do `OrcamentoCalculator` | Sim | 4, P5 |
| 6 | Ordem para estoque na Produção, com entrada e custo | Sim | 3, P1 |
| 7 | A Blade de criar e editar sai; a aba de ordens vira consulta; o `waste_percent` deixa de ser lido | Sim | 6, P3, P4 |

Etapas 1 a 4 não dependem de cliente. **Não há data**: a Blade sai quando a etapa 6 estiver em
produção, e não antes.

## Consequências

**US-MANU-007 (cancelada).** Não volta. Três itens do DoD dela ficaram sem dono e passam para a US da
ordem para estoque: custo gravado no servidor, conclusão atômica, e rascunho que não mexe em estoque.
A regra *"avisa e não bloqueia"* foi substituída pela D3. O pré-requisito de FSM cai, porque a
`pcp_ordens` já anda no motor. O registro MCP dela está `done` e deveria estar cancelado (lote 4).

**Ordem Blade.** Segue no ar até a etapa 7, e até lá continua merecendo correção: H1, H2 e H3 da
US-MANU-008 valem enquanto o `store` existir. Depois da etapa 7 somem `create`, `edit`, `store` e
`update`; ficam lista e detalhe só para leitura.

**Docs que passam a divergir, a corrigir nos PRs de lote e não aqui:** `Manufacturing/SCOPE.md`
(`purpose`) · `Producao/SCOPE.md` (`excludes` "Manufacturing segue fonte de material") ·
`Producao/SPEC.md` (linhas sobre reusar `MfgRecipe`, US-PCP-016) · o docblock do contrato.

**ADRs do módulo.** A ARQ-0002 (checar tudo antes, travar linha) contradiz a D3 e não corresponde
ao código (`lockForUpdate` em 0 arquivos do módulo). A ARQ-0001 descreve recursão que `mfg_recipes` não
tem. Ao ratificar esta, as duas ficam superadas no que tocam à ordem nova e à composição; quem marca é
o lote 4.

**Custo.** A US-PCP-034 deixa de estar bloqueada a partir da etapa 4. Fatias 2 e 3 seguem atrás da flag.

**Multi-tenant Tier 0.** `product_bom` já tem `business_id` e escopo; toda coluna nova herda. O comando
da etapa 2 recebe o `business_id` e nunca cruza empresas. Toda etapa tem teste entre as empresas 98 e
99 ([ADR 0358](0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## Alternativas descartadas

- **Manter `mfg_recipes` como fonte:** o [W] escolheu `product_bom`, que já resolve submontagem e é
  o que o estoque consome.
- **Sincronizar as duas tabelas:** mantém duas verdades, que é o defeito a remover.
- **Regra de consumo só no insumo, sem troca na linha:** a D6 pede trocável.
- **Ordem para estoque ficar na Fabricação:** o §7 pôs na Produção.
- **Cadastro novo de mídia:** o §6 proíbe nascer um segundo.
- **Tabela nova de opções:** não há dado que a justifique; volta se a lista por acabamentos não bastar.

## Perguntas ao [W]

Abertas. Sem resposta, a etapa indicada não começa. A recomendação é minha.

| # | Pergunta | Recomendo | Trava |
|---|---|---|---|
| **P1** | Escrever agora a US da ordem para estoque na Produção? (B10 da auditoria do Producao) | Sim | Etapa 6 |
| **P2** | Corrigir valor e estoque na ordem Blade, se ela vai sair? (B1 da auditoria do Manufacturing) | Corrigir: não há data de saída | Etapa 0 |
| **P3** | O que fazer com os 4 rascunhos antigos antes do corte? | Quem criou finaliza ou exclui; sem migração automática | Etapa 7 |
| **P4** | `waste_percent`: só parar de ler, ou apagar o dado? | Só parar de ler. Apagar não ganha nada | Etapa 7 |
| **P5** | Materiais da CV × produtos: ligar `comvis_materiais` a `variation_id` ou unificar os catálogos? (D5) | Ligar. Unificar é outra obra | Etapa 5 |
| **P6** | A perda planejada da CV continua no orçamento? (a resposta de 23/10 não tratou disso) | Sim, como a D4 dizia | Etapa 5 |
| **P7** | O editor da Fabricação é a tela de composição da US-PROD-025? A baixa do kit no PDV fica nessa US | Sim, uma tela só | Etapa 4 |
| **P8** | Quando juntar as duas tabelas de OS da CV (`comvis_os`, `cv_ordens_producao`, 0 linhas cada)? | Depois da etapa 7 | Nada aqui |

## Análise prévia

Feita antes de escrever (`pre-adr-introspect`). `decisions-search` e `git grep InsumosDaReceita|pcp_bobinas`
em `memory/decisions`: só a própria proposta. Número livre: `node scripts/governance/next-id.mjs adr` =
0448. Não cria mecanismo novo de governança, logo sem pesquisa externa além da que a proposta já fez.

Não medido: comportamento das etapas (nada foi construído) · o custo do backfill além das contagens ·
se alguma empresa tem produto com composição fora dessas tabelas.

---
**Trilha:** 2026-11-09 — escrita a partir da proposta de 2026-10-09 e das auditorias do Manufacturing
e do Producao (pendência B5 = B10). [CC]
