---
id: resources-js-pages-produto-etiquetas-index-casos
casos: Produto · Imprimir etiquetas · /labels/show
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a etiqueta é a única saída do catálogo que vira papel. Prévia com preço diferente do impresso, ou produto e modelo de outro negócio na folha, viram etiqueta errada no balcão.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "_pendente_ — o trio nasce na thread Produto/04. O veredito por UC entra no manifesto quando a lane estoque-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produto · Imprimir etiquetas (`/labels/show`)

> **Âncora:** o [charter](Index.charter.md) (R1–R8 e os Anti-hooks), a ficha `04-etiquetas.md` do
> playbook, a regra mestre de valor (`memory/proibicoes.md`) e os casos F1
> `cowork-inbox/produto-telas-novas/Etiquetas.casos.md` (UC-ETQ-01..10, [CC]). Os UCs não derivam do `.tsx`.

---

## UC-PETQ-01 · A tela nova só abre para o negócio da flag, com grupos e modelos dele · `must`

Origem: charter R1/R5 · Anti-hooks (outro negócio, permissão) · decisão [W] 2026-10-06 (flag MWART por negócio, biz=1 primeiro).

**Dado** que tenho `print_labels.access` no negócio 98, com um grupo de preço ativo e um modelo de etiqueta,
e o negócio 99 com outro grupo e outro modelo
**Quando** abro `/labels/show?product_id=<produto do 98>`
**Então** recebo Inertia `Produto/Etiquetas/Index` com a linha do produto, só o grupo e o modelo do 98
(mais os modelos do sistema), em qualquer empresa (sem flag desde a decisão [W] de 2026-10-06, noite);
`?classico=1` também abre a tela React, porque a Blade `labels.show` foi apagada no cutover de 2026-10-27;
e sem `print_labels.access` recebo 403.

Status: 🧪

---

## UC-PETQ-02 · O preço da linha é o que a impressão sairia · `must`

Origem: charter R2/R3 · UC-ETQ-03/04 · regra mestre de valor (dupla prova).

**Dado** um produto com venda 20, imposto de 10% e preço 15,5 no grupo Atacado
**Quando** a tela pede a linha a `/labels/add-product-row` em JSON
**Então** o preço sem grupo é `20,00` nos dois tipos, e no Atacado é `15,50` com imposto e `14,09`
sem imposto — o mesmo que a chamada de `ProductUtil` que `preview()` faz devolve.

Status: 🧪

---

## UC-PETQ-03 · Grupo percentual e grupo sem preço · `should`

Origem: charter R2.

**Dado** um grupo percentual de 10% e um grupo sem preço cadastrado
**Quando** a tela pede a linha
**Então** o grupo percentual sai `2,00` (10% de 20, como `getVariationGroupPrice` calcula) e o grupo sem
preço sai vazio.

Status: 🧪

---

## UC-PETQ-04 · Produto e grupo de outro negócio não entram · `must`

Origem: charter Anti-hook (outro negócio) · ADR 0093.

**Dado** um produto e um grupo de preço do negócio 99
**Quando** a tela do negócio 98 pede a linha
**Então** o produto do 99 volta sem linha, e o grupo do 99 não aparece nos preços da linha do 98.

Status: 🧪

---

## UC-PETQ-05 · A linha vem sempre em JSON · `should`

Origem: RUNBOOK §1. Até 2026-10-27 a Blade (`?classico=1`) pedia a linha em HTML; ela foi apagada no cutover.

**Quando** alguém pede a linha sem aceitar JSON
**Então** recebe a mesma linha em JSON (`linhas`); o HTML `labels.partials.show_table_rows` saiu com a Blade.
A folha impressa continua em Blade (`/labels/preview`), por decisão [W] de 2026-10-27.

Status: 🧪

---

## UC-PETQ-06 · Vários produtos vindos da seleção da lista de produtos · `should`

Origem: UC-ETQ-02 do F1 (era backlog) e a barra da seleção do protótipo da lista (`produto-blade.jsx`, "Etiquetas").

**Dado** dois produtos da empresa e um de outra empresa selecionados na lista
**Quando** a lista abre `/labels/show?product_ids=a,b,c`
**Então** a tela já vem com uma linha por variação dos produtos da empresa, na ordem pedida e com 1 etiqueta cada; o produto de outra empresa não entra (o mesmo `getDetailsFromProduct` de um produto só, que filtra pela empresa).

Teste: [`ProdutoEtiquetasContratoTest`](../../../../tests/Feature/Produto/ProdutoEtiquetasContratoTest.php) — `UC-PETQ-06`.

Status: ⬜ nasce neste PR — verde esperado.

---

## Backlog (sem teste ainda)

- [BACKLOG] Paginar a prévia por folha real e o botão dizer quantas folhas (UC-ETQ-01/07/09 do F1) — comportamento só do navegador.
- [BACKLOG] Desligar uma informação desabilita o corpo dela e a tira da prévia (UC-ETQ-05 do F1).
