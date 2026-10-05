---
id: resources-js-pages-produto-cadastros-index-casos
casos: Produto · Cadastros de apoio · /units (abas)
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: unidade, categoria e marca são referenciadas por todo produto. Apagar uma em uso deixa produto apontando pro nada; errar o escopo mostra cadastro de outro negócio; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Produto/02. O veredito por UC entra no manifesto quando a lane estoque-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produto · Cadastros de apoio (`/units`)

> **Âncora:** o [charter](Index.charter.md) (R1, R3, R4, R6 e os Anti-hooks), copiado do trio proposto
> `cowork-inbox/produto-telas-novas/Cadastros.casos.md` (UC-CAD-01..16, F1 [CC] 2026-08-21), a ficha
> `05-cadastros.md` e `03-cadastros.md` do playbook e as permissões que as Blades já exigiam (`unit.*`, `brand.*`,
> `category.*` só para `category_type = product`).
> Os UCs não derivam do `.tsx`. A coluna "origem" diz de qual UC-CAD cada um veio.

---

## UC-PCADAP-01 · `/units` abre a tela de abas · `must`

Origem: charter R1 · D3 [W] 2026-10-01 (Page parametrizada).

**Dado** que tenho `unit.view`
**Quando** abro `/units`
**Então** recebo Inertia `Produto/Cadastros/Index` com a aba Unidades ativa, e `?aba=marcas` abre na aba Marcas.

Status: 🧪

---

## UC-PCADAP-02 · Visita Inertia não vira JSON do DataTables · `must`

Origem: Anti-hook do charter (§5 2026-09-08).

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede `/units`
**Então** recebe a prop `unidades` — e um ajax **sem** `X-Inertia` (o da tela clássica) continua
recebendo o JSON do DataTables, e `?classico=1` devolve a view `unit.index`.

Status: 🧪

---

## UC-PCADAP-03 · Cada aba pela sua permissão · `must`

Origem: ficha 05 ("props `can` por aba; aba sem view → no-perm").

**Dado** um usuário só com `unit.view`
**Quando** abre `/units`
**Então** `can.marcas.view` vem falso e a lista de marcas não vem; e um usuário sem `unit.*` nem
`brand.*` recebe 403.

Status: 🧪

---

## UC-PCADAP-04 · Cadastro de outro negócio não aparece · `must` `[T0]`

**Dado** uma unidade e uma marca no meu negócio e outras num negócio vizinho
**Quando** abro a tela
**Então** vejo as minhas e não vejo as do vizinho.

Status: 🧪

---

## UC-PCADAP-05 · Excluir unidade em uso é recusado, e a tela sabe antes · `must`

Origem: UC-CAD-03 + UC-CAD-05 (a contagem é a mesma que vira o link pro índice filtrado).

**Dado** que 1 produto usa a unidade "m²"
**Quando** abro a tela e depois tento excluir "m²"
**Então** a linha traz `em_uso = 1` (a confirmação diz quantos usam e não oferece Excluir) e o
servidor recusa a exclusão — a unidade continua lá.

Status: 🧪

---

## UC-PCADAP-06 · Excluir unidade livre · `must`

Origem: UC-CAD-04.

**Dado** que nenhum produto usa "Quilograma"
**Quando** excluo
**Então** o servidor aceita e a unidade sai da lista.

Status: 🧪

---

## UC-PCADAP-07 · Marca em uso não sai · `must`

Origem: charter R4. Antes desta thread o legado apagava a marca em uso.

**Dado** que 1 produto usa a marca "Vinilcor"
**Quando** tento excluir
**Então** o servidor recusa dizendo quantos produtos usam, e a marca continua lá.

Status: 🧪

---

## UC-PCADAP-08 · Múltiplo de base escrito na linha · `should`

Origem: charter R6 · UC-CAD-02 ("Então").

**Dado** a unidade "Caixa" (cx) cadastrada como 1000 × "Unidade" (Un)
**Quando** abro a aba Unidades
**Então** a linha mostra `1 cx = 1000 Un`.

Status: 🧪

---

## UC-PCADAP-09 · Aba Categorias: só as de produto, do meu negócio, pai seguido das filhas · `must` `[T0]`

Origem: UC-CAD-10 ("Então": linha indentada e "em <pai>") · charter R7 · ficha 05 (`category.*` só quando `category_type == 'product'`).

**Dado** que tenho `category.view`, e existem "Comunicação visual" com a subcategoria "Lonas", uma
taxonomia de outro módulo e uma categoria de um negócio vizinho
**Quando** abro `/units?aba=categorias`
**Então** vejo "Comunicação visual" seguida de "Lonas" (que diz "em Comunicação visual"), o pai diz
quantas subcategorias tem, e não vejo nem a taxonomia de outro módulo nem a do vizinho. Sem
`category.view` a lista de categorias não vem.

Status: 🧪

---

## UC-PCADAP-10 · Categoria em uso não sai, e a tela sabe antes · `must`

Origem: UC-CAD-03 aplicado a Categorias · charter R4. Conta produto pela categoria **ou** pela subcategoria.

**Dado** que 1 produto está na subcategoria "ACM"
**Quando** abro a aba e depois tento excluir "ACM"
**Então** a linha traz `em_uso = 1` e o servidor recusa dizendo quantos usam — "ACM" continua lá.

Status: 🧪

---

## UC-PCADAP-11 · Categoria com subcategoria não sai · `must`

Origem: UC-CAD-11, **com a regra trocada** (ver `_saida-02`): o protótipo levava as filhas junto; o
legado apagava só o pai e deixava as filhas e os produtos apontando pra uma categoria apagada. Aqui o
servidor recusa e diz por quê.

**Dado** "Sinalização" com a subcategoria "Placas", sem produto
**Quando** tento excluir "Sinalização"
**Então** o servidor recusa dizendo que há 1 subcategoria dentro, e as duas continuam lá; excluída
"Placas", "Sinalização" sai.

Status: 🧪

> Decisão [W] 2026-10-01 (item 10): **recusar** é a regra — excluir o pai não leva as filhas junto.

---

## UC-PCADAP-12 · Editar unidade não mexe na conversão de estoque sem pedido explícito · `must`

Origem: decisão [W] 2026-10-01 (item 11, "pode corrigir"). Regra mestre de ESTOQUE: a conversão
(`base_unit_id` × `base_unit_multiplier`) muda quantidade em toda movimentação da unidade.

**Dado** "Caixa" = 1000 × "Unidade base" e "Meia" = 0,5 × "Unidade base"
**Quando** salvo a Caixa sem mandar o campo do múltiplo, abro o modal de edição, salvo sem mexer, e por fim desmarco o múltiplo
**Então** a base fica nos três primeiros passos (o modal mostra `1000` e `0,5`, não `1,000` e `1`), e só sai quando eu desmarco.

Status: 🧪

---

## UC-PCADAP-13 · As rotas de Variações, Grupos de preço e Garantias abrem a mesma tela · `must`

Origem: charter R1 · D3 [W] 2026-10-01 · ficha `03-cadastros.md` (thread 03).

**Dado** que tenho a permissão de cada aba
**Quando** abro `/variation-templates`, `/selling-price-group` ou `/warranties`
**Então** recebo Inertia `Produto/Cadastros/Index` na aba Variações, Grupos de preço ou Garantias — e o ajax
sem `X-Inertia` da tela clássica continua recebendo o JSON do DataTables.

Status: 🧪

---

## UC-PCADAP-14 · Variações, grupos e garantias só do meu negócio · `must` · [T0]

Origem: Anti-hook do charter (Tier 0) · UC-CAD-08 (valores em chips) · UC-CAD-13 (coluna Duração `24 meses`).

**Dado** que um negócio vizinho tem variação, grupo e garantia, e um produto dele aponta pro **meu** modelo de variação
**Quando** abro as abas
**Então** vejo só os meus registros, a variação traz os valores na linha, a garantia mostra `12 meses`, e a
contagem de uso da variação conta só produto do meu negócio (1, não 2).

Status: 🧪

---

## UC-PCADAP-15 · Variação em uso não sai · `must`

Origem: charter R4 · a tela clássica já escondia o Excluir de variação em uso, mas o servidor apagava.

**Dado** um modelo de variação usado por um produto meu e outro sem uso
**Quando** peço a exclusão dos dois
**Então** o servidor recusa o primeiro (`success: false`, nada apagado) e apaga o segundo.

Status: 🧪

---

## UC-PCADAP-16 · Permissão das abas novas · `must`

Origem: thread 01 (`variation.*`, `warranty.*`) · `SellingPriceGroupController`, que já cobrava `product.create`.

**Dado** um papel com `warranty.view` e `warranty.delete`, sem `product.create` nem `variation.view`
**Quando** abro a tela
**Então** a aba Garantias aparece **sem** Excluir (o `WarrantyController@destroy` nunca foi implementado), Grupos de
preço e Variações aparecem bloqueadas sem lista, e `/variation-templates` responde 403.

Status: 🧪

---

## UC-PCADAP-17 · Drawer de unidade grava o múltiplo como o operador digitou · `must`

Origem: UC-CAD-01/02 (charter R2) + ficha `10-cadastros-form.md` (PR-a) + decisão [W] 2026-10-01 item 11.
É ESTOQUE: o múltiplo converte toda movimentação da unidade.

**Dado** a unidade base "Und" do meu negócio
**Quando** crio pelo drawer "Meia" com `0,5` e "Caixa" com `1.000`, reabro cada uma no drawer e salvo sem mexer, e depois desligo o múltiplo da Caixa
**Então** grava 0,5 e 1000 pelas mesmas rotas do modal clássico (`POST /units`, `PUT /units/{id}`); o drawer abre com `0,5` e `1000` e salvar sem mexer não muda o valor; desligar manda `define_base_unit=0` e só aí a base sai.

Status: 🧪

---

## UC-PCADAP-18 · Unidade base de outro negócio, ou a própria, é recusada · `must` `[T0]`

Origem: ADR 0093 (Tier 0). O `base_unit_id` vem do form; antes desta thread o servidor gravava qualquer id.

**Dado** uma unidade do negócio vizinho e uma minha
**Quando** crio ou edito uma unidade apontando a base para a do vizinho, ou para ela mesma
**Então** o servidor responde `success: false` e não grava nada (nem a unidade nova, nem o nome trocado).

Status: 🧪

---

## UC-PCADAP-19 · Drawer de marca cria e edita só no meu negócio · `must` `[T0]`

Origem: UC-CAD-01/02 (charter R2) + UC-CAD-12 (marca da Oficina) + ADR 0093.

**Dado** uma marca do negócio vizinho
**Quando** crio e edito uma marca pelo drawer e tento editar a do vizinho pelo mesmo `PUT /brands/{id}`
**Então** a minha é criada e renomeada; a do vizinho fica como estava; a lista traz `oficina` por marca e a tela só oferece a chave da Oficina quando o módulo Repair está instalado.

Status: 🧪

---

## UC-PCADAP-20 · Subcategoria só sob categoria principal do meu negócio · `must` `[T0]`

Origem: UC-CAD-01/02 (charter R2, R7) + ficha `10-cadastros-form.md` (PR-b) + ADR 0093. O modal clássico
só lista categorias principais do negócio como pai; o servidor não conferia o `parent_id` recebido.

**Dado** "Comunicação visual" com a subcategoria "Lonas", "Impressos" sem filhas e uma categoria do vizinho
**Quando** crio pelo drawer uma subcategoria sob a principal, sob a do vizinho e sob "Lonas"; e edito
"Impressos" para ser filha dela mesma e "Comunicação visual" para ser filha de "Impressos"
**Então** só a primeira grava; as outras respondem `success: false` sem gravar nada, e nenhum vínculo existente muda.

Status: 🧪

---

## UC-PCADAP-21 · Drawer de variação renomeia valor e acrescenta valor novo · `must`

Origem: UC-CAD-01/02 (charter R2) + ficha `10-cadastros-form.md` (PR-b).

**Dado** o modelo "Cor" criado pelo drawer com Branco e Preto
**Quando** reabro, renomeio Branco para "Branco gelo" e acrescento Azul
**Então** a lista traz os ids dos valores; o valor existente é renomeado pelo id (`edit_variation_values`) e o novo
entra no fim. Valor já gravado não é removido pelo drawer: o servidor não remove valor de modelo, só renomeia.

Status: 🧪

---

## UC-PCADAP-22 · Drawer de garantia cria e edita só no meu negócio · `must` `[T0]`

Origem: UC-CAD-01/02 (charter R2) + ficha `10-cadastros-form.md` (PR-b) + ADR 0093.

**Dado** uma garantia do negócio vizinho
**Quando** crio "12 meses" pelo drawer, reabro e troco para 1 ano, e tento editar a do vizinho
**Então** a lista devolve o prazo cru (`12` e `months`) pro drawer abrir preenchido; a minha passa a 1 ano; a do vizinho fica como estava.

Status: 🧪

---

## Backlog (sem teste ainda — não é contrato até ganhar teste que o cite)

- [BACKLOG] Criar e editar Grupos de preço no drawer (UC-CAD-01/02) — fora da ficha da thread 10; segue no modal da Blade. As outras 5 abas estão no drawer (UC-PCADAP-17..22).
- [BACKLOG] Remover valor já gravado de um modelo de variação pelo drawer — o servidor só renomeia; remover pede rota e regra de uso.
- [BACKLOG] Desativar/ativar grupo de preço na própria linha (UC-CAD-09) — hoje a linha mostra Ativo/Inativo e a troca vai pela tela clássica.
- [BACKLOG] Contagem de produtos da variação clicável (filtro do índice por modelo de variação) — `/products/unificado` não tem esse filtro e é `nao_toca` da thread 03.
- [BACKLOG] Marca da Oficina na lista (UC-CAD-12), atalho `/` e busca sem resultado (UC-CAD-06/07), estados primeira-vez/carregando/densidade (UC-CAD-14..16) — a tela já tem `/`, busca e primeira-vez; falta teste de browser.
