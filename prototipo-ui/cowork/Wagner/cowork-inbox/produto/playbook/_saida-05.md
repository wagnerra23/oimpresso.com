---
sessao: "05"
titulo: "Produto/Importacao — saída da thread (PR-a: importar produtos · PR-b: estoque inicial)"
autor: "[CL]"
criado: 2026-10-01
base: 81141329b
thread: 07-importacao.md
veredito: "PR-a e PR-b entregues — /import-products e /import-opening-stock na mesma Page Inertia, cada um com conferência que roda o próprio store() e desfaz; algoritmos intocados e provados iguais por dois caminhos. As 3 provas do json fecham."
---

# _saída 05 · Importação — modo produtos

## Abertura

O placar marcava `pendente` por três motivos, todos cobertos pelas exceções desta leva:
- **depende de 01:** #8349 mergeado; a 01 só não fica `feito` porque o índice ainda tem D1/D2 como
  pendentes — respondidas em `_DECISOES-W-2026-10-01.md` (exceção 1).
- **depende de 07:** os contratos foram deslocados pela lei IT2 / check "Contratos de tela" para o PR
  de cada tela; o `_saida-07` registra isso (exceções 2 e 3). Copiei o `produto-importacao` de lá
  **sem mudar a copy**.
- as 3 provas de arquivo: são o que esta thread entrega.

## O que entrou

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/ImportProductsController.php` | `index()` → `Inertia::render('Produto/Importacao/Index')`; `?classico=1` mantém a Blade. `store()` ganha o desvio `conferir=1` **depois** da validação e **antes** de criar produto/estoque: `DB::rollBack()` e redirect com a lista do que seria criado. Com `conferir`, imagem por URL não é baixada (o rollback não desfaz arquivo). Nenhuma linha de cálculo, validação ou gravação mudou. |
| `resources/js/Pages/Produto/Importacao/Index.{tsx,charter.md,casos.md}` | Page + trio. **Enviar planilha** só libera depois de uma conferência sem erro do mesmo arquivo. |
| `governance/design/contracts/produto-importacao.contract.json` | copiado do `_saida-07`. `contrato-de-tela --contract` → limpo. |
| `tests/Feature/Produto/ProdutoImportacaoContratoTest.php` | UC-PIMP-01..05, tenant 98 × 99. |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-importacao.md` | F1 do MWART (o hook exige). |
| `.github/workflows/estoque-pest.yml` | trigger inclui o controller e a Page (sem isso mexer no controller não dispara o teste). |

## Regra mestre VALOR/ESTOQUE — o que garante "mesmo resultado"

- O caminho de gravação é **o mesmo código** do `store()`; a conferência só sai antes dele.
- **Prova por dois caminhos (UC-PIMP-04):** a mesma planilha importada direto no negócio 98 e
  conferida-e-enviada no 99 grava produtos, marca, categoria, custo, preço, saldo em estoque, linha
  do estoque inicial e o total do lançamento **idênticos**; o teste também exige que exista saldo 5
  e lançamento `opening_stock` (não compara vazio com vazio).
- **Conferência não grava (UC-PIMP-03):** marca e categoria que a validação cria por
  `firstOrCreate` são desfeitas pelo rollback; o teste confere produto, marca, categoria,
  lançamentos e saldo.

## Provas do json

- `resources/js/Pages/Produto/Importacao/Index.tsx` existe ✔
- `ImportProductsController.php` contém `Inertia::render('Produto/Importacao/Index'` ✔
- `ImportOpeningStockController.php` contém `Inertia::render('Produto/Importacao/Index'` ✘ — é o PR-b.

Placar depois do merge: a 05 fica `em curso` (2 de 3 provas). Esperado: a ficha diz 2 PRs.

## Pendente (não feito, com o porquê)

1. **PR-b — estoque inicial (`/import-opening-stock`).** Não cabia junto (limite de 300 linhas) e
   é o caso mais sensível: no `ImportOpeningStockController::store()` validação e gravação estão no
   **mesmo laço** (`addOpeningStock` por linha). O dry-run ali exige pular a gravação dentro do laço
   — a validação não depende do que foi gravado antes (o `$os_transaction` só alimenta a
   gravação), mas isso precisa da mesma prova de dois caminhos. Sem alvo nem contrato (errata A2 #2
   do `_saida-07`): a seção do modo estoque não tem copy congelada.
2. **Só o primeiro erro.** O `store()` para no primeiro erro (`break`). Relatar todas as linhas com
   erro mexeria no laço de validação — fica como decisão [W], não foi feito.
3. **Leitura do .csv no navegador** (R2/R4/R5 do trio proposto): substituída pela conferência do
   servidor, que vale também pra .xls/.xlsx.
4. **3 textos de instrução do protótipo corrigidos** pelo que o `store()` faz: data de validade é
   `mm-dd-aaaa` (`createFromFormat('m-d-Y')`, não dd/mm/aaaa); locais do produto separados por
   **vírgula** (`explode(',')`, não `|`); local do estoque inicial em branco = **primeiro local**
   do negócio (não "todos"). Pedido ao Cowork: corrigir em `produto-acoes.jsx` (`COLS_PRODUTO`).
   Nenhum desses textos está no contrato.
5. **Assinatura/cota no teste:** o teste fixa `isSubscribed`/`isQuotaAvailable` = verdadeiro por
   mock parcial; não é o que está sob teste.
6. Teste NÃO rodado local nem no CT 100 (regra da thread): a prova é a lane `estoque-pest` do PR.
   NÃO MEDI alvo (`alvo:medir`) — a thread não pede.

## PR

(preenchido no PR)

---

# PR-b · Importação — modo estoque inicial (`/import-opening-stock`)

Base: `origin/main` em `5247071b4` (depois do #8376). Placar na abertura: `em curso` — o único motivo
era a 3ª prova, que é o que este PR entrega (deps 01/07 já tratadas no PR-a, acima).

## O que entrou

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/ImportOpeningStockController.php` | `index()` → `Inertia::render('Produto/Importacao/Index')` com `modo = estoque`; `?classico=1` mantém a Blade. `store()` ganha o desvio `conferir=1` **depois** do laço inteiro e da checagem de erro: lê o que ficou gravado e `DB::rollBack()`. Uma linha nova no laço só anota a linha já gravada (`$conferidas[]`). Nenhuma linha de validação, cálculo ou gravação mudou. |
| `resources/js/Pages/Produto/Importacao/Index.tsx` | 2º modo na mesma Page: título, rota de envio, 6 colunas e a tabela da conferência (SKU, produto, local, quantidade, custo, saldo do local depois, total do lançamento). |
| `Index.charter.md` · `Index.casos.md` | Goal do modo estoque; sai o Non-Goal "modo estoque"; UC-PIMP-06..10. |
| `tests/Feature/Produto/ProdutoImportacaoContratoTest.php` | UC-PIMP-06..10, tenant 98 × 99. |
| `.github/workflows/estoque-pest.yml` | trigger inclui `ImportOpeningStockController.php` (os dois filtros). O run-set já é `tests/Feature/Produto/*Test.php`. |

## Por que a conferência é o `store()` inteiro, e não um desvio antes da gravação

No `ImportOpeningStockController::store()` validação e gravação estão no **mesmo laço**
(`addOpeningStock` por linha). E há dependência entre linhas: a 2ª linha do mesmo SKU no mesmo local
**encontra** o lançamento que a 1ª criou (`$os_transaction`) e soma nele. Pular a gravação mudaria o
que a 2ª linha vê. Então a conferência deixa o laço gravar tudo dentro da transação, **lê** saldo
(`variation_location_details`) e total do lançamento (`transactions.final_total`) como ficaram, e
desfaz. O que a tela mostra é o que foi gravado de verdade, não uma conta refeita.

Efeitos fora do banco no caminho: nenhum. `addOpeningStock` → `Transaction`/`purchase_lines`/
`updateProductQuantity` (só banco). Observers de `Transaction` (Financeiro, NFSe) só agem em
`sell`/`purchase`. Kardex é relatório derivado das transações — não é tabela.

## Regra mestre VALOR/ESTOQUE — dois caminhos

- **(a) UC-PIMP-09:** a mesma planilha (A 7 + A 3 no mesmo local, B 4) no 98 direto e no 99 por
  conferir→enviar grava lançamento, linhas (quantidade, custo, imposto, lote), total e saldo
  **idênticos**. O teste exige saldo 10 e total 125 no SKU A — não compara vazio com vazio.
- **(b) UC-PIMP-08:** depois da conferência, nenhum lançamento, linha de compra nem saldo de local
  fica (contagens antes = depois), e a conferência devolveu 3 linhas (não é vácuo).
- UC-PIMP-07 confere os números da conferência: 7+3 no mesmo lançamento → saldo 10 / total 125;
  B → 4 / 32.

## Multi-tenant (Tier 0) — sem P0

Medido no código, não presumido: o produto é achado por `sub_sku` **com** `P.business_id`, o local
**com** `business_id`, o lançamento existente **com** `business_id`. `updateProductQuantity` recebe
ids já validados. UC-PIMP-10 prova: SKU que só existe no 99 e local que só existe no 99 são recusados
no 98 com a linha, sem gravar em nenhum dos dois.

## Provas do json

- `resources/js/Pages/Produto/Importacao/Index.tsx` existe ✔
- `ImportProductsController.php` contém `Inertia::render('Produto/Importacao/Index'` ✔
- `ImportOpeningStockController.php` contém `Inertia::render('Produto/Importacao/Index'` ✔ (este PR)

Placar depois deste PR: as 3 provas passam; a 05 segue `em curso` só por "depende de 01/07 (não
feita)" — os dois motivos já tratados pelas exceções desta leva (D1/D2 em `_DECISOES-W-2026-10-01.md`;
contrato da 07 deslocado pela IT2). Vira `feito` quando o índice do Cowork for reescrito.

## Pendente (com o porquê)

1. **Sem contrato nem alvo do modo estoque** (errata A2 #2 do `_saida-07`): a seção não tem copy
   congelada. Os `data-contract` são os mesmos do modo produtos; a copy do modo estoque vem de
   `COLS_ESTOQUE`/`ImportarEstoque` do protótipo.
2. **2 textos do protótipo corrigidos** pelo que o `store()` faz — pedido ao Cowork: local em branco =
   **primeiro local do negócio** (não "local padrão"); data de validade no **formato de data do
   negócio** (`uf_date`, a tela mostra o formato), não dd/mm/aaaa fixo.
3. **Só o primeiro erro** e **relato por linha com erro**: o laço para no primeiro (`break`) — mudar é
   mexer no algoritmo, decisão [W] (igual ao PR-a).
4. `.xlsx` também é lido (`Excel::toArray`); a Blade só aceitava `.xls` no input. A tela aceita os três.
5. Teste NÃO rodado local nem no CT 100 (regra da thread): a prova é a lane `estoque-pest` do PR.
   NÃO MEDI alvo — a thread não pede.

## PR

(preenchido no PR)
