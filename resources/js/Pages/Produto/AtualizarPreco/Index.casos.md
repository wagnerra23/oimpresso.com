---
id: resources-js-pages-produto-atualizarpreco-index-casos
casos: Produto · Atualizar preço por planilha · /update-product-price
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a planilha muda o preço de todo o catálogo de uma vez. Gravar diferente do caminho antigo, gravar ao só conferir ou tocar preço de outro negócio vira venda errada em massa.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Produto/06. O veredito por UC entra no manifesto quando a lane estoque-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produto · Atualizar preço por planilha (`/update-product-price`)

> **Âncora:** o [charter](Index.charter.md) (R1–R4 e os Anti-hooks), a ficha `08-atualizar-preco.md`
> do playbook, a regra mestre de valor (`memory/proibicoes.md`) e os casos propostos
> `cowork-inbox/produto-telas-novas/Importacao.casos.md` (UC-PRC-01..04, F1 [CC]). Os UCs não derivam do `.tsx`.

---

## UC-PATPRC-01 · A tela abre com os grupos ativos deste negócio · `must`

Origem: charter R1/R4 · UC-PRC-02 (grupo inativo não aparece).

**Dado** que tenho `product.update` no negócio 98, com um grupo ativo, um inativo, e o negócio 99 com outro grupo
**Quando** abro `/update-product-price`
**Então** recebo Inertia `Produto/AtualizarPreco/Index` com só o grupo ativo do 98; sem `product.update` recebo 403;
e `?classico=1` devolve a view `selling_price_group.update_product_price`.

Status: 🧪

---

## UC-PATPRC-02 · Caminho antigo e novo gravam o mesmo preço · `must`

Origem: charter Anti-hook · regra mestre de valor (dupla prova).

**Dado** uma planilha com preço de venda `1234.56` e preço de grupo `999.9` para um SKU do 98
**Quando** ela é aplicada pelo formulário da Blade e, a partir do mesmo estado, pela tela nova (visita Inertia)
**Então** os preços gravados (`sell_price_inc_tax`, `default_sell_price`, `profit_percent`, preço do grupo) são
idênticos nos dois caminhos e iguais a `1234.56` / `999.9`; e uma planilha com `1.234,56` (vírgula decimal e milhar)
é recusada pelos dois, sem gravar nada.

Status: 🧪

---

## UC-PATPRC-03 · Conferência mostra antes → depois e não grava · `must`

Origem: charter R2 · ficha 08 ("conferência") · UC-PRC-01.

**Dado** a mesma planilha do UC-PATPRC-02
**Quando** a tela pede a conferência (`conferir=1`)
**Então** recebo, por SKU e preço, o valor de antes e o de depois — e o banco continua com os preços de antes.

Status: 🧪

---

## UC-PATPRC-04 · Conferência não vaza nem toca outro negócio · `must`

Origem: charter Anti-hook (Tier 0, ADR 0093).

**Dado** uma planilha do 98 com um SKU que só existe no negócio 99
**Quando** o 98 pede a conferência
**Então** a resposta não traz produto nem preço do 99, traz um alerta `outro_negocio` para o SKU, e o preço do 99 segue igual.

Status: 🧪

---

## UC-PATPRC-05 · Recusa do servidor chega na tela · `should`

Origem: charter R3 (a tela diz por que não aplicou).

**Dado** que o `import()` recusou a planilha (mensagem em `notification`)
**Quando** a tela volta para `/update-product-price`
**Então** a prop `erro` traz a mensagem do servidor.

Status: 🧪
