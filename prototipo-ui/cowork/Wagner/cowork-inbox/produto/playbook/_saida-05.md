---
sessao: "05"
titulo: "Produto/Importacao — saída da thread (PR-a: importar produtos)"
autor: "[CL]"
criado: 2026-10-01
base: 81141329b
thread: 07-importacao.md
veredito: "PR-a entregue — /import-products em Inertia com conferência dry-run antes de gravar; algoritmo de importação intocado e provado igual por dois caminhos. PR-b (estoque inicial) pendente — a prova do json sobre ImportOpeningStockController fica aberta."
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
