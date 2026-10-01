---
id: resources-js-pages-produto-importacao-index-casos
casos: Produto · Importação de produtos · /import-products
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a importação cria produto, marca, categoria e estoque inicial em lote. Uma conferência que grava, ou uma tela nova que importa diferente da antiga, move estoque e preço sem ninguém ver.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Produto/05. O veredito por UC entra no manifesto quando a lane estoque-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produto · Importação de produtos (`/import-products`)

> **Âncora:** o [charter](Index.charter.md) (R1, R3, R6 e os Anti-hooks), o trio proposto
> `cowork-inbox/produto-telas-novas/Importacao.*` (UC-IMP-01..07, F1 [CC] 2026-08-21), a ficha
> `07-importacao.md` do playbook ("valida antes de gravar") e a regra mestre VALOR/ESTOQUE.
> Os UCs não derivam do `.tsx`.

---

## UC-PIMP-01 · `/import-products` abre a tela nova · `must`

Origem: ficha 07 · permissão que o legado já exigia (`product.create`, D2 [W] 2026-10-01).

**Dado** que tenho `product.create`
**Quando** abro `/import-products`
**Então** recebo Inertia `Produto/Importacao/Index` no modo produtos; `?classico=1` devolve a view
`import_products.index`; sem `product.create` recebo 403.

Status: 🧪

---

## UC-PIMP-02 · Conferência lista o que seria criado · `must`

Origem: charter R6 · trio UC-IMP-01.

**Dado** uma planilha boa de 2 produtos
**Quando** envio com `conferir=1`
**Então** volto pra tela com a conferência: uma linha por produto, com linha, nome, SKU, tipo,
estoque inicial, custo e preço como o servidor calculou.

Status: 🧪

---

## UC-PIMP-03 · Conferência não grava nada · `must`

Origem: Anti-hook do charter · regra mestre VALOR/ESTOQUE.

**Dado** uma planilha com marca e categoria novas e estoque inicial
**Quando** confiro
**Então** nenhum produto, marca, categoria, variação, estoque inicial ou lançamento de estoque fica
gravado.

Status: 🧪

---

## UC-PIMP-04 · Caminho novo grava igual ao antigo · `must` `[T0]`

Origem: regra mestre VALOR/ESTOQUE (prova por dois caminhos).

**Dado** a mesma planilha
**Quando** importo direto (caminho antigo) num negócio e confiro e depois envio (caminho novo) em outro
**Então** produtos, marcas, categorias, custo, preço, saldo em estoque e as linhas do estoque
inicial gravados são idênticos.

Status: 🧪

---

## UC-PIMP-05 · Unidade de outro negócio é recusada, com a linha · `must` `[T0]`

Origem: Anti-hook do charter (Tier 0) · trio UC-IMP-03.

**Dado** uma planilha cuja unidade só existe em outro negócio
**Quando** confiro
**Então** a tela mostra o erro do servidor com o número da linha e nada é gravado em nenhum dos dois.

Status: 🧪
