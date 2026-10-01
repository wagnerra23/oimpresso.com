---
sessao: "_saida-08"
thread: "08 · Catálogo duplicado — aposentar um dos dois"
dono: "[CL]"
data: 2026-10-01
tipo: entregue PARCIAL (index + show redirecionam; generateQr fica)
base_lida: wagnerra23/oimpresso.com@main 0f0ff8a8e
---
# _saida-08

## Decisão aplicada
D3 = **ProductCatalogue sobrevive** ([W] 2026-10-01, `_DECISOES-W-2026-10-01.md`). O
índice ainda marca D3 como `respondida: false`, porque o json é do Cowork e o Code não o
edita. Enquanto o Cowork não aplicar aquela edição, o placar mostra esta thread como
`pendente · decisão D3`.

## Comparação feita ANTES de redirecionar
`OfficeimpressoController` (index/show/generateQr) × `ProductCatalogueController` + `CatalogueService` + `ProductCatalogueRepository`:

| view | dados | escopo de business | autenticação/gate | veredito |
|---|---|---|---|---|
| `index` | iguais: produtos `ProductForSales` da location, descontos vigentes, categorias | `business_id` + location do business nos dois | OI: login. PC: **público** (throttle 30/min) | redireciona |
| `show` | iguais: mesmas relações, group prices, desconto por variação, combo | `business_id` nos dois | OI: login. PC: público | redireciona |
| `generateQr` | iguais | sessão nos dois | OI: `officeimpresso_module` **ou** superadmin. PC: `productcatalogue_module` **ou** superadmin | **fica** |

As views das duas pastas `catalogue/` são idênticas (`diff -r` sem diferença). O QR gerado
pelo Officeimpresso já apontava para a rota pública do ProductCatalogue (`url('catalogue/…')`),
e o `index` do Officeimpresso já linkava o `show` do ProductCatalogue. Redirecionar `index` e
`show` não perde dado nem acesso, porque o destino é público e a origem exigia login.

## O que entregou
- `OfficeimpressoController::index` e `::show` passam a devolver um 302 para a ação
  equivalente do ProductCatalogue, levando os parâmetros e a query string (o `?location_id=`
  do `show` decide quais descontos aparecem). Saiu um 302, e não um 301, porque a decisão é
  recente e um 301 ficaria preso no cache do browser se ela for revertida.
- O `generateQr` ficou intocado, com um docblock que diz por que ele fica.
- Teste `Modules/Officeimpresso/Tests/Feature/CatalogoRedirectTest.php`, com 4 casos: redirect
  do index com query, redirect do show com `location_id`, a rota do QR ainda mapeada para
  `OfficeimpressoController@generateQr`, e o QR respondendo 200 sem desviar. O arquivo entrou
  na allowlist da lane `officeimpresso-pest.yml`, porque sem isso ele não rodaria no CI. Esse
  é o único arquivo tocado fora do prefixo da thread.

## Provas do json
`provas: []`. A `nota_provas` pede para registrar qual controller perdeu as views: **o
`OfficeimpressoController` perdeu `index` e `show`**. O `generateQr` continua nele.

## Pendente: decisão [W]
O `generateQr` só pode ser aposentado depois que [W] resolver a questão de pacote
(`officeimpresso_module` × `productcatalogue_module`):
- **(a)** aceitar o 403 para quem só tem `officeimpresso_module`; ou
- **(c)** ajustar os pacotes no superadmin, para que quem tem `officeimpresso_module` também
  tenha `productcatalogue_module`, e só depois redirecionar.

NÃO MEDI quantos negócios têm `officeimpresso_module` sem `productcatalogue_module`. Isso
exigiria consultar o banco de produção, e esta sessão não fez essa consulta.

## PR
Ver o corpo do PR desta branch (`claude/officeimpresso-thread-08`).
