---
title: "Preço por faixa de quantidade — o 'A PARTIR DE' do pacote V2 não tem fonte no UltimatePOS"
status: proposed
date: "2026-08-19"
decisores: [Wagner (aprova), Maiara (pediu a onda 3), Claude Code (autor)]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
origem: "Aplicação do pacote 'PROTÓTIPO OFICIAL — PRODUTO UNIFICADO V2' (2026-08-19) na tela /products/unificado. As ondas 1 e 2 (moldura e paginação) foram aplicadas; a onda 3 esbarrou neste campo. Pedido de [M]: 'Tudo, inclusive o preço por faixa. Aí eu abro antes uma proposta de schema pra faixa de quantidade — é decisão de produto, não de tela.'"
---

# Preço por faixa de quantidade — o "A PARTIR DE" precisa de decisão antes de existir

> **Não é bug nem preguiça.** É uma capacidade que o protótipo mostra e que o ERP **não tem
> onde guardar**. Implementar sem decidir significaria inventar o número — que é exatamente o
> que o próprio pacote proíbe.

## 1 · O que o pacote mostra

Na coluna **Preço de venda**, itens com mais de uma faixa não mostram um valor: mostram o
sobrescrito **"A PARTIR DE"** com o menor valor, sublinhado tracejado. Passar o mouse abre um
popover com até 4 linhas no formato `<quantidade> + → <preço>`, mais a nota *"Preço também varia por cor
e tamanho"* e a ação *"Ver todas as variações"*.

Traduzindo pro balcão: **"compre mais, pague menos por unidade"**.

## 2 · De onde o protótipo tira esses números

De lugar nenhum. Ele **deriva do preço cheio**, com fatores fixos `1 / 0,93 / 0,88 / 0,84 /
0,81` e piso em `custo × 1,15`:

```js
fatoresFaixa = [1, 0.93, 0.88, 0.84, 0.81];
faixasDe(r, v) {
  const piso = r.cost !== undefined ? r.cost * 1.15 : 0;
  return v.qtds.map((q, i) => [q, Math.max(r.price * this.fatoresFaixa[i], piso)]);
}
```

E o **próprio handoff declara que isso não vale em produção** (§9, "O que o servidor precisa
garantir"):

> *"Faixas de preço por quantidade vindas da tabela de preço real — no protótipo elas são
> derivadas do preço; em produção a fonte é a tabela de preço, e 'a partir de' tem de ser a
> menor faixa efetiva."*

## 3 · O que o UltimatePOS tem hoje — e o que não tem

**Tem:** preço por **grupo de cliente**.

```
selling_price_groups   (id, business_id, name)          -- "Balcão", "Atacado", "Corporativo"
variation_group_prices (variation_id, price_group_id, price_inc_tax, price_type)
```

Cada variação pode ter um preço diferente por grupo, fixo ou percentual (`price_type`, default
`'fixed'`). O grupo é atributo do **cliente** (`contacts.selling_price_group_id`), não da venda.

**Não tem:** nenhuma coluna de quantidade. Verificado no **baseline de schema**
(`database/schema/mysql-schema.sql`, tabela `variation_group_prices`) — que é a fonte
autoritativa, não a migration de 2018: ela nasceu com 3 colunas e o schema real tem 4, porque
`price_type` foi adicionada depois. Mesmo no schema atual não existe `qty`, `qty_min`,
`qty_from` nem equivalente.

A diferença não é técnica, é de negócio:

| | Preço por grupo (existe) | Preço por quantidade (não existe) |
|---|---|---|
| Quem determina | **quem** compra | **quanto** se compra |
| Muda durante a venda? | não — o cliente já é do grupo | sim — mudar a qtd muda o preço |
| "A partir de" significa | "clientes de atacado pagam menos" | "compre 50 e pague menos" |

**Trocar um pelo outro na tela seria mentira.** A frase "A PARTIR DE <preço>" sob a coluna de
preço, num item cuja única variação é o grupo do cliente, faz o balcão prometer um desconto
por volume que o sistema não vai aplicar quando a venda for lançada.

## 4 · Por que isso precisa de decisão, e não de implementação

Três perguntas que só o produto responde, e das quais o schema depende:

1. **A faixa é por produto ou por variação?** Um banner com 4 cores tem a mesma tabela de
   quantidade pras 4? Se sim, a faixa pendura em `products`; se não, em `variations`.
2. **A faixa convive com o grupo de cliente ou substitui?** Cliente do Atacado comprando 100
   unidades paga o menor dos dois, o produto dos dois, ou a faixa do grupo dele?
3. **Quem aplica na venda?** Faixa que só aparece na consulta e não é aplicada no PDV é pior
   que faixa nenhuma — o vendedor promete e o sistema cobra outro valor. Isso significa mexer
   em `SellPosController`/`ProductUtil`, que é o coração do faturamento.

A pergunta 3 é a que muda o tamanho do trabalho de "uma tabela nova" pra "mexer no cálculo de
venda". Ela precisa ser respondida antes da primeira linha de código.

## 5 · Opções

### A — Tabela nova de faixas por quantidade (o que o pacote pede)

```
variation_qty_prices (id, business_id, variation_id, qty_from decimal, price_inc_tax decimal)
  UNIQUE (variation_id, qty_from)
  business_id NOT NULL + índice + FK  -- Tier 0, ADR 0093
```

- **Ganha:** é exatamente o que o pacote desenhou; "a partir de" fica honesto.
- **Custa:** UI de cadastro (tela de produto ganha um grid de faixas) + aplicação no PDV +
  decisão sobre a interação com grupo de cliente + migração dos clientes que hoje fazem isso
  na mão.
- **Risco:** toca o cálculo de venda. Não é mudança de tela.

### B — Reaproveitar o popover pros grupos de preço que já existem

O popover passa a listar **um preço por grupo cadastrado** (Balcão · Atacado · Corporativo), e o
sobrescrito vira **"MENOR PREÇO"** ou **"POR TABELA"** em vez de "A PARTIR DE".

- **Ganha:** dado real, zero schema, entrega hoje. E é informação que o balcão hoje **não vê**
  em lugar nenhum da consulta.
- **Custa:** não é o que o pacote desenhou. Muda o rótulo e o significado.
- **Risco:** baixo — nenhuma promessa nova é feita ao cliente.

### C — Não fazer

O preço continua valor único. O popover não existe.

- **Ganha:** nada muda.
- **Custa:** a coluna Preço fica muda pra quem tem tabela de preço cadastrada, que é
  justamente o cliente maior.

## 6 · Recomendação

**B agora, A quando houver sinal qualificado** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)).

B entrega hoje uma informação real que a tela não mostra, com o mesmo gesto (hover no preço)
que o pacote desenhou — muda só o rótulo, que é o que mantém a frase honesta. A é trabalho de
schema **e** de cálculo de venda, e ainda não há cliente pagando que tenha reportado precisar
de desconto por volume no sistema. Quando houver, este documento já tem as três perguntas
prontas.

Se a decisão for **A**, o caminho é: ADR canon → migration + `business_id` Tier 0 → Pest
cross-tenant → UI de cadastro → aplicação no PDV → só então a tela de consulta.

## 7 · Estado atual da tela

A onda 3 entrega **saldo por local**, **observação do produto** e **variações de cor/tamanho**
— os três têm fonte real. A coluna Preço continua mostrando **valor único**, e o popover de
faixa **não foi implementado**, aguardando esta decisão. A ausência está declarada no charter
da tela, não escondida.
