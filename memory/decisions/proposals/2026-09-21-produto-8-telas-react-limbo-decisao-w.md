---
title: "As 8 telas React do Produto — ligar, rota paralela (US-PROD-029), ou trancar a porta? E a reconciliação 023 ↔ 029"
status: accepted
date: "2026-09-21"
decidido_em: "2026-09-21"
decisao: "SIM, entra na navegação — aplicar as duas linhas e a reconciliação ([W], textual)"
decisores: [Wagner (decidiu), Felipe (decidiu a 029 em 2026-08-24), Claude Code (autor)]
parent_module: Produto
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0344-two-strikes-cobre-processo
related_specs:
  - memory/requisitos/Produto/SPEC.md (US-PROD-020, 023, 029)
related_charters:
  - resources/js/Pages/Produto/Create.charter.md
  - resources/js/Pages/Produto/Edit.charter.md
  - resources/js/Pages/Produto/Unificado/Index.charter.md
origem: "Chip aberto no #7522 (fix do writer de estoque) — a correção pousou num caminho que ninguém percorre, e mexer no Blade de produção foi o preço. O enquadramento do chip dizia que as telas React são INALCANÇÁVEIS; a medição desta sessão mostra que são NÃO-LINKADAS, e que existe uma porta viva e sem gate."
---

# As 8 telas React do Produto — o que a medição mudou na pergunta

> ## ⚠️ DECIDIDO e ERRATA (2026-09-21, no mesmo dia)
>
> **[W] respondeu: SIM, a Consulta entra na navegação** — e mandou aplicar as duas linhas e a
> reconciliação. Ao ir aplicar, **medi e derrubei uma afirmação minha deste documento**. Ela
> fica registrada, não apagada:
>
> **ERRADO (o que eu escrevi e publiquei em PR):** *"uma rota sem `can:`"*, *"superfície Tier-0
> viva e sem dono"*, e a recomendação de adicionar `middleware('can:product.view')`.
>
> **MEDIDO:** `ProdutoUnificadoController:139` **já aborta com 403** e aceita
> `product.view` **OU** `product.create` — e o comentário ao lado declara a intenção:
> *"a semântica canônica **NÃO é middleware** … porque quem pode cadastrar produto precisa
> alcançar o catálogo"*. Tem UC próprio (`UC-PUNI-06`) **com teste**
> (`ProdutoUnificadoContratoTest.php:340`). Eu afirmei "sem gate" lendo a **ausência de
> middleware** na rota, sem abrir o corpo do controller — a classe [LC-08](../../LICOES_CODE.md),
> e a mesma forma da §5 2026-09-08 (*afirmar sobre a interação de duas defesas tendo lido uma*).
>
> **Consequência prática — a "linha 1" estava invertida:** aplicar o middleware seria
> **REGRESSÃO** (trancaria quem tem `product.create` e não tem `product.view`, exatamente o
> caso que o controller garante). ⚠️ **Mas a minha justificativa tinha o mecanismo errado:** eu escrevi que "o teste do UC-PUNI-06 cairia" — **falso, medido**. Ele revoga **as duas** permissions antes de exigir `403`, então o `403` do middleware satisfaz o assert; e o caminho `create`-only não tem cobertura. A regressão seria real **e passaria VERDE**. O `TODO [CL]` em
> `routes/web.php:699` é uma **instrução em canon que produz regressão se obedecida** — o
> conserto certo é **corrigir o TODO**, não cumpri-lo. Ver §3 reescrito.
>
> O resto do documento — a porta de entrada, a circularidade, o reponte do BulkEdit e a
> reconciliação 023↔029 — **sobreviveu à medição** e está aplicado.

> **O que eu resolvi e o que é seu.** A técnica (como partir a US, qual writer é de quem)
> eu resolvi medindo, e está proposta abaixo. **Uma coisa era sua e só sua:** a Consulta
> `/products/unificado` entra na navegação? — **respondida: SIM** ([W] 2026-09-21).

## 1. A correção do enquadramento (e é o que mais importa aqui)

O chip afirmava: *"Elas são INALCANÇÁVEIS hoje… os 11 `<Link>` que existem são circulares,
sem porta de entrada. O que roda em produção é o Blade."*

**A metade da circularidade está certa, e eu reproduzi.** Medido em `origin/main` hoje:
**14 navegações Inertia** (não 11) mirando `/products`, e **14 de 14 estão dentro de
`resources/js/Pages/Produto/`** — nenhuma página fora do Produto navega pra lá. O único
ponteiro externo (`Manufacturing/Settings.tsx:167`) é um `<a href="/products">` **puro**, que
não manda header e cai no Blade: ele *confirma* a tese, não a refuta.

**Mas "sem porta de entrada" está errado, e por um mecanismo que muda a decisão.** A porta
não é um `<Link>` — é uma **rota**:

`ProdutoUnificadoController:180` faz `Inertia::render('Produto/Unificado/Index', …)`
**sem bifurcar por header**. Numa primeira requisição comum o Inertia devolve a página React
inteira — o header só importa nas visitas *seguintes*. Logo `/products/unificado` **é** uma
tela React alcançável digitando a URL. E ela liga no resto do cacho:

| passo | arquivo:linha | o que acontece |
|---|---|---|
| entra | `routes/web.php:700` | `/products/unificado` — render Inertia incondicional (o gate de permissão existe, mas **dentro** do controller: `:139`, `abort(403)`, `view` OU `create`) |
| → cadastrar | `Unificado/Index.tsx:721` e `:1323` | `router.visit('/products/create')` — **manda** `X-Inertia` |
| → editar | `Unificado/_components/DetalheProduto.tsx:363` | `router.visit('/products/{id}/edit')` — **manda** `X-Inertia` |
| React renderiza | `ProductController` (8 sites de `header('X-Inertia')`) | pega o ramo Inertia → `Produto/Create` / `Produto/Edit` |
| grava | `Edit.tsx:122` → `put('/products/{id}')` | cai no **`update()` COMPARTILHADO** com a Larissa |

**A palavra certa é NÃO-LINKADA, não inalcançável.** Ninguém chega clicando: medi que
nenhuma entrada de navegação aponta pra `/products/unificado` (todos os hits de "unificado"
em código de menu são do **Financeiro**, `/financeiro/unificado`), e a entrada "Produtos" da
sidebar recebe o `href` do menu legado e usa `<a href>` puro — ou seja, Blade.

## 2. Por que a distinção decide o caso

1. **O #7522 não protegeu caminho morto.** Ele fechou um bug num writer que qualquer um com
   a URL alcança. A conclusão do chip ("corrigi um defeito num caminho que ninguém percorre")
   estava certa sobre o *tráfego* e errada sobre a *exposição*.
2. ~~**Hoje existe superfície Tier-0 viva e sem dono:** uma rota sem `can:`…~~ — **REFUTADO
   por mim mesmo na aplicação; ver a errata no topo.** O gate existe (`:139`, `abort(403)`,
   `product.view` **OU** `product.create`, com UC e teste). O que sobra de verdadeiro, e é bem
   mais modesto: um caminho de escrita **vivo e sem tráfego** — protegido por permissão, mas
   exercitado por ninguém, logo sem smoke real que o cubra.
3. **Há corroboração independente de que ninguém as usa, e é dura:** `BulkEdit.tsx:138` salva
   em `POST /products/mass-update`, e essa rota **não existe** — medido com controle positivo:
   `mass-update` em `routes/` dá **zero** (rc=1), `bulk-update` existe (`routes/web.php:715`).
   O botão Salvar daquela tela responde erro. Um usuário real notaria no primeiro clique.
   Pior: `BulkEdit.casos.md:41` registra **decisão [W] de 2026-07-27 mandando repontar a
   tela**, e a linha do `.tsx` nunca foi aplicada — ~2 meses.

## 3. As "duas linhas" — uma inverteu na medição, a outra destravou

### Linha 1 — ~~adicionar `can:product.view` na rota~~ → **corrigir o TODO que manda fazer isso**

Medido ao ir aplicar (detalhe na errata do topo): o gate **já existe** dentro do controller,
com semântica `product.view` **OU** `product.create`, declarada em comentário, coberta por
`UC-PUNI-06` e **com teste**. Pôr `middleware('can:product.view')` na rota seria **mais
estrito que o controller** e trancaria quem só tem `product.create` — regressão. ⚠️ E
**nenhum teste a pegaria**, medido: o único que exercita o gate
(`ProdutoUnificadoContratoTest.php:340`) revoga **as duas** permissions antes de exigir
`403`, logo o `403` do middleware satisfaz o assert igual; e o caminho `create`-only tem
**zero** cobertura no unificado (os 3 testes que concedem só `product.create` não citam a
rota). A regressão passaria VERDE — o que **agrava** o TODO em vez de aliviar.

O defeito real é o **`TODO [CL]` de `routes/web.php:699`**: ele instrui a próxima sessão a
cometer essa regressão. Vira comentário que aponta pro gate verdadeiro. Aplicado em PR
próprio (só `routes/`, mudança de comentário, zero delta de comportamento).

### Linha 2 — **repontar `BulkEdit.tsx` pra `/products/bulk-update`** ✅ destravada

A decisão [W] é de **2026-07-27**, e o canon repetia em 3 lugares (`casos.md:41`,
`charter.md:85`, `RUNBOOK:82`) que a linha do `.tsx` *"viaja junto com o `UC-PBULK-05` —
sozinha não faz a tela salvar"*, porque o writer lia 5 chaves sem `??` e revertia o lote.

**Re-medido hoje (§5 2026-09-03 — gap declarado em canon tem prazo de validade):** o
**[#7523](https://github.com/wagnerra23/oimpresso.com/pull/7523) entregou o `UC-PBULK-05` em
2026-09-18** (*"writer deriva chave ausente"*, eixo VALOR aprovado por [W] sob a REGRA MESTRE).
A pré-condição está satisfeita — o reponte agora **completa** a cadeia em vez de ficar órfão.
Payload conferido: `bulkUpdate()` lê `input('products')` e itera `['variations']`, exatamente
a forma que a tela monta.

⚠️ **O que o reponte NÃO faz:** a flag `enable_product_bulk_edit` segue **`false`**
(`config/constants.php:84`, upstream *"Will be depreciated in future"*), então o operador não
alcança o botão pela lista Blade. O reponte remove a mina; **não** liga a feature. O destino
dela segue decisão sua, como o `PARIDADE-charter-vs-legado.md:222` já registra.

## 4. A reconciliação 023 ↔ 029 — a ordem declarada é o inverso da intenção

O SPEC hoje diz, ao mesmo tempo:

- **US-PROD-029** `blocked_by: US-PROD-023` — *faça a 023 primeiro*;
- e, no corpo da 029: *"Aquela US promove as 8 telas assumindo o controller compartilhado de
  hoje. Esta decisão **muda o desenho dela** para o cadastro."*

Se a 029 redesenha o cadastro, fazer a 023 antes é **produzir o trabalho que a 029 descarta**.
A dependência está apontando pro lado contrário da intenção.

**A saída não é escolher entre as duas — é partir a 023 pelo critério que a medição oferece:
qual writer a tela toca.** Medido, submit por submit:

| tela | escreve? | writer | dona |
|---|---|---|---|
| `Index`, `Show`, `StockHistory`, `Unificado/Index` | não | — (read-only) | **023** |
| `BulkEdit` | sim | `bulk-update` (writer próprio; hoje apontando pra rota inexistente) | **023** |
| `SellingPrices` | sim | `save-selling-prices` (writer próprio) | **023** |
| `Create` | sim | `POST /products` → **`store()` compartilhado** | **029** |
| `Edit` | sim | `PUT /products/{id}` → **`update()` compartilhado** | **029** |

**São 2 de 8** as telas que encostam no caminho de gravação da Larissa — exatamente as 2 que
motivaram a 029. As outras 6 podem andar sem tocar nele.

**Proposta de edição no SPEC** (não aplicada — aguarda sua decisão):

1. Escopo da **US-PROD-023** passa a ser **6 telas** (Index, Show, StockHistory,
   Unificado/Index, BulkEdit, SellingPrices). Create/Edit saem.
2. **US-PROD-029** ganha Create/Edit como escopo próprio e **perde o
   `blocked_by: US-PROD-023`** — com o cadastro fora da 023, não há mais dependência.
3. O aceite da 023 mantém o `can:` e absorve o reponte do BulkEdit.

Isso remove a circularidade sem que nenhuma das duas US precise ser cancelada.

## 5. A pergunta — RESPONDIDA

> **A Consulta `/products/unificado` entra na navegação?**
> ### ✅ **SIM** — [W] 2026-09-21: *"sim, entra na navegação — aplica as duas linhas e a reconciliação"*

Consequência: as 6 telas da 023 **ganham usuário**, e terminá-las deixa de ser especulativo.
A Consulta é **aditiva** (não substitui `/products`), então ligá-la não tem cutover F5 nem
canary.

⚠️ **O fio que eu NÃO puxei, e por que:** *ligar na navegação* é trabalho de execução da
US-PROD-023, não uma das "duas linhas" — e fazê-lo **hoje** exporia a Larissa a 7 telas
`draft` que, pelo 🔴 do próprio BRIEFING, *"ainda não cobrem toda a função de produto simples,
variável e combo já disponível no Blade"*. A decisão está registrada e o item entrou no aceite
da 023; o cabeamento acontece quando as telas estiverem prontas. Se você quiser o link **já**,
mesmo com as telas em `draft`, isso é uma palavra sua e eu ligo.

Sobre a direção do cadastro (Create/Edit) eu **não** recomendo reabrir a 029: a decisão de
Felipe de 2026-08-24 é recente, foi tomada com a medição certa, e a sua de **2026-09-18**
(*"agora é o Protótipo quem manda, e a paridade deve ser o objetivo"*, no #7553) aponta pro
mesmo lugar — a 029 é o *como* chegar na paridade sem tocar o caminho da Larissa. O que eu
registro como custo conhecido, e a própria 029 já assume de olhos abertos, é que ela troca
*"1 writer, 2 chamadores"* por *"2 writers"* — e o histórico medido deste módulo mostra que o
arranjo atual já custou 3 PRs (#4943 revertido pelo #4994, depois #7522). O mitigante está no
aceite dela (Service compartilhado **ou** declarar no código por que as duas existem); ele não
é opcional.

## 6. Recibos

Rodado em `origin/main` (fast-forward nesta sessão, `0 0` vs `origin/main`), 2026-09-21:

| o que | comando | resultado |
|---|---|---|
| navegação Inertia pra `/products` | `git grep -nE "(Link\|router\.(visit\|get)\|route\()" origin/main -- '*.tsx'` filtrado por `/products` | **14**, todas em `Pages/Produto/` |
| a porta é incondicional | `git grep -nE "X-Inertia\|Inertia::render" origin/main -- app/Http/Controllers/ProdutoUnificadoController.php` | 1 hit: `Inertia::render`, **nenhum** `X-Inertia` |
| bifurcação no controller do cadastro | `git grep -c "header('X-Inertia')" origin/main -- app/Http/Controllers/ProductController.php` | **8** sites |
| writer de cada Page | `git show origin/main:<page>` + grep de `post(`/`put(`/`patch(` | tabela do §4 |
| o 404 do BulkEdit | `git grep -n "mass-update" origin/main -- routes/` | **rc=1 (zero)** |
| controle positivo do anterior | `git grep -n "bulk-update" origin/main -- routes/` | rc=0, `routes/web.php:715` |
| estado dos charters | `git ls-tree -r --name-only origin/main resources/js/Pages/Produto/` + `status:` de cada | **7 `draft` + 1 `live`** (Index) |
| ninguém linka a Consulta | `git grep -n "unificado" origin/main` em código de menu (PHP/TSX) | só `/financeiro/unificado` |

## 7. Resíduo declarado

- **Não medi** se algum consumidor externo (bookmark, e-mail, atalho do navegador da Larissa)
  já aponta pra `/products/unificado`. O repo não responde isso; quem responde é o log de
  acesso em produção.
- A afirmação *"ninguém chega clicando"* vale pro **código servido** que eu varri
  (`resources/views/`, `resources/js/`, menu em PHP). Um link colado fora do repo não aparece
  nessa varredura.
- **Não tratei** a `US-PROD-020` (que hoje bloqueia a 023). Ela segue como está; a reconciliação
  do §4 não depende dela.
- **Não liguei a navegação** — razão e condição no §5.
- **Não medi** o `whats-active` na volta pra aplicar: o servidor MCP estava desconectado. Usei o
  fallback de git que a §5 2026-09-05 nomeia (`git log HEAD..origin/main -- <alvo>`), que é piso,
  não teto — sessão irmã sem push segue invisível.
- **O que FOI aplicado** (o pedido de [W] em 2026-09-21): a reconciliação do §4 no `SPEC.md`
  (neste PR) · o reponte do `BulkEdit.tsx` + os 3 textos de canon que afirmavam "não aplicado"
  (PR próprio) · o conserto do `TODO` de `routes/web.php` (PR próprio).
