---
id: dominios-wr-comercial-modulos-nfe-tabelas-nf-entrada-produtos-composicao
table: NF_ENTRADA_PRODUTOS_COMPOSICAO
module: nfe
created_at_version: 565
last_modified_version: 736
target_version: 1468
columns_count: 64
foreign_keys_count: 8
foreign_keys:
  CODFORMULA_PERFIL: FORMULA_PERFIL
  CODFORNECEDOR: FORNECEDOR
  CODNF_ENTRADA: NF_ENTRADA
  CODNF_ENTRADA_PRODUTOS: NF_ENTRADA_PRODUTOS
  CODPRODUTO: PRODUTO
  CODPRODUTO_COMPOSICAO: PRODUTO_COMPOSICAO
  CODPRODUTO_GRUPO: PRODUTO_GRUPO
  CODPRODUTO_LOTE: PRODUTO_LOTE
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_PRODUTOS_COMPOSICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 565;
- **Última mudança:** UPDATE 736;
- **Total colunas (versão 1468):** 64

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFORMULA_PERFIL` | [`FORMULA_PERFIL`](../../estoque/tabelas/FORMULA_PERFIL.md) |
| `CODFORNECEDOR` | [`FORNECEDOR`](../../cadastros/tabelas/FORNECEDOR.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODNF_ENTRADA_PRODUTOS` | [`NF_ENTRADA_PRODUTOS`](../../nfe/tabelas/NF_ENTRADA_PRODUTOS.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_COMPOSICAO` | [`PRODUTO_COMPOSICAO`](../../estoque/tabelas/PRODUTO_COMPOSICAO.md) |
| `CODPRODUTO_GRUPO` | [`PRODUTO_GRUPO`](../../estoque/tabelas/PRODUTO_GRUPO.md) |
| `CODPRODUTO_LOTE` | [`PRODUTO_LOTE`](../../estoque/tabelas/PRODUTO_LOTE.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v565 | v565 |
| 2 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | → `NF_ENTRADA` | v565 | v565 |
| 3 | `CODFABRICA` | `VARCHAR(60)` | NULL |  | v565 | v565 |
| 4 | `CODFORNECEDOR` | `VARCHAR(10)` | NULL | → `FORNECEDOR` | v565 | v565 |
| 5 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v565 | v565 |
| 6 | `CODPRODUTO_COMPOSICAO` | `VARCHAR(15)` | NULL | → `PRODUTO_COMPOSICAO` | v565 | v565 |
| 7 | `DESCRICAO` | `VARCHAR(300)` | NULL |  | v565 | v565 |
| 8 | `UNIDADE` | `VARCHAR(3)` | NULL |  | v565 | v565 |
| 9 | `COMP` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 10 | `LARG` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 11 | `ESPESSURA` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 12 | `QTDADEPECA` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 13 | `QUANT` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 14 | `CUSTO_FABR` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 15 | `CUSTO_MEDIO` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 16 | `MARGEM` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 17 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 18 | `LOCAL` | `VARCHAR(30)` | NULL |  | v565 | v565 |
| 19 | `CODPRODUTO_GRUPO` | `VARCHAR(15)` | NULL | → `PRODUTO_GRUPO` | v565 | v565 |
| 20 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v565 | v565 |
| 21 | `FRETE` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 22 | `CODPRODUTO_LOTE` | `INTEGER` | NULL | → `PRODUTO_LOTE` | v565 | v565 |
| 23 | `LOTE` | `VARCHAR(1)` | NULL |  | v565 | v565 |
| 24 | `CODIGOEAN` | `VARCHAR(60)` | NULL |  | v565 | v565 |
| 25 | `TOTAL` | `DOUBLE PRECISION` | NULL |  | v565 | v565 |
| 26 | `CUSTO_COMPOSICAO` | `double precision` | NULL |  | v610 | v610 |
| 27 | `QUANT_MATERIAL_COMPRADO` | `DOUBLE PRECISION` | NULL |  | v614 | v614 |
| 28 | `VALOR_COMPOSICAO` | `double precision` | NULL |  | v631 | v631 |
| 29 | `CUSTO_CENTRO_TRABALHO` | `double precision` | NULL |  | v631 | v631 |
| 30 | `CODCOMPOSICAO_BASE` | `INTEGER` | NULL |  | v673 | v673 |
| 31 | `CODNF_ENTRADA_PRODUTOS` | `INTEGER` | NULL | → `NF_ENTRADA_PRODUTOS` | v673 | v673 |
| 32 | `REF_CODIGO` | `INTEGER` | NULL |  | v674 | v674 |
| 33 | `REF_CODPRODUTO_COMPOSICAO` | `varchar(15)` | NULL |  | v674 | v674 |
| 34 | `REF_CODPRODUTO` | `varchar(15)` | NULL |  | v674 | v674 |
| 35 | `CUSTO_VENDA` | `DOUBLE PRECISION` | NULL |  | v675 | v675 |
| 36 | `CODFORMULA_PERFIL` | `INTEGER` | NULL | → `FORMULA_PERFIL` | v679 | v679 |
| 37 | `PESO` | `DOUBLE PRECISION` | NULL |  | v696 | v696 |
| 38 | `PERC_RATEIO_CUSTO_VENDA` | `DOUBLE PRECISION` | NULL |  | v696 | v696 |
| 39 | `CUSTO_VENDA_UNITARIO` | `double precision` | NULL |  | v719 | v719 |
| 40 | `CUSTO_VENDA_TOTAL` | `DOUBLE PRECISION` | NULL |  | v565 | v721 |
| 41 | `CUSTO_DIGITADO` | `DOUBLE PRECISION` | NULL |  | v721 | v721 |
| 42 | `PARENT` | `INTEGER` | NULL |  | v721 | v721 |
| 43 | `QTDADEPECA_FORMULA` | `varchar(500)` | NULL |  | v723 | v723 |
| 44 | `COMP_FORMULA` | `varchar(500)` | NULL |  | v723 | v723 |
| 45 | `LARG_FORMULA` | `varchar(500)` | NULL |  | v723 | v723 |
| 46 | `ESPESSURA_FORMULA` | `varchar(500)` | NULL |  | v723 | v723 |
| 47 | `CUSTO_PERC` | `double precision` | NULL |  | v723 | v723 |
| 48 | `CUSTO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 49 | `CUSTO_EXTRA` | `double precision` | NULL |  | v723 | v723 |
| 50 | `CUSTO_VENDA_EXTRA` | `double precision` | NULL |  | v723 | v723 |
| 51 | `CUSTO_VENDA_PERC` | `double precision` | NULL |  | v723 | v723 |
| 52 | `MARKUP` | `double precision` | NULL |  | v723 | v723 |
| 53 | `PERC_LUCRO_DESEJADO` | `double precision` | NULL |  | v723 | v723 |
| 54 | `PERC_MARGEM_CONTRIBUICAO` | `double precision` | NULL |  | v723 | v723 |
| 55 | `VALOR_LUCRO` | `double precision` | NULL |  | v723 | v723 |
| 56 | `COMP_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 57 | `LARG_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 58 | `ESPESSURA_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 59 | `QTDADEPECA_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 60 | `QUANT_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 61 | `TOTAL_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 62 | `VALOR_POR_PECA` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 63 | `CUSTO_VENDA_MINIMO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 64 | `COMPOSICAO` | `VARCHAR(1)` | NULL |  | v736 | v736 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 565 | CREATE | CREATE TABLE com 26 colunas |
| 610 | ADD_COL | + CUSTO_COMPOSICAO double precision |
| 614 | ADD_COL | + QUANT_MATERIAL_COMPRADO DOUBLE PRECISION |
| 631 | ADD_COL | + VALOR_COMPOSICAO double precision |
| 631 | ADD_COL | + CUSTO_CENTRO_TRABALHO double precision |
| 673 | ADD_COL | + CODCOMPOSICAO_BASE INTEGER |
| 673 | ADD_COL | + CODNF_ENTRADA_PRODUTOS INTEGER |
| 674 | ADD_COL | + REF_CODIGO INTEGER |
| 674 | ADD_COL | + REF_CODPRODUTO_COMPOSICAO varchar(15) |
| 674 | ADD_COL | + REF_CODPRODUTO varchar(15) |
| 675 | ADD_COL | + CUSTO_VENDA DOUBLE PRECISION |
| 679 | ADD_COL | + CODFORMULA_PERFIL INTEGER |
| 683 | ADD_COL | + PESO double precision |
| 686 | ADD_COL | + PERC_RATEIO_CUSTO_VENDA DOUBLE PRECISION |
| 696 | ADD_COL | + PESO DOUBLE PRECISION |
| 696 | ADD_COL | + PERC_RATEIO_CUSTO_VENDA DOUBLE PRECISION |
| 719 | ADD_COL | + CUSTO_VENDA_UNITARIO double precision |
| 721 | RENAME_COL | × CUSTO_LOJA → CUSTO_VENDA_TOTAL |
| 721 | ADD_COL | + CUSTO_DIGITADO DOUBLE PRECISION |
| 721 | ADD_COL | + PARENT INTEGER |
| 722 | ADD_COL | + QTDADEPECA_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + COMP_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + LARG_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + ESPESSURA_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + CUSTO_PERC DOUBLE PRECISION |
| 722 | RENAME_COL | × CUSTO_INICIAL → CUSTO |
| 722 | ADD_COL | + CUSTO DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_EXTRA DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_VENDA_EXTRA DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_VENDA_PERC DOUBLE PRECISION |
| 722 | ADD_COL | + MARKUP DOUBLE PRECISION |
| 722 | ADD_COL | + PERC_LUCRO_DESEJADO DOUBLE PRECISION |
| 722 | ADD_COL | + PERC_MARGEM_CONTRIBUICAO DOUBLE PRECISION |
| 722 | ADD_COL | + VALOR_LUCRO DOUBLE PRECISION |
| 722 | ADD_COL | + COMP_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + LARG_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + ESPESSURA_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + QTDADEPECA_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + QUANT_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + TOTAL_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + VALOR_POR_PECA DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_VENDA_MINIMO DOUBLE PRECISION |
| 723 | ADD_COL | + QTDADEPECA_FORMULA varchar(500) |
| 723 | ADD_COL | + COMP_FORMULA varchar(500) |
| 723 | ADD_COL | + LARG_FORMULA varchar(500) |
| 723 | ADD_COL | + ESPESSURA_FORMULA varchar(500) |
| 723 | ADD_COL | + CUSTO_PERC double precision |
| 723 | ADD_COL | + CUSTO_EXTRA double precision |
| 723 | ADD_COL | + CUSTO_VENDA_EXTRA double precision |
| 723 | ADD_COL | + CUSTO_VENDA_PERC double precision |
| 723 | ADD_COL | + MARKUP double precision |
| 723 | ADD_COL | + PERC_LUCRO_DESEJADO double precision |
| 723 | ADD_COL | + PERC_MARGEM_CONTRIBUICAO double precision |
| 723 | ADD_COL | + VALOR_LUCRO double precision |
| 736 | ADD_COL | + COMPOSICAO VARCHAR(1) |

