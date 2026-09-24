# Handoff: Fabricação (Manufacturing) — receitas, ordens de produção, insumos, relatório, configurações

Pacote de handoff da família de telas do módulo **Manufacturing** do oimpresso: a consulta de
receitas (ficha técnica / BOM), o editor de ingredientes, a ordem de produção, a análise de
impacto de insumo, o relatório do período, as configurações do módulo e a ficha técnica impressa.
Escrito com detalhe suficiente para reimplementá-la no codebase real **sem ter participado da
conversa que a desenhou**.

---

## §0 · Contrato de leitura (ler antes de escrever qualquer linha)

1. **Este documento é normativo. O protótipo é ilustrativo.** Onde os dois divergirem, vale este
   documento; se o documento estiver calado, **pare e pergunte** — não preencha.
2. **Toda lista aqui é declarada `[FECHADA]` ou `[ILUSTRATIVA]`.** Lista `[FECHADA]` não recebe
   item novo, nem "por consistência", nem vindo do legado. Lista `[ILUSTRATIVA]` mostra exemplo e
   o item real vem do banco.
3. **Procedência de todo valor está marcada:**
   `[DS]` = citado do design system / codebase alvo, com arquivo e linha ·
   `[TELA]` = decidido nesta tela · `[RUNTIME]` = observado rodando (nunca é regra) ·
   `[LEGADO]` = está no módulo Blade atual.
4. **Não substituir componente do design system.** Se o componente não cobre o caso, o contorno é
   na tela e vira item da pauta (`contexto/pauta-design-system.md`). Recriar componente localmente
   está proibido.
5. **Não acrescentar** coluna, aba, filtro, campo, ação, KPI ou rota que não esteja aqui.
   **Não substituir** rota, permissão ou nome de campo. **Não completar** regra ausente por
   analogia com outro módulo.
6. **Cada requisito de §17 tem um teste de aceite de uma linha.** Você se autoconfere por ele.
7. As leis de escrita que este pacote segue estão em `contexto/manual-escrita-para-agente.md`.
   Elas explicam *por que* o documento é assim; não é preciso concordar para executar.

---

## §1 · Sobre os arquivos deste pacote

`design/` contém **referências de design escritas em HTML/JSX** — protótipos que mostram aparência
e comportamento pretendidos, e que abrem com duplo-clique. **Não são código de produção para
copiar.** A tarefa é **recriar estas telas no ambiente do codebase alvo**:

| Alvo real | Valor |
|---|---|
| Stack | Laravel 13.6 + UltimatePOS v6 · Inertia + React 18 + TypeScript + Tailwind 4 |
| Shell | `resources/js/Layouts/AppShellV2.tsx` (sidebar **preta dark-fixo** — UI-0023) |
| Componentes | `resources/js/Components/shared/*` + `resources/js/Components/ui/*` (shadcn `new-york`, base `slate`) |
| Módulo | `Modules/Manufacturing/` — **já existe**, com controllers, services, entities, permissões e views Blade |
| Ícones | `lucide-react@^0.460` |
| Idioma | PT-BR em toda copy visível; chaves de dado e `data-*` em inglês |

**Fidelidade:** hi-fi em layout, comportamento, cálculo e copy. Contraste **medido** (§11).
Não medido: Lighthouse, axe, leitor de tela, zoom 200%.

### O que este pacote NÃO resolve

- **Não é backend.** O protótipo calcula custo na tela para dar retorno imediato; a autoridade é
  o servidor (§9). Nenhum número do protótipo é fonte.
- **Não decide migração.** As rotas Blade legacy continuam de pé; o que este pacote define é a
  camada Inertia. Coexistência é decisão do §15, não sua.
- **Não cobre estoque nem fiscal.** Finalizar uma produção movimenta estoque; o comportamento
  dessa movimentação é do módulo de Estoque e **não está especificado aqui**.
- **Não cobre a fila de chão de fábrica** (OS/produção da gráfica). Os botões "Fila de produção"
  são pontes de navegação, não escopo.
- **Não traz dado real.** `manufacturing-data.jsx` é cena de mock (8 receitas, 22 insumos,
  6 ordens) escolhida para revelar caso de borda, não para semear banco.

---

## §2 · O que a tela faz

O módulo responde a uma pergunta que hoje não tem tela no oimpresso: **quanto custa produzir, com
o preço de insumo de hoje.** Uma receita (ficha técnica) descreve o que entra num produto
fabricado — substrato, tinta, acabamento, estrutura, peça, estampa. O custo dessa receita não é
digitado: é **lido a cada leitura** a partir do preço de compra atual de cada insumo. Uma nota de
compra lançada muda o custo de todas as receitas que usam aquele item, e a tela mostra por
quanto.

Sobre isso vêm três consequências operacionais: qual receita está com **margem magra** (preço de
venda desatualizado), qual está com **desperdício alto** (encaixe/plotagem ruim), e qual insumo
**concentra o custo** — o que sobe se o fornecedor reajustar.

**Personas** `[FECHADA]`:

| Persona | Quem é | O que faz aqui | Frequência |
|---|---|---|---|
| **Larissa · balcão/orçamento** | forma preço e responde cliente | consulta custo unitário e margem antes de orçar | muitas vezes ao dia |
| **Wagner · dono** | decide preço e compra | vê margem magra, desperdício e impacto de insumo | semanal |
| **Eliana · produção** | executa o lote | imprime a **via de produção** (sem custo) e lança a ordem | por lote |

**A regra que governa tudo:**

1. **`[T0]` Isolamento multi-tenant é Tier 0.** Nenhuma query sem `business_id`. Em Manufacturing
   o `business_id` **não é coluna da receita** — vem pela cadeia `mfg_recipes.variation_id →
   variations.product_id → products.business_id` `[DS]` (`Modules/Manufacturing/Services/RecipeBomService.php`
   L50-58). Não existe global scope aqui; o JOIN é obrigatório.
2. **`[V0]` Dinheiro exibido é derivado, nunca digitado.** Todo número de custo desta tela sai de
   uma fórmula sobre `variations.dpp_inc_tax`. Ver §16.
3. **Quem não tem permissão não vê o botão** — nunca vê o botão desabilitado `[TELA]`
   (`manufacturing-producao.jsx`, card "Permissões").

---

## §3 · Layout

### 3.1 · Grade da consulta (aba Receitas)

```
┌────────────────────────────────────────────────────────────────────────────┐
│ PageHeader  "Manufacturing"                        [+ Nova receita]        │  h≥44px
│ 8 receitas · 6 ordens de produção · custo recalculado …                    │
├────────────────────────────────────────────────────────────────────────────┤
│ Receitas ⁸ │ Insumos │ Ordens de produção ⁶·¹ │ Relatório │ Configurações  │  abas
├────────────────────────────────────────────────────────────────────────────┤
│ ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐               │
│ │ CUSTO MÉD. │ │ MARGEM<45% │ │ DESPERD≥8% │ │ PRODUÇÃO   │  4 KPI · 2 são │  grid 4×1fr
│ │ R$ 64,08   │ │ 1          │ │ 2          │ │ 5          │  filtro         │  gap 10
│ └────────────┘ └────────────┘ └────────────┘ └────────────┘               │
├────────────────────────────────────────────────────────────────────────────┤
│ [🔍 Buscar receita…  (tecla /)]   Todas · Comunicação visual · Têxtil · …  │  toolbar
├────────────────────────────────────────────────────────────────────────────┤
│ ☐ │ RECEITA        │ CATEGORIA │ QTD │ CUSTO TOT │ CUSTO UN │ VENDA │ MARG │  thead sticky
│ ☐ │ Banner lona…   │ Com.vis.  │14,40│ R$ 148,72 │ R$ 14,87 │ 62,00 │ 76%  │  linha 44px
│ …10 por página                                                             │
├────────────────────────────────────────────────────────────────────────────┤
│ 1–10 de 8            ‹ 1 2 ›                                               │  paginação
└────────────────────────────────────────────────────────────────────────────┘
   ▲ seleção acende a BulkBar sticky no rodapé (imprimir fichas · atualizar preço)
```

Medidas `[TELA]` — todas em `design/04-modulos/manufacturing/css/manufacturing.css`:

| Elemento | Valor | Onde |
|---|---|---|
| Altura de linha da tabela | `min-height: 44px` | `.mfg-tr` |
| Cabeçalho da tabela | `min-height: 34px`, `position: sticky; top: 0` | `.mfg-thead` |
| Largura mínima da tabela | `960px` receitas · `1100px` ordens · `900px` insumos · `940px` relatório | `.mfg-table[.op/.ins/.rep]` |
| Grade de colunas (receitas) | `34px minmax(240px,2.2fr) 1fr 108px 120px 118px 96px 92px` | `.mfg-tr` |
| Padding horizontal do módulo | `20px` | `.mfg-tabs`, `.mfg-bar`, `.mfg-tablewrap` |
| Drawer | `width: min(680px, 94vw)`, colado à direita | `.mfg-drw` |
| Modal | `width: min(520px, 94vw)`, `max-height: 88vh`, raio `12px` | `.mfg-modal` |
| Modal de confirmação | `width: min(400px, 92vw)` | `.mfg-modal.sm` |
| Editor: coluna lateral | `320px` fixa, `sticky top: 0` | `.mfg-ed-cols`, `.mfg-ed-side` |
| Campo | altura `32px`, raio `6px`; campo numérico em linha `26px` | `.mfg-inp`, `.mfg-inp.num` |

**Breakpoints** `[TELA]` `[FECHADA]`: um só — `max-width: 1080px` colapsa o editor de duas
colunas para uma (`.mfg-ed-cols`). Abaixo disso as tabelas rolam na horizontal dentro de
`.mfg-tablewrap` (`overflow: auto`); a plataforma alvo é **cockpit desktop ≥1280px**, mesma
declaração do charter atual `[DS]` (`resources/js/Pages/Manufacturing/Index.charter.md` L28).

### 3.2 · Anatomia do bloco repetido (grupo de ingredientes)

```
┌ .mfg-grp ─────────────────────────────────────────────────────┐
│ Substrato          ⁽¹⁾              R$ 95,68                  │  cabeçalho: nome · contagem · subtotal
├───────────────────────────────────────────────────────────────┤
│ INGREDIENTE   │ QUANTIDADE │ UNIDADE │ CUSTO UNIT. │ SUBTOTAL │  cabeçalho de coluna
│ Lona brilho…  │ [ 10,400 ] │ [m² ▾]  │ R$ 9,20/m²  │ R$ 95,68 │  linha editável
│ INS-004                                                    ✕  │
├───────────────────────────────────────────────────────────────┤
│ ＋ Ingrediente em Substrato                                    │  ação do grupo
└───────────────────────────────────────────────────────────────┘
```

O subtotal do grupo é **derivado** (`Σ quantidade × preço × multiplicador`), nunca digitado.

---

## §4 · Passo a passo da tela

### 4.1 · Abas `[FECHADA]`

| Aba | Id | Contador no rótulo | Some quando |
|---|---|---|---|
| Receitas | `receitas` | nº de receitas | nunca |
| Insumos | `insumos` | — | nunca |
| Ordens de produção | `producao` | nº de ordens + `N rasc.` se houver rascunho | sem permissão `manufacturing.access_production` |
| Relatório | `relatorio` | — | nunca |
| Configurações | `config` | — | nunca |

Aba ativa: `TabBar` do DS (`_ds_bundle.js` L6605). Sublinhado `2px solid var(--accent)`,
rótulo `var(--text)` peso 600 — **e fundo `color-mix(in oklch, var(--accent-soft) 50%, transparent)`**
(L6645-6648). `[DS]`

⚠️ **Tensão medida, aplicada como está.** Até 2026-09-07 esta ficha dizia "Nunca pill-active",
citando o guia do DS (§Layout rules: "Tabs underline-active in primary, never pill-active"). O
componente que renderiza a aba pinta o fundo. **O componente vence a prosa do guia**: a tela usa
o `TabBar` sem alteração e a contradição foi para `pauta-design-system.md`. Não "corrigir" o
fundo aqui, nem por dentro do componente.

### 4.2 · Consulta de receitas

| Elemento | Detalhe |
|---|---|
| Busca | um campo, casa em nome + SKU + categoria + subcategoria (`.toLowerCase().includes`). Atalho `/` foca; `/` digitado dentro de campo não é atalho. Placeholder: `Buscar receita por nome, SKU, categoria…  (tecla /)` |
| Chips de categoria | derivados das categorias presentes + `Todas` na frente. Seleção única `[TELA]` |
| KPI 1 · Custo médio / unidade | leitura, **não filtra**. Média aritmética do custo unitário das receitas exibidas |
| KPI 2 · Margem abaixo de 45% | **filtro liga/desliga**; mostra só receitas com margem `< 45` |
| KPI 3 · Desperdício ≥ 8% | **filtro liga/desliga**; mostra só receitas com `waste ≥ 8` |
| KPI 4 · Produção do mês | leitura. Conta ordens finalizadas; sublinha nº de rascunhos |
| Ordenação | clique no cabeçalho alterna asc/desc; indicador `⇵` inativo, `↑`/`↓` ativo; ordenar volta para a página 1 |
| Paginação | 10 por página, só aparece com mais de 10 resultados; mostra `1–10 de 24` |
| Seleção | checkbox por linha + "selecionar todas" (todas as **filtradas**, não só as visíveis) |
| Clique na linha | abre o drawer da receita. Clique no checkbox **não** abre (`stopPropagation`) |
| Vazio | `Nenhuma receita encontrada` + `Ajuste a busca, troque a categoria ou limpe o filtro de KPI.` |

**Coluna Quantidade — a declaração obrigatória:** quando a receita tem sub-unidade de saída
(`subUn`), a coluna mostra a quantidade **na sub-unidade** (`qtdLiq × subFator`) com o rótulo da
sub-unidade ao lado (ex. `14,40 m linear` para um lote de `9,60 m²`). Nunca mostrar número
convertido sem o rótulo da unidade — a linha declara em que unidade está falando.

**Barra de seleção (BulkBar)** — sticky no rodapé, `[FECHADA]` com 3 ações:
`Limpar` · `Imprimir fichas` (lote) · `Atualizar preço de venda do produto` (só existe quando a
configuração `enable_updating_product_price` está ligada **e** a permissão de editar está presente).

### 4.3 · Drawer da receita (leitura)

Cabeçalho: nome · `SKU · categoria / subcategoria · rende X un · atualizado <quando>`.
Corpo: um bloco por grupo de ingredientes com subtotal, depois o quadro de custo:

```
Ingredientes                                     R$ 95,68
Custo extra (18% sobre ingredientes | R$ X por m² produzido | valor fixo)   R$ 17,22
Desperdício            4% · rende 9,60 de 10,00 m²
Sub-unidade de saída   14,40 m linear
────────────────────────────────────────────────────────
Custo por m²                                     R$ 14,87   ← 15px 600 em --accent
Preço de venda atual                             R$ 62,00
Margem                                           76,0%
```

Rodapé `[FECHADA]`: `Fechar` · `Ficha com custo` · `Via de produção` · `Produzir` (permissão
`prod`) · `Editar ingredientes` (permissão `editar`).

Nota obrigatória no corpo, texto verbatim `[TELA]`:
> O custo é recalculado a cada leitura a partir do preço atual dos ingredientes — a receita não
> guarda valor congelado. Uma compra de insumo salva em **Compras** muda este número.

### 4.4 · Aba Insumos (impacto reverso)

Tabela de insumos com: nome, código, custo/unidade, estoque, **nº de receitas que o usam** e
**maior peso** (% do custo total da receita em que ele mais pesa). Insumo sem receita mostra
`—` e `sem receita`, e **não é clicável**.

Clique abre drawer com um **simulador**: `input[type=range]` de `-30%` a `+60%`, passo `5`,
default `+10%`. Para cada receita afetada: consumo convertido para a unidade base, custo unitário
atual, custo com a variação, e a margem resultante. Nota obrigatória:
> A conta usa o consumo da receita já convertido para a unidade base. Uma nota lançada em
> **Compras** aplica a variação de verdade.

### 4.5 · Aba Ordens de produção

Filtros `[FECHADA]`: Local (`Todos` + locais do business) · De · Até · `Só finalizadas`.
Colunas `[FECHADA]`: Data · Referência · Local · Produto (com `N ingredientes · quem lançou`) ·
Qtd · Custo total · Custo unit. · Situação (`Finalizada` / `Rascunho`).

Duas marcas que **não podem ser omitidas**:
- Ordem finalizada mostra o sufixo `fix` ao lado do custo total, com `title="custo congelado na
  data da produção"`.
- Rodapé da tabela: `N ordens · custo do período R$ X · ordens finalizadas mostram o custo
  congelado na data`.

### 4.6 · Aba Relatório

Período (De/Até) + `Só finalizadas` (default **ligado**). Agrupa por produto: ordens, quantidade,
custo total, custo médio, e `% do período` com barra mini. Ordenado por custo desc.
Rodapé: `Custo de produção do período R$ X · lançado como entrada de estoque no Financeiro`.

### 4.7 · Aba Configurações

Três cartões `[FECHADA]`:

1. **Configurações do módulo** — prefixo da referência (texto), `Bloquear edição da quantidade de
   ingrediente` (switch), `Atualizar preço do produto ao finalizar produção` (switch), rodapé com
   a versão do módulo e botão `Atualizar` **desabilitado enquanto nada mudou**.
2. **Permissões (simulação)** — 4 chips que ligam/desligam `ver`, `criar`, `editar`, `prod`. É
   ferramenta do protótipo para conferir os estados; **no app real este cartão não existe** — as
   permissões vêm do backend. Contorno declarado.
3. **Integrações** — três linhas de texto com link: Produtos, Compras, Fila de produção/OS.

---

## §5 · Editor de ingredientes (a tela dentro da tela)

Ocupa o corpo do módulo inteiro (não é overlay), com trilha de volta
`Receitas / <nome>` e meta `SKU · N ingredientes em M grupos`.

**Coluna principal:** os grupos, cada um com cabeçalho (nome, contagem, subtotal, remover) e
linhas de ingrediente em 6 colunas: Ingrediente (nome + `SKU` + equivalência quando há
sub-unidade) · Quantidade (`input number`, passo `0.001`) · Unidade (`select` de sub-unidades
quando o insumo tem; senão a unidade base como texto) · Custo unit. · Subtotal · remover.
Cada grupo tem `＋ Ingrediente em <grupo>`, que abre a **busca de insumo** (máx. 7 resultados,
`Enter` escolhe o primeiro, `esc` fecha). Abaixo: `＋ Novo grupo de ingredientes`, que oferece só
os grupos ainda não usados.

**Coluna lateral (320px, sticky):** Nome · Qtd produzida + Unidade · Sub-unidade de saída + Fator
(Fator desabilitado enquanto não houver sub-unidade) · Desperdício (%) com dica `rende X un de Y`
· Custo extra (tipo + valor, com o rótulo do valor mudando junto: `Percentual` / `R$ / unidade` /
`Valor (R$)`) · Preço de venda com dica `margem X%` · quadro **Custo ao vivo**.

**Regras numeradas** `[FECHADA]`:

1. Salvar exige **pelo menos 1 ingrediente** (`nIng === 0` desabilita o botão).
2. Com `disable_editing_ingredient_qty` ligado, a quantidade vira texto — em **todos** os lugares
   (editor e ordem de produção). A tela avisa: `Edição de quantidade de ingrediente está bloqueada
   em Configurações.`
3. Sem permissão de editar: campos desabilitados + aviso `Sua permissão é apenas de leitura
   (mfg.receita: ver).` — a copy final deve citar a permissão real: `manufacturing.access_recipe`.
4. Trocar a sub-unidade de uma linha troca `mult` junto (o multiplicador vem da sub-unidade, não é
   digitado).
5. O editor trabalha numa **cópia** da receita (`JSON.parse(JSON.stringify(recipe))`); cancelar
   descarta, salvar devolve o objeto inteiro.
6. Excluir receita só pelo editor ou pelo drawer, sempre com confirmação (§4.2 modal `.sm`), com o
   texto que diz o que se perde: ficha + N ingredientes, e que **ordens já lançadas continuam com
   o custo registrado**.

---

## §6 · Ordem de produção

Formulário em duas colunas. Esquerda: **consumo de ingredientes** calculado
(proporcional: `fator = qtd da ordem ÷ qtd da receita`), cada linha editável (override) e com o
estoque ao lado; linha com consumo maior que o estoque recebe fundo `--warn 7%` e o número em
`--neg`. Direita: Referência (prefixo das configurações) · Data · Local · Receita · Quantidade a
produzir · quadro de custo · o switch `Finalizar`.

**Regras numeradas** `[FECHADA]`:

1. Trocar receita ou quantidade **zera os overrides de consumo** (`consumo: null`) — override é
   sobre um consumo calculado; mudou a base, mudou o consumo.
2. Estoque insuficiente **não bloqueia**: avisa `Estoque insuficiente em N insumos — finalizar vai
   deixar saldo negativo.` com ponte para Compras. Quem decide é o operador.
3. **Rascunho não movimenta estoque.** Só `Finalizar` dá entrada no produto e baixa os
   ingredientes. O botão diz o que vai acontecer: `Salvar rascunho` / `Salvar e finalizar`.
4. **Ao finalizar, o custo do dia é congelado** (`custoSnap`). Rascunho nunca tem custo congelado.
   A partir daí a ordem mostra os dois números: `Custo congelado na produção` e `Mesma receita
   hoje`, com a variação percentual. Dois números do mesmo fato precisam fechar — e quando não
   fecham, a linha explica por quê.
5. Com `enable_updating_product_price` ligado, finalizar propaga o custo unitário para a ficha do
   produto; a tela declara isso **antes** de salvar: `Ao finalizar, o preço de custo do produto
   será atualizado (R$ X)`.
6. Salvar exige permissão de criar e receita com ingredientes.

---

## §7 · Modelo de custo (a regra de negócio profunda)

Implementado em `manufacturing-data.jsx` → `custos()` e `consumoOP()`, espelhando
`[DS]` `RecipeBomService::calculateCost()` (L84-116) e `ManufacturingUtil::getRecipeTotal()`
(L228-250). **Fórmulas — reproduzir exatamente:**

```
ingredientes = Σ ( quantidade_da_linha × variations.dpp_inc_tax × multiplicador_da_sub_unidade )

custo_extra  = production_cost_type = 'percentage' → ingredientes × extra_cost / 100
             = production_cost_type = 'per_unit'   → extra_cost × total_quantity
             = production_cost_type = 'fixed'      → extra_cost

custo_total  = ingredientes + custo_extra
rendimento   = total_quantity − total_quantity × waste_percent / 100
custo_unit   = total_quantity > 0 ? custo_total / total_quantity : 0
margem_%     = final_price > 0 ? (final_price − custo_unit) / final_price × 100 : 0
```

Sete pontos que um agente costuma errar aqui:

1. **`custo_unit` divide por `total_quantity`, NÃO pelo rendimento.** O desperdício aparece como
   rendimento declarado, e não embutido no custo unitário. É assim no legado; manter.
2. **`per_unit` multiplica pela quantidade da RECEITA** (`total_quantity`), não pela quantidade da
   ordem — na receita. Na **ordem**, `per_unit` multiplica pela quantidade da ordem (`op.qtd`);
   `fixed` entra rateado pelo fator (`extra × fator`); `percentage` incide sobre o consumo da
   ordem. São três contas diferentes: ver `consumoOP()`.
3. **Divisão por zero é resultado zero**, nunca `NaN`, nunca `Infinity` — em `custo_unit`,
   `margem` e `% do período`.
4. **Multiplicador de sub-unidade multiplica o custo da linha**, e a linha mostra a equivalência
   na unidade base (`0,044 galão (5 L) · equivale a 0,220 L`).
5. **Margem é sobre o preço de venda** (`(venda − custo) / venda`), não sobre o custo. Faixas de
   cor `[TELA]` `[FECHADA]`: `≥ 55%` positivo · `45–54,9%` alerta · `< 45%` negativo.
6. **Nada é arredondado no meio da conta.** Arredondamento é de apresentação (2 casas, `pt-BR`).
   A única gravação arredondada é o `custoSnap` da ordem finalizada (`toFixed(2)`).
7. **Atualizar preço de venda a partir do custo usa `custo_unit × 2`** `[TELA]` — é um
   *placeholder* do protótipo. **Não implemente esse fator 2 em produção**: a regra real de markup
   não foi decidida (§18, pendência 1).

---

## §8 · Ficha técnica impressa (folha de prova PT-07)

Duas variantes da mesma folha, uma decisão de negócio cada:

| Variante | Quando | O que muda |
|---|---|---|
| **Ficha com custo** | orçamento, conferência | mostra custo unitário, subtotais e o quadro de total |
| **Via de produção** | bancada / chão de fábrica | **sem nenhum valor de compra**; a coluna de custo vira caixa de conferência (`Separado ☐`) e o destaque passa a ser `N itens · separar tudo na bancada` |

Anatomia (A4, margem 0, conteúdo `20mm 16mm 16mm`): marcas de corte nos 4 cantos ·
mira de registro · cabeçalho com eyebrow `Office Impresso · Manufacturing` (+ ` · via de
produção`) · carimbo (Receita / Produto / Emitida em) · **cotas** com linha de medida (lote,
rendimento líquido, sub-unidade, custo) · tabela agrupada por grupo de ingredientes ·
assinaturas (Produção / Conferido por) · tira CMYK + escada de cinza · rodapé com SKU.

Impressão em **lote**: a barra de seleção imprime N fichas de uma vez, uma folha por receita.
Mecanismo: portal no `<body>` + `@media print` que esconde `body > *` e mostra só
`.mfg-print-host`; `window.print()` 120ms após montar, `afterprint` fecha.
Texto obrigatório no pé da folha: `Ficha de uso interno — não é documento fiscal.`

---

## §9 · O que o servidor precisa garantir

| Garantia | Por quê |
|---|---|
| Todo cálculo de custo recalculado **no servidor** antes de gravar | O protótipo calcula para dar retorno imediato. Se o cliente mandar `final_total`, um POST forjado grava custo arbitrário numa `transaction` `type=production_purchase` — que é lançamento contábil. |
| `business_id` pela cadeia `mfg_recipes → variations → products` em **toda** query | `[T0]` ADR 0093. Manufacturing legacy **não tem global scope** — o JOIN é a única barreira (`RecipeBomService::resolveBom` L50-58). Sem ele, receita de outro tenant aparece na lista. |
| Permissão verificada no servidor, não só na UI | Padrão do módulo `[DS]`: `auth()->user()->can('superadmin') \|\| moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')` **e** a permissão específica (`RecipeController` L66, L152, L286…). Esconder o botão é UX; a barreira é o gate. |
| Finalizar produção é **transação atômica** (ordem + entrada do produto + baixa dos ingredientes) | Meio caminho deixa estoque mentindo. Rascunho→finalizada é a única transição que move estoque (§6.3). |
| `custoSnap` gravado no ato do fechamento, do lado do servidor | O congelado é prova histórica. Se vier do cliente, o histórico é editável pelo cliente. |
| Recalcular custo **na leitura**, nunca servir `ingredients_cost` como verdade | A coluna `mfg_recipes.ingredients_cost` existe e envelhece: o preço do insumo muda sem passar pela receita. O legado já lê dinâmico (`getRecipeTotal($row)`); manter. |
| Rejeitar quantidade `≤ 0` e receita sem ingrediente | Regra 1 de §5 no servidor também. |
| Validar `production_cost_type` contra `[FECHADA] {fixed, percentage, per_unit}` | Valor fora da lista muda a fórmula em silêncio. Coluna default `'percentage'` `[DS]` (migration `2020_08_19_103831`). |
| Numeração de referência gerada no servidor, com o prefixo do business | Prefixo é configuração do tenant (`ref_no_prefix`); duas abas abertas geram a mesma referência se o cliente numerar. |
| Sub-unidade validada como sub-unidade **daquele** produto | `base_unit_multiplier` de outra unidade multiplica o custo por um número arbitrário. |

---

## §10 · Design tokens

**Nenhum token novo foi criado.** Tudo vem do bundle do DS (`.cockpit`).

| Uso | Token |
|---|---|
| Acento (roxo, hue 295) | `--accent` · `--accent-2` · `--accent-soft` · `--accent-fg` |
| Superfícies | `--bg` · `--bg-2` · `--surface` · `--border` |
| Texto | `--text` · `--text-dim` · `--text-mute` |
| Semântico | `--pos` (margem ok) · `--warn` (alerta/rascunho/falta) · `--neg` (margem magra/erro) |
| Tipografia | `--font-sans` (IBM Plex Sans) · `--font-mono` (IBM Plex Mono) |
| Raio | `--radius-sm` 6px (campo, botão) · 8px (cartão, tabela, grupo) · 10px (lateral do editor, cartão de config) · 12px (modal) |

Regras aplicadas `[TELA]`:

- Cor **sempre** por token ou `color-mix(in oklch, var(--token) N%, transparent)`. Tinta de pílula
  e de linha usa 7–8%; borda de pílula 30%; anel de KPI selecionado 35%.
- Espaçamento na grade 4/8 (exceções medidas: `7px`, `9px`, `11px`, `13px` em padding de campo e
  KPI — herdadas do protótipo, listadas no CHECKLIST Anexo A).
- Número **sempre** `font-variant-numeric: tabular-nums` + `--font-mono`.
- Uppercase tracked (`.07em`–`.09em`, 10px) só em rótulo de coluna, de campo e eyebrow de seção.

**Três exceções de cor crua, todas declaradas** (nenhuma é decorativa):

1. ~~`rgba(0,0,0,.28 | .32 | .40 | .42)` em 4 declarações~~ — **zeradas na onda A**
   (2026-09-08). As quatro saíram junto com o markup caseiro: scrim e sombra agora são do
   `Drawer` (`oklch(0.15 0 0 / 0.45)`, L3815), do `Modal` (mesmo scrim + `0 24px 60px -12px
   rgba(0,0,0,.45)`, L5120), do `BulkBar` (`0 20px 40px -12px rgba(0,0,0,.45)`, L2156) e do
   `Toast` (`0 8px 24px -6px rgba(0,0,0,.35)`, L6912). São valores **do componente**, citados,
   não escritos pela tela. O DS continua sem token de scrim e de sombra elevada — o item segue
   na pauta, mas já não é exceção desta tela. **Conferir:** `grep -n "rgba(0,0,0" manufacturing-page.css`
   volta 0.
2. Bloco `@media print` inteiro em 11 cinzas (`#fff` `#000` `#111` `#555` `#666` `#777` `#999`
   `#bbb` `#ccc` `#ddd` `#f0eeeb`): **papel não tem tema.** O cockpit não publica paleta de
   impressão. → ADR `0413`.
3. `#00AEEF #EC008C #FFF200 #231F20` na tira do rodapé da ficha: são as **quatro tintas de
   processo** (ciano, magenta, amarelo, preto) — valor de tinta, não cor de tema. É o mesmo
   conjunto do primitivo `ProofStrip kind="cmyk"` do DS.

---

## §11 · Acessibilidade — medido, não presumido

Contraste calculado (WCAG 2.1, sRGB, pares reais desta tela, nos dois temas — tokens de
`_ds/…/colors_and_type.css` L274-340):

| Par | Claro | Escuro | Mínimo | Veredito |
|---|---|---|---|---|
| `--text` sobre `--surface` | **17,32:1** | **11,42:1** | 4,5 | ✅ |
| `--text-dim` sobre `--surface` | **6,00:1** | **5,49:1** | 4,5 | ✅ |
| `--text-dim` sobre `--bg-2` | **5,42:1** | **6,80:1** | 4,5 | ✅ |
| `--text-mute` sobre `--surface` | **3,24:1** | **3,18:1** | 4,5 | ❌ |
| `--text-mute` sobre `--bg-2` | **2,92:1** | **3,94:1** | 4,5 | ❌ |
| `--accent` **como texto** sobre `--surface` | **5,15:1** | **2,64:1** | 4,5 | ❌ no escuro |
| `--accent` como texto sobre `--bg-2` | **4,66:1** | **3,27:1** | 4,5 | ❌ no escuro |
| `--warn` como texto sobre `--surface` | **4,40:1** | 7,17:1 | 4,5 | ❌ no claro (marginal) |
| `--pos` / `--neg` como texto sobre `--surface` | 5,67 / 5,32 | 6,24 / 5,12 | 4,5 | ✅ |
| `--accent-fg` (#fff) sobre `--accent` (botão primário) | **5,15:1** | 5,15:1 | 4,5 | ✅ |

Consequências para a reimplementação (obrigatórias):

1. **Não usar `--text-mute` em texto pequeno.** Rótulo de coluna, rótulo de campo, SKU, unidade e
   meta de trilha devem sair em `--text-dim`. É troca de token, não cor nova. Ver ADR `0410`.
2. **`--accent` não serve como cor de texto no tema escuro.** Link, valor destacado, aba ativa e
   chip ativo precisam do tom claro do acento no escuro. Ver ADR `0411`.
3. Foco visível: a camada transversal já entrega anel universal `outline: 2px solid var(--accent);
   outline-offset: 1px` + halo `--accent-soft` `[DS]` (`02-shell/css/otimiza-ondas.css` L14).
   No alvo, o canon é `focus-visible:ring-[3px] ring-ring/50`. **Não remover.**
4. Alvo de toque: linha de tabela tem 44px; em `pointer: coarse` a camada transversal força
   `min-height: 44px` em botão/select/checkbox. O `input.num` de 26px **fica abaixo de 44px em
   desktop** — aceito para a persona teclado+mouse do balcão, declarado.
5. Diálogos: `role="dialog"` + **`aria-modal="true"`** + `aria-label`, `esc` fecha, **foco
   aprisionado e devolvido ao elemento anterior** — os cinco overlays são `Drawer` (L3763-3801)
   e `Modal` (L5049-5087) do DS desde a onda A. Resolve o item 4 de §18. No alvo, o equivalente
   é `ui/sheet.tsx` / `ui/alert-dialog.tsx` (§15.3).
   **Conferir:** abrir a gaveta de insumo (aba Insumos), apertar `Tab` dez vezes — o foco nunca
   sai do painel; `Esc` fecha. Antes da onda A o `Esc` desta gaveta **não funcionava**: o
   listener era global, vivia em `manufacturing-page.jsx` e não conhecia o estado `sku`.

**Não verificado:** Lighthouse, axe, leitor de tela (NVDA/VoiceOver), zoom 200%, navegação
completa por teclado no editor, contraste dos cinzas da folha impressa em papel real.

---

## §12 · Defeitos do design system encontrados

Quatro, todos com ADR aberta em `design/adr/` — nenhum foi "corrigido" por dentro da tela:

| ADR | Defeito | Onde dói | Contorno em pé |
|---|---|---|---|
| `0410` | `--text-mute` reprova AA em texto pequeno nos dois temas (3,24 / 2,92 / 3,18 / 3,94) | rótulo de coluna, de campo, SKU, unidade | trocar por `--text-dim` na tela |
| `0411` | `--accent` não é reescrito no tema escuro; como **texto** mede 2,64:1 | link, aba ativa, chip ativo, valor destacado | usar `--accent` só em fundo/borda no escuro |
| `0412` | `StatusBadge` não tem `kind` para produção; `DataTable` exige paginator do servidor; `PageHeaderTabs` navega por `href` e a tela usa abas de estado | 3 componentes compartilhados | tabela e abas locais no protótipo |
| `0413` | O cockpit não publica paleta de impressão — nenhum token de papel/tinta | toda a folha PT-07 | cinzas literais no bloco `@media print` |

Regra que vale aqui: **a mesma correção aparecendo 3× em telas diferentes deixa de ser correção de
tela e passa a ser defeito de sistema.** `0410` é o terceiro registro do mesmo token — ver
`contexto/pauta-design-system.md`.

---

## §13 · Estado (React)

| Grupo | Variáveis |
|---|---|
| Navegação | `aba` · `tela` (`{tipo:'receita-edit'\|'op-form', id}`) · `initialView` (prop) |
| Dados | `recipes` · `producoes` · `settings` · `perms` |
| Consulta de receitas | `q` · `cat` · `kpi` · `ord {k,dir}` · `pag` · `sel[]` |
| Overlays | `openId` (drawer da receita) · `opAberta` (drawer da ordem) · `novaOpen` · `confirma` · `imprimir {itens, semCusto}` |
| Retorno | `toast` (2600ms) |
| Editor (local) | `r` (cópia da receita) · `addIn` (grupo recebendo ingrediente) · `novoGrupo` |
| Ordem (local) | `op` (a ordem sendo montada) |
| Insumos (local) | `q` · `sku` (selecionado) · `pct` (variação simulada) |

**Derivados — NUNCA em estado:** `linhas` (receita + custos) · `filtradas` · `visiveis` ·
`nPags` · `pagina` · `magra` · `perda` · `custoMed` · `allSel` · `aberta` · `c` (custos da
receita aberta) · `falta` (insumos sem estoque) · `totalPeriodo` · `dirty` (config alterada).

**Persistência:** nenhuma. A tela não escreve `localStorage` `[TELA]`. Se for preciso lembrar
filtro ou densidade no alvo, a chave é `oimpresso.manufacturing.<coisa>` e a chave de rascunho é
`{tenant}.{user}` — nunca só `user`.

---

## §14 · Arquivos

| Arquivo | Papel |
|---|---|
| `design/Fabricacao - Guia de Producao.html` | **Manifesto**: cascata de CSS + ordem de dependência do JS. Zero estilo, zero componente, zero dado dentro dele |
| `design/manufacturing-data.jsx` | Fonte única de dado e **de cálculo** (`custos`, `consumoOP`, `usosDoInsumo`, `fmt`, `num`, `fmtDate`) → `window.MFG` |
| `design/icons.jsx` | Primitivo de ícone → `window.I`. Espelho de 22 glifos; o alvo usa lucide inteiro (contorno) |
| `design/manufacturing-page.jsx` | A tela: abas, KPIs, consulta, seleção, drawer da receita → `window.ManufacturingPage` |
| `design/manufacturing-recipe.jsx` | Modal "Nova receita", busca de insumo, editor de ingredientes, campo (`MfgCampo`) |
| `design/manufacturing-producao.jsx` | Lista de ordens, formulário, drawer da ordem, relatório, configurações |
| `design/manufacturing-insumos.jsx` | Impacto reverso do insumo + simulador de variação |
| `design/manufacturing-print.jsx` | Folha de prova PT-07 (com custo / via de produção), impressão em lote |
| `design/manufacturing-app.jsx` | **Contorno do pacote**: shell mínimo, troca de tema, stub de `__go`, único ponto de mount. Não é portado |
| `design/styles.css` | Camada 0 — folha do shell cockpit, cópia verbatim. Não editar |
| `design/_ds/…/colors_and_type.css` | Camada 0 — tokens do DS, cópia verbatim. Não editar |
| `design/02-shell/css/otimiza-ondas.css` | Camada 2 — thead fixo, tabular-nums, foco visível, toque 44px. Cópia verbatim |
| `design/02-shell/css/guia-standalone.css` | Camada 2 — **só do pacote**: moldura para abrir fora do cockpit |
| `design/04-modulos/manufacturing/css/manufacturing.css` | Camada 4 — todo o CSS da família (`.mfg-*`), cópia verbatim |
| `design/adr/041*.md` | Os 4 defeitos de DS, com medição |
| `design/LAUDO-conferencia-fabricacao.md` | Conferência datada: veredito, placar, achados, contraste |
| `design/CHECKLIST-15D-fabricacao.md` | Score ponderado por persona + regras binárias |
| `contexto/SDD-tela-fabricacao-v1.0.md` | De onde veio: casos de uso, modelo de dados, dívidas |
| `contexto/manual-escrita-para-agente.md` | As leis de escrita que este pacote obedece |
| `contexto/pauta-design-system.md` | Propostas e defeitos de DS acumulados do produto |

### Como abrir

Abrir `design/Fabricacao - Guia de Producao.html` no navegador (duplo-clique). Precisa de internet
na primeira carga: React 18.3.1, ReactDOM, Babel 7.29.0 e as fontes IBM Plex vêm de CDN com
`integrity` SRI. Nada de `npm install`, nada de servidor.

- O tema (claro/escuro) troca na barra do topo — é do pacote, não da tela.
- `/` foca a busca; `esc` fecha overlay.
- **Imprimir:** `Ctrl/Cmd+P` a partir de "Ficha com custo" ou "Via de produção" — a folha PT-07 é
  A4 e sai sem o chrome.
- O bundle de componentes do DS (`_ds_bundle.js`) **é obrigatório** desde a onda A: a família lê
  `window.OfficeImpressoPontoWR2DesignSystem_019dd0` em todos os cinco arquivos de tela. No
  protótipo ele já é carregado por `oimpresso.com.html`, do espelho único
  `_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js` (a pasta `019dd02f` e o alias
  que ela carregava foram apagados em 21/09/2026). Cada arquivo resolve o namespace em tempo de
  render (`const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}`) e não em tempo
  de carga, porque a família é lazy. **No alvo, nada disso se copia:** o §15.3 mapeia cada componente
  do DS para o arquivo real do repo.

---

## §15 · Diff contra o que já existe no repo

O módulo alvo **já tem uma tela Inertia**. Não descreva o alvo: aplique o diff.

### 15.1 · `resources/js/Pages/Manufacturing/Index.tsx` (315 linhas, rota `/manufacturing/v2/production`)

| Está hoje | Deve ficar | Como conferir |
|---|---|---|
| Título `Produção`, ícone `factory`, descrição "Lista MWART em coexistência…" | Mesma tela passa a ser **a aba "Ordens de produção"** da família Fabricação; título do módulo `Manufacturing`, e a navegação entre as 5 abas fica no header | `/manufacturing/v2/production` mostra as 5 abas de §4.1 com "Ordens de produção" ativa |
| 4 KPIs: Total · Finalizadas · Pendentes · Valor total | Mantidos como estão nesta aba. **Não** trocar pelos KPIs de §4.2 (aqueles são da aba Receitas) | os 4 rótulos continuam idênticos |
| Tabela de 5 colunas (Ref, Data, Local, Total, Status) | 8 colunas de §4.5, com Produto (`nome` + `N ingredientes · quem lançou`), Qtd e Custo unit., e o sufixo `fix` em ordem finalizada | ordem finalizada mostra `fix` com `title="custo congelado na data da produção"` |
| Sem rodapé de total | Rodapé `N ordens · custo do período R$ X · ordens finalizadas mostram o custo congelado na data` | com 2+ ordens no filtro, o rodapé aparece e o total bate com a soma da coluna |
| `<select>` nativo de local com `eslint-disable no-restricted-syntax` (L165) | Manter o `<select>` nativo **ou** migrar para `ui/select.tsx`. **Não** decidir sozinho: item 5 de §18 | — |
| `StatusPill` local (L292-311) com `text-success-fg` / `text-warning-fg` | Trocar por `StatusBadge kind="producao"`, acrescentando o mapping `producao: { rascunho, finalizada }` — o próprio componente autoriza: *"Adicionar novo domínio: estender `mappings` abaixo + commitar"* `[DS]` (`Components/shared/StatusBadge.tsx` L17) | `grep -n "producao:" Components/shared/StatusBadge.tsx` retorna 1 linha; nenhuma pílula com `bg-*` sólido (AP7) |
| Filtros: local + intervalo de data | + `Só finalizadas` como checkbox (hoje só existe como KPI clicável) | marcar o checkbox reduz a lista às finalizadas e a URL ganha `is_final=1` |
| `Index.layout` com `breadcrumbItems=[Manufacturing, Produção]` | Mantido | — |

### 15.2 · Telas que **não existem** e precisam ser criadas

| Nova página | Rota proposta | Conteúdo | Backend que já existe |
|---|---|---|---|
| `Pages/Manufacturing/Recipes.tsx` | `GET /manufacturing/v2/recipe` | §4.2 + §4.3 (consulta + drawer) | `RecipeController@index` (DataTables) + `RecipeBomService::resolveBom/calculateCost` |
| `Pages/Manufacturing/RecipeEdit.tsx` | `GET /manufacturing/v2/recipe/{recipe}/ingredients` | §5 (editor) | `RecipeController@addIngredients`, `@store`, `@update`, `getIngredientRow` |
| `Pages/Manufacturing/Inputs.tsx` | `GET /manufacturing/v2/insumos` | §4.4 (impacto reverso + simulador) | **não existe** — precisa de um método novo no `RecipeBomService` (`usosDoInsumo`) |
| `Pages/Manufacturing/ProductionForm.tsx` | `GET /manufacturing/v2/production/create` e `/{production}/edit` | §6 | `ProductionController@create/@store/@update` |
| `Pages/Manufacturing/Report.tsx` | `GET /manufacturing/v2/report` | §4.6 | `ProductionController@getManufacturingReport` |
| `Pages/Manufacturing/Settings.tsx` | `GET /manufacturing/v2/settings` | §4.7 cartões 1 e 3 (o cartão 2 é do protótipo) | `SettingsController@index/@store` |
| Ficha PT-07 | `GET /manufacturing/v2/recipe/{recipe}/ficha?sem_custo=0\|1` | §8 | **não existe** |

**Proibições deste diff:**

- **Não remover** nenhuma rota Blade legacy de `Modules/Manufacturing/Routes/web.php` (L10-32).
  Coexistência é o combinado do charter atual `[DS]` (`Index.charter.md` L26-30, "Non-Goals").
- **Não** renomear `manufacturing.production.v2.index`.
- **Não** criar tabela nem coluna nova. Todo campo desta tela mapeia para schema existente (§16);
  as duas exceções estão em §18.
- **Não** usar `withoutGlobalScopes` nos services `[DS]` (`Index.charter.md` L33, "Anti-hooks").
- **Não** permitir `UPDATE` direto em `transactions` `[DS]` (mesma seção).
- **Não** atualizar o charter para `live` sem aprovação do Wagner `[DS]` (mesma seção).

### 15.3 · Componentes do alvo a usar (e o que cada um não resolve)

| Precisa de | Use | Ressalva medida |
|---|---|---|
| Cabeçalho da tela | `Components/shared/PageHeader.tsx` (uso em `Pages/Manufacturing/Index.tsx` L118-130) | — |
| Abas do módulo | `Components/shared/PageHeaderTabs.tsx` | `ghosts[]` são `{key, label, href, icon?, badge?}` e navegam por `<Link href>` `[DS]` (L57-77). O protótipo usa aba de **estado** (sem navegação). Como o alvo já expõe uma rota por aba (§15.2), use `href` — e o `badge` cobre os contadores de §4.1 |
| Tabela | `Components/shared/DataTable.tsx` | Exige `pagination: PaginatorShape<T>` **e** `endpoint` `[DS]` (L113-152): paginação e busca são **do servidor**, não do cliente como no protótipo. `meta: {width, align, mono}` por coluna e `rowState` cobrem geometria e estado de linha (L60-77, L129-133) |
| KPI | `Components/shared/KpiCard.tsx` | props em uso: `label`, `value`, `icon`, `tone`, `size="compact"`, `onClick`, `selected` (`Index.tsx` L136-160). Conferir a assinatura no arquivo antes de usar prop nova |
| Vazio | `Components/shared/EmptyState.tsx` | props em uso: `icon`, `variant`, `title`, `description`, `action` (`Index.tsx` L216-240) |
| Seleção em lote | `Components/shared/BulkActionBar.tsx` | conferir assinatura; §4.2 pede 3 ações |
| Drawer | `Components/ui/sheet.tsx` | tem focus trap — resolve o item 4 de §18 |
| Modal de confirmação | `Components/ui/alert-dialog.tsx` | — |
| Campo de dinheiro | `Components/ui/numeric-input-ptbr.tsx` | **usar sempre** em campo de valor: entrada `pt-BR` (`1.234,56`) sem ambiguidade de locale |
| Gráfico do relatório | `Components/shared/Chart.tsx` | a barra `%` do §4.6 é uma barra mini inline; `Chart` é para série |
| Ícones | `lucide-react` | os 4 usados: `plus`, `search`, `printer`, `pencil`. O `icons.jsx` do protótipo é espelho de 22 glifos — **contorno**, não portar |

---

## §16 · Mapa de campos — todo número exibido tem origem

`[FECHADA]`. `D` = derivado (com fórmula) · `T` = digitado (com campo de origem).

| Na tela | D/T | Origem |
|---|---|---|
| Nome da receita | **D** | cadeia da variação: `products.name` + `product_variations.name` + `variations.name` + `variations.sub_sku` `[DS]` (`Entities/MfgRecipe.php::forDropdown` L64-82). **Não existe coluna `name` na receita** — não crie |
| SKU exibido (`MFG-0001`) | **D** | `[TELA]` rótulo do protótipo. No alvo o identificador é `variations.sub_sku` |
| Categoria / subcategoria | **T** | `categories` do produto (chain `products.category_id`) |
| Quantidade produzida | **T** | `mfg_recipes.total_quantity` |
| Unidade | **T** | `units` via `products.unit_id` |
| Sub-unidade de saída / Fator | **T** | `mfg_recipes.sub_unit_id` → `units.base_unit_multiplier` |
| Desperdício % | **T** | `mfg_recipes.waste_percent` |
| Custo extra + tipo | **T** | `mfg_recipes.extra_cost` + `mfg_recipes.production_cost_type` `[FECHADA] {fixed, percentage, per_unit}` |
| Preço de venda | **T** | `mfg_recipes.final_price` |
| Quantidade do ingrediente | **T** | `mfg_recipe_ingredients.quantity` |
| Sub-unidade do ingrediente | **T** | `mfg_recipe_ingredients.sub_unit_id` |
| Ordem dos ingredientes | **T** | `mfg_recipe_ingredients.sort_order` |
| Grupo do ingrediente | **T** | `mfg_recipe_ingredients.mfg_ingredient_group_id` → `mfg_ingredient_groups.name` / `.description` `[DS]` (`Utils/ManufacturingUtil.php` L88-90) |
| Custo unitário do insumo | **T** (de outra tela) | `variations.dpp_inc_tax` — preço de compra com imposto. Manufacturing **lê**, nunca escreve |
| Estoque do insumo | **T** (de outra tela) | `variation_location_details` por local `[DS]` (`ManufacturingUtil::getIngredientDetails` L26-31) |
| Subtotal da linha | **D** | `quantity × dpp_inc_tax × base_unit_multiplier` |
| Subtotal do grupo | **D** | Σ dos subtotais das linhas do grupo |
| Ingredientes / Custo extra / Custo total | **D** | §7 |
| Rendimento líquido | **D** | `total_quantity − total_quantity × waste_percent/100` |
| Custo unitário | **D** | `custo_total ÷ total_quantity` |
| Margem % | **D** | `(final_price − custo_unit) ÷ final_price × 100` |
| Custo médio / unidade (KPI) | **D** | média aritmética do custo unitário das receitas do business |
| Margem abaixo de 45% (KPI) | **D** | contagem com `margem < 45` |
| Desperdício ≥ 8% (KPI) | **D** | contagem com `waste_percent ≥ 8` |
| Peso do insumo na receita (%) | **D** | `qtd_do_insumo × preço ÷ custo_total × 100` |
| Custo simulado com ±X% | **D** | `(custo_total + qtd × preço × X/100) ÷ total_quantity` |
| Referência da ordem | **T** | `transactions.ref_no`; prefixo de `business.manufacturing_settings.ref_no_prefix` |
| Data / Local da ordem | **T** | `transactions.transaction_date` / `.location_id` |
| Quem lançou | **T** | `transactions.created_by` |
| Situação da ordem | **T** | `transactions.mfg_is_final` (`1` finalizada · `0` rascunho) |
| Consumo por ingrediente na ordem | **D** | `quantity_da_receita × (qtd_da_ordem ÷ total_quantity)`, salvo override |
| Custo total da ordem (rascunho) | **D** | ao vivo, preço de hoje |
| Custo total da ordem (finalizada) | **T** | `transactions.final_total` — congelado no fechamento |
| Custo extra da ordem | **D/T** | `transactions.mfg_production_cost` + `.mfg_production_cost_type` |
| Variação "mesma receita hoje" | **D** | `(vivo − congelado) ÷ congelado × 100` |
| Versão do módulo | **T** | `System::getProperty('manufacturing_version')` `[DS]` (`SettingsController@index` L48) |

**Configurações** `[FECHADA]` — chaves reais do JSON `business.manufacturing_settings` `[DS]`
(`SettingsController@store` L67-73):

| Na tela | Chave |
|---|---|
| Prefixo da referência | `ref_no_prefix` |
| Bloquear edição da quantidade de ingrediente | `disable_editing_ingredient_qty` |
| Atualizar preço do produto ao finalizar produção | `enable_updating_product_price` |

**Permissões** `[FECHADA]` — nomes reais `[DS]` (migrations `2019_07_26_170450`, `2019_08_08_172837`;
`Http/Controllers/DataController.php` L37-52):

| Na tela | Permissão |
|---|---|
| `ver` | `manufacturing.access_recipe` |
| `criar` | `manufacturing.add_recipe` |
| `editar` | `manufacturing.edit_recipe` |
| `prod` | `manufacturing.access_production` |

Gate de assinatura, sempre junto: `hasThePermissionInSubscription($business_id, 'manufacturing_module')`.

---

## §17 · Requisitos com teste de aceite

`[FECHADA]` — 24 requisitos. Cada um se confere em uma linha.

| # | Requisito | Teste de aceite |
|---|---|---|
| R-01 | 5 abas de §4.1, com contadores | abrir o módulo: 5 abas, "Receitas" com o nº de receitas e "Ordens" com nº + rascunhos |
| R-02 | Aba "Ordens" não renderiza sem `manufacturing.access_production` | usuário sem a permissão vê 4 abas e nenhuma referência a produção |
| R-03 | Busca casa nome, SKU, categoria e subcategoria | digitar `MFG-0003` retorna 1 linha; digitar `banner` retorna as 2 de banner |
| R-04 | `/` foca a busca; dentro de campo, `/` digita | com foco no corpo, `/` põe cursor na busca; com foco na busca, `/` insere `/` |
| R-05 | KPI 2 e 3 filtram; KPI 1 e 4 não | clicar "Margem abaixo de 45%" reduz a lista e o cartão ganha anel `--accent`; clicar em "Custo médio" não muda nada |
| R-06 | Ordenação alterna e volta à página 1 | 2 cliques em "Custo total" invertem a ordem; estando na página 2, ordenar leva à 1 |
| R-07 | 10 linhas por página; contador `a–b de N` | com 24 resultados: `1–10 de 24`, 3 botões de página |
| R-08 | "Selecionar todas" marca as filtradas, não as visíveis | com filtro de 24 resultados, marcar o topo seleciona 24 |
| R-09 | Coluna Quantidade declara a unidade que está exibindo | receita com sub-unidade mostra `14,40 m linear`; sem sub-unidade mostra `4,40 m²` |
| R-10 | Margem colorida em 3 faixas (≥55 / 45–54,9 / <45) | 76% verde · 49% âmbar · 38% vermelho |
| R-11 | Custo unitário divide por `total_quantity`, não pelo rendimento | receita com 10 m² e 4% de desperdício: `custo_unit = total/10`, não `total/9,6` |
| R-12 | As três fórmulas de custo extra de §7 | trocar o tipo com o mesmo valor `18` muda o total conforme a fórmula |
| R-13 | Divisão por zero devolve `0`, nunca `NaN` | receita com `total_quantity = 0` mostra `R$ 0,00` e margem `0%` |
| R-14 | Drawer da receita fecha com `esc` e com clique no scrim | ambos fecham; nenhum estado fica pendurado |
| R-15 | Editor não salva sem ingrediente | receita vazia: botão "Salvar receita" desabilitado |
| R-16 | `disable_editing_ingredient_qty` bloqueia quantidade nos 2 lugares | ligar em Configurações: editor e ordem mostram a quantidade como texto |
| R-17 | Trocar receita/quantidade na ordem zera overrides | editar um consumo, trocar a quantidade: o consumo volta ao calculado |
| R-18 | Estoque insuficiente avisa e não bloqueia | consumo > estoque: linha tingida, aviso com contagem, botão salvar segue ativo |
| R-19 | Rascunho não movimenta estoque; finalizar movimenta | salvar rascunho não muda saldo; finalizar baixa insumo e dá entrada no produto |
| R-20 | Ordem finalizada congela custo e mostra os dois números | finalizar hoje e reabrir: `Custo congelado` + `Mesma receita hoje` + variação % |
| R-21 | Sufixo `fix` na lista de ordens finalizadas | linha finalizada tem `fix` com `title="custo congelado na data da produção"` |
| R-22 | Ficha "via de produção" não mostra nenhum valor de compra | imprimir a via: nenhuma ocorrência de `R$` na folha |
| R-23 | Impressão em lote gera uma folha por receita | selecionar 3 receitas e imprimir: 3 páginas A4 |
| R-24 | Botão "Atualizar" das configurações só habilita com mudança | abrir Configurações: desabilitado; mudar 1 caractere: habilita |

---

## §18 · Pendências conhecidas

1. **Markup do "Atualizar preço de venda" não foi decidido.** O protótipo usa `custo × 2`
   `[TELA]`, placeholder óbvio. Precisa de regra: markup por categoria? margem-alvo por produto?
   tabela de preço? **Não implementar o fator 2.**
2. **Desperdício por ingrediente existe no schema e a tela não mostra.**
   `mfg_recipe_ingredients.waste_percent` `[DS]` (migration `2019_08_12_114610`) e
   `transaction_sell_lines.mfg_waste_percent`. A tela modela desperdício só no nível da receita
   (`mfg_recipes.waste_percent`). Decisão pendente: expor por linha ou declarar o campo morto.
3. **A aba Insumos não tem backend.** `usosDoInsumo()` é cálculo novo; precisa de um método no
   `RecipeBomService` com o JOIN de tenant e teste. Sem isso, a aba não sai.
4. ~~**Overlay sem focus trap** no protótipo.~~ **Fechada na onda A** (2026-09-08): os cinco
   overlays são `Drawer`/`Modal` do DS, que trazem trap, `aria-modal` e devolução de foco.
   Continua valendo para o alvo usar `ui/sheet.tsx` / `ui/alert-dialog.tsx`.
5. **`<select>` nativo × `ui/select.tsx`** na aba de ordens: hoje o repo tem um
   `eslint-disable no-restricted-syntax` explícito (`Index.tsx` L165). Manter ou migrar é decisão
   de quem mantém a régua do lint, não deste pacote.
6. **Grupos de ingredientes**: a lista de 9 nomes do protótipo (`Substrato`, `Tinta`,
   `Acabamento`, `Estrutura`, `Aplicação`, `Peça`, `Estampa`, `Tecido`, `Mão de obra`) é
   **`[ILUSTRATIVA]`** — no alvo vem de `mfg_ingredient_groups` do business. Não semear.
7. **Ordem de produção não tem FSM.** O charter atual proíbe `UPDATE` direto em `transactions` e
   diz que o trait de FSM de Sells/Repair não cobre Manufacturing `[DS]` (`Index.charter.md`
   L32-33). Rascunho→finalizada precisa de caminho explícito antes de ir a produção.
8. **Pílula, chip e contador de aba vazam com rótulo de duas palavras.** Medido a 914px de
   viewport: `.mfg-pill` ("22 % do custo", coluna de 160px), `.mfg-chip` ("Comunicação visual") e
   `.mfg-tab-n` ("6 · 1 rasc.") quebram em duas linhas e o texto sai da borda arredondada — só três
   seletores do arquivo declaram `white-space: nowrap`, e nenhum é pílula. Correção da tela:
   `white-space: nowrap` nas três classes + coluna de peso 160→176px (ou encurtar o rótulo para
   `22%` e levar "do custo" para o cabeçalho). Detalhe no LAUDO, achado MÉDIA.
9. **Escrita em massa de preço não tem confirmação nem desfazer.** É a única ação da tela que
   escreve em N registros de uma vez. Ver pendência 1 — as duas andam juntas.
10. **Não medido:** Lighthouse, axe, leitor de tela, zoom 200%, e a folha PT-07 renderizada — o
    CSS de impressão foi lido linha a linha, mas nenhuma folha foi gerada nesta conferência
    (LAUDO §6).


---

## §19 · Onda A — 23 elementos passaram a vir do DS (2026-09-08)

`[FECHADA]` — lista completa do que mudou. Nenhum item fora dela foi tocado.
Procedência: **[DS]** citado de `_ds/wagner-office-impresso-design-system-49a36f…/_ds_bundle.js`
com a linha da declaração · **[TELA]** decidido aqui · **[RUNTIME]** observado no navegador.

### 19.1 · Pré-requisito

A família resolve o namespace em tempo de render, não de carga:
`const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};` — os cinco arquivos são
lazy e o bundle é `defer`. **Conferir:**
`Object.keys(window.OfficeImpressoPontoWR2DesignSystem_019dd0).length` === 59 no console.

### 19.2 · Substituições

| # | Elemento | Estava | Ficou | Conferir |
|---|---|---|---|---|
| A-01 | Gaveta da receita | `.mfg-scrim`+`.mfg-drw` | `Drawer`+`DrawerSection` **[DS]** L3753, `width={680}` | abre pela linha da tabela; `Tab` circula dentro |
| A-02 | Gaveta da ordem | idem | idem | idem, na aba Ordens |
| A-03 | Gaveta do insumo | idem | idem | **`Esc` fecha — antes não fechava** |
| A-04 | Modal "Nova receita" | `.mfg-modal` | `Modal` **[DS]** L5041, `width={520}` | foco entra no primeiro campo |
| A-05 | Modal de exclusão | `.mfg-modal.sm` | `Modal`, `width={400}` | copy de perda sai verbatim |
| A-06 | KPI-filtro ×2 | `button.mfg-kpi` | `KpiFilterCard` **[DS]** L4856, `tone="amber"` | `aria-pressed` alterna |
| A-07 | Paginação ×2 | `.mfg-pag` | `Pagination` **[DS]** L5263 | `<nav aria-label="Paginação">` + `aria-current="page"` |
| A-08 | Abas | `nav.mfg-tabs` | `TabBar` **[DS]** L6605 | `aria-current="page"` na ativa |
| A-09 | Barra de seleção | `.mfg-bulk` | `BulkBar` **[DS]** L2137 | marcar 2 linhas; "Limpar" virou o ✕ do componente |
| A-10 | Dica do sufixo `fix` | `title=` nativo | `Tooltip` **[DS]** L6948 | **abre por foco de teclado**, não só mouse |
| A-11 | Estado vazio ×4 | `.mfg-empty` | `EmptyState` **[DS]** L4126, `variant="no-results"` | cada um ganhou ação de saída |
| A-12 | Switch ×2 (Config) | `label.mfg-check.big` | `Switch` **[DS]** L6526 | label + sublabel do componente |
| A-13 | Checkbox ×2 | `label.mfg-check` | `Checkbox` **[DS]** L2511 | "Só finalizadas" nas duas telas |
| A-14 | Campo de data ×5 | `input[type=date]` | `DatePicker` **[DS]** L3360 | calendário PT-BR dd/mm/aaaa |
| A-15 | Cabeçalho | `.os-page-h` (classe de outro módulo) | `PageHeader` **[DS]** L5158 | `h1` com `font: 600 22px/1.3` |
| A-16 | KPI de leitura ×2 | `div.mfg-kpi` | `KpiCard` **[DS]** L4650 (sub-rótulo é `description`) | — |
| A-17 | Botões ×23 | `.os-btn` | `Button` **[DS]** L2246 | `grep -n "os-btn" manufacturing-*.jsx` volta 0 |
| A-18 | Faixa de margem ×3 | `.mfg-pill.ok/.warn/.bad` | `StatusBadge tone` **[DS]** L6282 | 76% success · 49% warning · 38% danger |
| A-19 | Barra de % do período | `.mfg-bar-mini` | `Progress variant="bar"` **[DS]** L5796 | aba Relatório |
| A-20 | Aviso de bloco ×4 | `p.mfg-err` / `.mfg-note` | `Alert` **[DS]** L738 | estoque insuficiente leva `action` → Compras |
| A-21 | Toast | `.mfg-toast` | `Toast` **[DS]** L6900, `tone="ok"` | salvar receita |
| A-22 | Campos de texto | `.mfg-inp` | `Input`/`Select`/`Textarea` **[DS]** L4481/4546/4511 | só os **não** numéricos — ver 19.4 |
| A-23 | Folha PT-07 | SVG e CSS locais | `RegistrationMark` L6147 · `ProofFrame` L5931 · `Dimension` L3642 · `ProofStrip` L6023 **[DS]** | ver 19.5 |

### 19.3 · Defeito achado na execução

**[RUNTIME]** O `DatePicker` recebe `value` como `Date|ISO`; passando a string ISO crua
(`"2026-08-01"`) ele interpreta meia-noite **UTC** e exibe o dia anterior em fuso negativo —
medido: `01/08/2026` aparecia como **`31/07/2026`** em UTC-3.

**Contorno [TELA]:** a ponte é local nos dois sentidos, em `manufacturing-producao.jsx` L17-18 —
`fromISO()` monta `new Date(y, m-1, d)` e `toISO()` lê `getFullYear/getMonth/getDate`.
Nunca `toISOString()` sobre a data local. **Conferir:** aba Ordens abre com `01/08/2026` e
`31/08/2026`. **Não é defeito do DS** — é o contrato `Date` sendo alimentado com string; foi
para a pauta como pedido de aceitar ISO local, não como bug.

### 19.4 · O que continua local — contorno declarado, nunca decisão de estilo

Cada linha tem o motivo **medido** e o item da auditoria. Não substituir por componente do DS
sem que a lacuna abaixo seja fechada primeiro.

| Fica | Por quê | Item |
|---|---|---|
| `.mfg-table` ×4 | `DataTable` sem cabeçalho fixo e sem largura mínima (a tela usa `position:sticky` e `min-width` 960/1100/900/940); `DataTablePro` não aceita ordenação/seleção controladas | B-01 |
| `.mfg-s` (busca ×3) | `Input` sem `ref` (atalho `/` = R-04), sem slot de ícone, sem `onKeyDown` (Enter escolhe o primeiro insumo) | B-02 |
| `.mfg-inp` numéricos (9 campos) | `Input` não tem `min`/`max`/`step` — o passo de 0,001 do ingrediente e o teto de 100% do desperdício se perderiam | B-04 |
| `.mfg-crumb` ×2 | `Breadcrumb` só aceita `href`; a volta do editor é por estado | B-07 |
| `.mfg-chip` ×3 usos | `FilterChip` é pílula de filtro ativo com ✕, não seletor de escolha | B-08 |
| `.mfg-grp`/`.mfg-ing` | grade de ingredientes editável, 4 variantes — sem equivalente no DS | C-03 |
| `.mfg-sim` | não há `Slider` no DS; `Progress` é leitura | C-04 |
| `.mfg-foot` ×2 | `DataTable` não tem slot de rodapé | C-06 |
| `.mfg-tot` ×4 | não há `KeyValue`/`SummaryList` no DS | C-07 |
| 11 cinzas do `@media print` | o cockpit não publica paleta de impressão | C-08 / ADR 0413 |
| `window.I` (22 glyphs) | espelho do DS; o produto usa Lucide inteiro | C-09 |

**Ganhos de acessibilidade que não vieram de componente [TELA]:** `aria-pressed` nos chips de
categoria e de permissão; `aria-label` nos campos numéricos e nos botões ✕ da grade;
`aria-label` + `aria-valuetext` no slider da aba Insumos.

### 19.5 · A folha PT-07 precisa ser impressa antes de fechar

**[TELA]** `ProofFrame` entra com `grid={false}` — a grade de prova é ruído sobre papel.
Saíram do código as 4 tintas de processo em hex (`#00AEEF #EC008C #FFF200 #231F20`) e a escada
de cinza calculada em runtime (`rgb(255 − k×2,55)`); as duas tiras agora são
`ProofStrip kind="cmyk"` e `kind="density"`.

⚠️ **Pendente, e é medição, não conserto:** os quatro primitivos pintam por token de tela e o
cockpit não publica paleta de impressão (C-08). **Conferir:** imprimir uma ficha com custo e uma
via de produção e olhar mira, marcas de corte, cotas e tiras no papel. Se o token não sobreviver
ao `@media print`, o resultado vira linha nova nesta seção — **não** um ajuste dentro do
componente do DS.

### 19.6 · O que a onda A não resolveu

Continuam abertos, por decisão do Wagner (listas B e C de `auditoria-aderencia-fabricacao.md`):

1. **As 4 tabelas não são operáveis por teclado** — a linha é `div onClick`, sem `tabIndex`,
   sem `role`, sem Enter. É a falha mais grave do módulo e depende de B-01.
2. **Nenhum retorno de ação é anunciado** — não existe `aria-live` em nenhum componente do
   bundle (grep nos 9.204 linhas: zero). O `Toast` é visual.
3. **Abas não navegam por seta** ←/→ — o `TabBar` trata clique, não é `role="tablist"`.
4. **Erro de campo não se liga ao campo** — o `Input` grava `data-invalid` mas não emite
   `aria-invalid` nem `aria-describedby`.
5. **Alvos abaixo de 44px em ponteiro fino** — já aceito no §11.4.

Os itens 2 e 3 **não são da Fabricação**: valem para Clientes, Financeiro e Oficina igualmente.


---

## §20 · Defeito do shell do protótipo achado nesta onda — **não portar para o alvo**

`[CONTORNO DO PROTÓTIPO]` Este parágrafo existe para quem implementa **não** copiar nada daqui.
O alvo é Inertia + Vite: não tem fila de carregamento própria, e nada desta seção se aplica a ele.
Está escrito porque o defeito apareceu enquanto a onda A era verificada e quase foi confundido
com quebra do módulo — se ele reaparecer, o diagnóstico já está pronto.

### 20.1 · Está / deve ficar

**Estava** (`oimpresso.com.html`, laço `proximo()`): o carregador preguiçoso baixa os ~150
arquivos de módulo numa janela deslizante de 8 e executa em ordem. Duas falhas somadas:

1. `fetch` não tem prazo. Uma conexão que **nunca assenta** deixa a promessa pendente para
   sempre — nem `.then` nem `.catch` disparam.
2. O reagendamento (`setTimeout(proximo, 0)`) morava **dentro** do `.then` de sucesso e do
   `.catch`. Qualquer caminho que escapasse dos dois parava a fila de vez.

**Sintoma:** a fila congela em silêncio — zero erro no console, `window.__oiLazyDone` preso em
`false`, `document.querySelectorAll('script').length` parado. Tudo que está **atrás** do
arquivo travado nunca carrega; o boundary do shell mostra "Carregando módulo…" para sempre.

**Por que o sintoma muda de tela em tela:** a partição por rota (L353-380) joga a família da rota
salva em `localStorage["oimpresso.route"]` para a **frente** da fila. Abrindo por Fabricação, os
seis arquivos da família já rodaram antes do ponto de travamento e a tela funciona; abrindo por
outra rota, a família fica **atrás** e o módulo nunca aparece. O mesmo build "funciona" e "não
funciona" conforme a última rota visitada — foi o que fez o defeito parecer da onda A.

**Deve ficar** (aplicado em 08/09/2026): prazo de **15.000 ms** por arquivo
(`Promise.race` com um `setTimeout` que resolve `null`; estourou → `console.warn` e a fila
segue sem ele), e o reagendamento movido para o **último elo** da corrente
(`.then(…).catch(…).then(reagenda)`), onde não depende de nada acima ter dado certo. Ordem de
execução idêntica: nenhuma dependência entre arquivos se inverte.

### 20.2 · Como conferir

1. Abrir com `localStorage.removeItem("oimpresso.route")`, recarregar e ir a Fabricação:
   `document.querySelector(".mfg-root")` existe.
2. `window.__oiLazyDone === true` ao fim da fila (pode levar minutos em preview lento — o
   contador `document.querySelectorAll("script").length` tem de **subir** entre duas leituras
   com 10 s de intervalo; medir em janela de 4 s dá falso positivo de "congelada").
3. Nenhum arquivo perdido derruba o resto: quem estourar o prazo aparece como
   `Carregador: <arquivo> não chegou` no console, e a fila continua.

### 20.3 · O que isso ensinou sobre medir

**Fila lenta e fila morta se parecem.** Neste preview a fila anda a ~5 s por arquivo; duas
leituras com 4 s de intervalo dão o mesmo número e parecem congelamento. Duas rodadas de
diagnóstico foram gastas assim — a minha e a da verificação, as duas concluindo "morreu em
`manufacturing-producao.jsx`" quando os seis arquivos da família já haviam carregado. **Antes de
chamar de travado, amostrar em intervalo maior que o tempo de um passo, e listar o que já
carregou** (`[...document.querySelectorAll("script")]` filtrando por `sourceURL=`) em vez de
inferir pelo global que falta.


---

## §21 · Atualização do DS puxada em 2026-09-08 — o que mudou na Fabricação

### ⚠️ 21.0 · Onde gravar uma atualização do DS — errei aqui, leia antes

**O projeto tem DOIS arquivos de bundle, e a página carrega o que NÃO é o espelho vinculado.**

| Arquivo | Quem carrega |
|---|---|
| `_ds/wagner-office-impresso-design-system-**49a36f76**-…/_ds_bundle.js` | **`oimpresso.com.html`** — espelho único desde 21/09/2026 |
| ~~`_ds/office-impresso-design-system-**019dd02f**-…`~~ | apagada em 21/09/2026, junto com o alias de 5 linhas que ela carregava |

**Histórico — o defeito que este parágrafo registrava.** Havia dois espelhos, um deles com um alias
no fim do bundle. Eram **cópias**, não links: atualizar um não movia o outro.

**O que eu fiz de errado.** Puxei a atualização, gravei só no espelho 49a36f e declarei
resolvido lendo o arquivo. O runtime seguiu com o bundle velho por 4.114 ch, e as duas edições
que dependiam dele **quebraram a tela em silêncio**:

- `kind="producao"` caiu em `MAP['producao'] === undefined` → a coluna Situação passou a
  imprimir o valor cru do banco: `"rascunho"`, `"finalizada"` em minúsculas, sem cor. Enum de
  banco exposto ao usuário, contra a regra PT-BR/sentence-case do próprio guia.
- `tone="soft-*"` caiu em `C[tone] || C.outline` → as 3 pílulas de margem ficaram **todas
  iguais**, transparentes. Regressão: com o `tone` sólido anterior pelo menos havia cor.

**Nenhum erro no console nos dois casos.** `StatusBadge` não avisa kind nem tom desconhecido —
degrada em silêncio. É o pior modo de falha possível para quem confere lendo arquivo.

**Regra, então:** ao puxar DS, gravar **nos dois** caminhos (bundle + `colors_and_type.css`),
versionar o `?v=` das duas tags, e **conferir em runtime, nunca no arquivo**:

```js
const ns = window.OfficeImpressoPontoWR2DesignSystem_019dd0;
/'soft-success'/.test(String(ns.StatusBadge))   // → true
/\n {4}producao: \{/.test(String(ns.StatusBadge)) // → true  (NÃO usar /producao/: casa em
                                                  //  em_producao dentro do kind `os`)
/scrollbarWidth/.test(String(ns.TabBar))         // → true
```

**Medido depois da correção (08/09/2026):** os três `true`; Ordens lê "Rascunho"
`oklch(0.27 0.06 75)` âmbar e "Finalizada" `oklch(0.27 0.06 162)` verde; margens 82/76/58%
tintadas de novo.

---

O espelho local (`_ds/wagner-office-impresso-design-system-49a36f…/`) estava atrás da origem.
Puxado: bundle 287.322 → **291.436 ch** (9.204 → 9.290 L), `colors_and_type.css` 19.852 →
**20.877 ch**, manifesto 44 componentes nos dois (nenhum componente novo, nenhum removido).

**Três funções mudaram** — `StatusBadge` (+2.958 ch), `TabBar` (+208), `Skeleton` (+516).
Duas tocam esta família.

### 21.1 · `StatusBadge` ganhou o domínio `producao` — B-03 fechado

`[DS]` bundle L6537-6540: `producao: { finalizada: ['Finalizada','soft-success'], rascunho:
['Rascunho','soft-warning'] }`. Entraram junto `arquivo_prazo`, `ajuste_estoque` e
`transferencia_estoque` — nenhum usado aqui.

**Está / deve ficar:** a situação da ordem (`manufacturing-producao.jsx`) sai do contorno
`tone="success" label="Finalizada"` + `kind="documento" value="rascunho"` e passa a
`<StatusBadge kind="producao" value={op.final ? "finalizada" : "rascunho"} />`.
**Conferir:** aba Ordens — "Finalizada" verde-soft com dot, "Rascunho" **âmbar** com dot.

⚠️ **O rascunho é âmbar de propósito, não engano.** O comentário do componente diz que o DS
adotou a forma que **este protótipo** já pintava em `.mfg-pill.warn`, citando UI-0029
(soberania da FORMA ao protótipo). Não "corrigir" para cinza.

### 21.2 · `TabBar` esconde a barra de rolagem — o desvio que a Wagner viu

`[DS]` A versão nova acrescenta `scrollbarWidth: 'none'`, `msOverflowStyle: 'none'` e um
`<style>` interno `.ds-tabbar-scroll::-webkit-scrollbar{display:none;height:0}` (bundle
L6677-6700). O `overflowX: 'auto'` continua — a barra **rola**, só não **aparece**.

**Medido antes:** calha de 10px (`width 624` vs `clientWidth 614`). **Depois:** calha **0**,
`className === "ds-tabbar-scroll"`. Nada a fazer na tela — mas só depois de gravar o bundle no
caminho certo (§21.0); atualizar o espelho sozinho não muda nada em runtime.

**Nota de origem, sem ação nesta tela:** a barra que aparecia era **vertical**, não horizontal.
O `TabBar` define só `overflowX: 'auto'`, e pela regra do CSS o outro eixo deixa de ser
`visible` e vira `auto` — com `height: 36` no botão e `marginBottom: -1`, sobra 1px de
transbordo (`scrollHeight 36` vs `clientHeight 35`) e o navegador desenha a calha. A regra
`::-webkit-scrollbar{display:none;height:0}` esconde as duas, então o sintoma sumiu — mas o
transbordo de 1px continua existindo. Foi para `pauta-design-system.md`.

**O transbordo em si continua sendo culpa da tela**, e continua aberto: a aba "Ordens de
produção" mede 230px porque o `count` recebe `"6 · 1 rasc."`, uma frase dentro de uma pílula
dimensionada para número (`font: 600 10.5px var(--font-mono)`, `padding: 0 6px`,
`minWidth: 18` — L6741-6749). É o B-09 da auditoria, **aguardando decisão de conteúdo**: só o
número na pílula, ou os dois números fora dela. A barra escondida deixa de ser sintoma visível,
mas o rótulo continua ocupando o dobro do que deveria.

### 21.3 · Desvio meu corrigido junto: tom sólido em badge de estado

As 3 pílulas de faixa de margem saíram da onda A com `tone="success|warning|danger"` — que é
**fill sólido** (`bg: var(--color-success)`, `fg: '#fff'`, L6297-6310). O AP7 do guia manda o
oposto: fundo tintado 5–10% + dot + texto colorido, *"nunca bg-fill sólido nem pastel"*.

**Deve ficar:** `soft-success` / `soft-warning` / `soft-danger` (L6404-6427) — fundo
`--color-*-soft`, texto `--color-*-fg`, borda 20%, dot ligado.
**Conferir:** `grep -n 'tone={.*"success"' manufacturing-*.jsx` volta 0 (todos com `soft-`).

### 21.4 · Sem efeito aqui

`Skeleton` (+516 ch, shimmer) não é usado pela família.
