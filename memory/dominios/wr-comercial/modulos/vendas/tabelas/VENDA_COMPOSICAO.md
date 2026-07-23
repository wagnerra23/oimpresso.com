---
id: dominios-wr-comercial-modulos-vendas-tabelas-venda-composicao
table: VENDA_COMPOSICAO
module: vendas
created_at_version: 57
last_modified_version: 749
target_version: 1468
columns_count: 96
foreign_keys_count: 8
foreign_keys:
  CODFORMULA_PERFIL: FORMULA_PERFIL
  CODLOCAL: LOCAL
  CODPRODUTO: PRODUTO
  CODPRODUTO_GRUPO: PRODUTO_GRUPO
  CODPRODUTO_ORIGEM: PRODUTO
  CODVENDA: VENDA
  CODVENDA_COMPOSICAO_BASE: VENDA_COMPOSICAO
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_COMPOSICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 57;
- **Última mudança:** UPDATE 749;
- **Total colunas (versão 1468):** 96

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFORMULA_PERFIL` | [`FORMULA_PERFIL`](../../estoque/tabelas/FORMULA_PERFIL.md) |
| `CODLOCAL` | [`LOCAL`](../../cadastros/tabelas/LOCAL.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_GRUPO` | [`PRODUTO_GRUPO`](../../estoque/tabelas/PRODUTO_GRUPO.md) |
| `CODPRODUTO_ORIGEM` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_COMPOSICAO_BASE` | [`VENDA_COMPOSICAO`](../../vendas/tabelas/VENDA_COMPOSICAO.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v57 | v57 |
| 2 | `CODVENDA` | `VARCHAR(10)` | NOT NULL | → `VENDA` | v57 | v57 |
| 3 | `CODVENDA_PRODUTO` | `INTEGER` | NOT NULL | → `VENDA_PRODUTO` | v57 | v57 |
| 4 | `PRODUTO` | `varchar (300)` | NULL |  | v57 | v163 |
| 5 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v57 | v57 |
| 6 | `QUANT` | `DOUBLE PRECISION` | NULL |  | v57 | v57 |
| 7 | `COMP` | `DOUBLE PRECISION` | NULL |  | v57 | v57 |
| 8 | `LARG` | `DOUBLE PRECISION` | NULL |  | v57 | v57 |
| 9 | `QTDADEPECA` | `DOUBLE PRECISION` | NULL |  | v57 | v57 |
| 10 | `APROVEITAMENTO` | `VARCHAR(5)` | NULL |  | v57 | v57 |
| 11 | `MEDIDAS` | `VARCHAR(100)` | NULL |  | v57 | v596 |
| 12 | `PATH` | `VARCHAR(255)` | NULL |  | v57 | v57 |
| 13 | `PASSADAS` | `INTEGER` | NULL |  | v57 | v57 |
| 14 | `REVERSO` | `VARCHAR(1)` | NULL |  | v57 | v57 |
| 15 | `COM_LOGO` | `VARCHAR(1)` | NULL |  | v57 | v57 |
| 16 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v613 | v613 |
| 17 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 18 | `ESPESSURA` | `DOUBLE PRECISION` | NULL |  | v123 | v123 |
| 19 | `CODPRODUTO_GRUPO` | `VARCHAR(15)` | NULL | → `PRODUTO_GRUPO` | v123 | v123 |
| 20 | `PARENT` | `INTEGER, ADD PRODUCAO SMALLINT, ADD PRIORIDADE SMALLINT, ADD CODSETOR INTEGER, ADD DT_PREVISAO_ENTREGA TIMESTAMP` | NULL |  | v128 | v128 |
| 21 | `UNIDADE` | `varchar (6)` | NULL |  | v160 | v160 |
| 22 | `ESTOQUE_LOCAL` | `varchar(15)` | NULL |  | v245 | v245 |
| 23 | `SERVICO_TERCEIROS` | `smallint` | NULL |  | v280 | v280 |
| 24 | `CUSTO_FABR` | `double precision` | NULL |  | v736 | v736 |
| 25 | `CODLOCAL` | `integer` | NULL | → `LOCAL` | v321 | v321 |
| 26 | `LOCAL` | `varchar (150)` | NULL |  | v321 | v321 |
| 27 | `VINCULO_COMP` | `varchar(1)` | NULL |  | v335 | v335 |
| 28 | `VINCULO_LARG` | `varchar(1)` | NULL |  | v335 | v335 |
| 29 | `VINCULO_ESPESSURA` | `varchar(1)` | NULL |  | v337 | v337 |
| 30 | `VINCULO_QTDADEPECA` | `varchar(1)` | NULL |  | v353 | v353 |
| 31 | `DT_PREVISAO_ENTREGA_TERCEIRO` | `TIMESTAMP` | NULL |  | v359 | v359 |
| 32 | `PESSOA_FORNECEDOR_CODIGO` | `VARCHAR(10)` | NULL |  | v371 | v371 |
| 33 | `STATUS` | `varchar(20)` | NULL |  | v376 | v376 |
| 34 | `ADIC_POSTERIORMENTE` | `integer` | NULL |  | v376 | v376 |
| 35 | `DESP_QUANT` | `double precision` | NULL |  | v376 | v376 |
| 36 | `DESP_COMP` | `double precision` | NULL |  | v376 | v376 |
| 37 | `DESP_LARG` | `double precision` | NULL |  | v376 | v376 |
| 38 | `DESP_QTDADEPECA` | `double precision` | NULL |  | v376 | v376 |
| 39 | `DESP_ESPESSURA` | `double precision` | NULL |  | v376 | v376 |
| 40 | `REAPROVEITADO` | `integer` | NULL |  | v376 | v376 |
| 41 | `QUANT_RETIRADO` | `double precision` | NULL |  | v376 | v376 |
| 42 | `OBS_PRODUCAO` | `blob sub_type 1 segment size 80` | NULL |  | v408 | v408 |
| 43 | `COBRANCA_UNICA` | `VARCHAR(1)` | NULL |  | v413 | v413 |
| 44 | `PERC_ADICIONA` | `DOUBLE PRECISION` | NULL |  | v427 | v427 |
| 45 | `FORMULA` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v478 | v478 |
| 46 | `PESO` | `DOUBLE PRECISION` | NULL |  | v724 | v724 |
| 47 | `ADICIONA_VALOR` | `varchar (1)` | NULL |  | v579 | v579 |
| 48 | `CUSTO_COMPOSICAO` | `double precision` | NULL |  | v610 | v610 |
| 49 | `QUANT_UNITARIO` | `DOUBLE PRECISION` | NULL |  | v615 | v615 |
| 50 | `NAO_MULTIPLICA_QUANT_PRODUTO` | `DOM_BOOLEAN` | NULL |  | v615 | v615 |
| 51 | `QUANT_DETALHE` | `varchar(100)` | NULL |  | v615 | v615 |
| 52 | `VINCULO_QUANT_UNITARIO` | `VARCHAR(1)` | NULL |  | v628 | v628 |
| 53 | `VINCULO_QUANT` | `VARCHAR(1)` | NULL |  | v628 | v628 |
| 54 | `VALOR_BASE` | `DOUBLE PRECISION` | NULL |  | v631 | v631 |
| 55 | `VALOR_COMPOSICAO` | `double precision` | NULL |  | v631 | v631 |
| 56 | `CUSTO_CENTRO_TRABALHO` | `double precision` | NULL |  | v631 | v631 |
| 57 | `MULTIPLICA_POR` | `VARCHAR(30)` | NULL |  | v633 | v633 |
| 58 | `MARGEM` | `DOUBLE PRECISION` | NULL |  | v742 | v742 |
| 59 | `CODVENDA_COMPOSICAO_BASE` | `INTEGER` | NULL | → `VENDA_COMPOSICAO` | v650 | v650 |
| 60 | `CUSTO_EXTRA` | `DOUBLE PRECISION` | NULL |  | v651 | v651 |
| 61 | `CUSTO_EXTRA_TOTAL` | `DOUBLE PRECISION` | NULL |  | v651 | v651 |
| 62 | `CODFORMULA_PERFIL` | `INTEGER` | NULL | → `FORMULA_PERFIL` | v655 | v655 |
| 63 | `NAO_RETORNA_ESTOQUE_AO_CANCELAR` | `DOM_BOOLEAN` | NULL |  | v659 | v659 |
| 64 | `CUSTO_VENDA` | `DOUBLE PRECISION` | NULL |  | v675 | v675 |
| 65 | `CUSTO_VENDA_UNITARIO` | `double precision` | NULL |  | v719 | v719 |
| 66 | `CUSTO_VENDA_TOTAL` | `double precision` | NULL |  | v186 | v721 |
| 67 | `COMPOSICAO` | `DOM_BOOLEAN` | NULL |  | v725 | v725 |
| 68 | `COMP_FORMULA` | `VARCHAR(500)` | NULL |  | v722 | v722 |
| 69 | `LARG_FORMULA` | `VARCHAR(500)` | NULL |  | v722 | v722 |
| 70 | `ESPESSURA_FORMULA` | `VARCHAR(500)` | NULL |  | v722 | v722 |
| 71 | `QTDADEPECA_FORMULA` | `VARCHAR(500)` | NULL |  | v722 | v722 |
| 72 | `COMP_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 73 | `LARG_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 74 | `ESPESSURA_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 75 | `QTDADEPECA_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 76 | `QUANT_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 77 | `CUSTO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 78 | `TOTAL_COMPOSICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 79 | `CUSTO_PERC` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 80 | `CUSTO_VENDA_PERC` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 81 | `CUSTO_VENDA_EXTRA` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 82 | `PERC_LUCRO_DESEJADO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 83 | `VALOR_POR_PECA` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 84 | `CUSTO_VENDA_MINIMO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 85 | `VALOR_LUCRO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 86 | `PERC_MARGEM_CONTRIBUICAO` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 87 | `MARKUP` | `DOUBLE PRECISION` | NULL |  | v722 | v722 |
| 88 | `TOTAL` | `double precision` | NULL |  | v723 | v723 |
| 89 | `VDESC` | `double precision` | NULL |  | v736 | v736 |
| 90 | `VOUTRO` | `double precision` | NULL |  | v736 | v736 |
| 91 | `PDESC` | `double precision` | NULL |  | v736 | v736 |
| 92 | `POUTRO` | `double precision` | NULL |  | v736 | v736 |
| 93 | `VALOR_RELATORIO` | `double precision` | NULL |  | v736 | v736 |
| 94 | `TOTAL_RELATORIO` | `double precision` | NULL |  | v736 | v736 |
| 95 | `VALOR_COMPRA` | `double precision` | NULL |  | v736 | v736 |
| 96 | `CODPRODUTO_ORIGEM` | `VARCHAR(15)` | NULL | → `PRODUTO` | v749 | v749 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 57 | CREATE | CREATE TABLE com 15 colunas |
| 64 | ADD_COL | + VALOR DOUBLE PRECISION |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 122 | ADD_COL | + ESPESSURA DOUBLE PRECISION |
| 123 | ADD_COL | + CODPRODUTO_GRUPO VARCHAR(15) |
| 123 | ADD_COL | + ESPESSURA DOUBLE PRECISION |
| 128 | ADD_COL | + PARENT INTEGER, ADD PRODUCAO SMALLINT, ADD PRIORIDADE SMALLINT, ADD CODSETOR INTEGER, ADD DT_PREVISAO_ENTREGA TIMESTAMP |
| 160 | ADD_COL | + UNIDADE varchar (6) |
| 163 | ALTER_TYPE | ~ PRODUTO TYPE varchar (300) |
| 183 | ALTER_TYPE | ~ MEDIDAS TYPE varchar(30) |
| 186 | ADD_COL | + CUSTO_LOJA double precision |
| 245 | ADD_COL | + ESTOQUE_LOCAL varchar(15) |
| 280 | ADD_COL | + SERVICO_TERCEIROS smallint |
| 282 | ADD_COL | + CUSTO_FABR DOUBLE PRECISION |
| 321 | ADD_COL | + CODLOCAL integer |
| 321 | ADD_COL | + LOCAL varchar (150) |
| 335 | ADD_COL | + VINCULO_COMP varchar(1) |
| 335 | ADD_COL | + VINCULO_LARG varchar(1) |
| 335 | ADD_COL | + VINCULO_ESPESSURA varchar(1) |
| 337 | ADD_COL | + VINCULO_ESPESSURA varchar(1) |
| 353 | ADD_COL | + VINCULO_QTDADEPECA varchar(1) |
| 359 | ADD_COL | + DT_PREVISAO_ENTREGA_TERCEIRO TIMESTAMP |
| 371 | ADD_COL | + PESSOA_FORNECEDOR_CODIGO VARCHAR(10) |
| 376 | ADD_COL | + STATUS varchar(20) |
| 376 | ADD_COL | + ADIC_POSTERIORMENTE integer |
| 376 | ADD_COL | + DESP_QUANT double precision |
| 376 | ADD_COL | + DESP_COMP double precision |
| 376 | ADD_COL | + DESP_LARG double precision |
| 376 | ADD_COL | + DESP_QTDADEPECA double precision |
| 376 | ADD_COL | + DESP_ESPESSURA double precision |
| 376 | ADD_COL | + REAPROVEITADO integer |
| 376 | ADD_COL | + QUANT_RETIRADO double precision |
| 408 | ADD_COL | + OBS_PRODUCAO blob sub_type 1 segment size 80 |
| 413 | ADD_COL | + COBRANCA_UNICA VARCHAR(1) |
| 427 | ADD_COL | + PERC_ADICIONA DOUBLE PRECISION |
| 478 | ADD_COL | + FORMULA BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 504 | ADD_COL | + PESO DOUBLE PRECISION |
| 579 | ADD_COL | + ADICIONA_VALOR varchar (1) |
| 596 | ALTER_TYPE | ~ MEDIDAS TYPE VARCHAR(100) |
| 610 | ADD_COL | + CUSTO_COMPOSICAO double precision |
| 613 | ADD_COL | + VALOR DOUBLE PRECISION |
| 615 | ADD_COL | + QUANT_UNITARIO DOUBLE PRECISION |
| 615 | ADD_COL | + NAO_MULTIPLICA_QUANT_PRODUTO DOM_BOOLEAN |
| 615 | ADD_COL | + QUANT_DETALHE varchar(100) |
| 628 | ADD_COL | + VINCULO_QUANT_UNITARIO VARCHAR(1) |
| 628 | ADD_COL | + VINCULO_QUANT VARCHAR(1) |
| 631 | ADD_COL | + VALOR_BASE DOUBLE PRECISION |
| 631 | ADD_COL | + VALOR_COMPOSICAO double precision |
| 631 | ADD_COL | + CUSTO_CENTRO_TRABALHO double precision |
| 633 | ADD_COL | + MULTIPLICA_POR VARCHAR(30) |
| 639 | ADD_COL | + MARGEM DOUBLE PRECISION |
| 650 | ADD_COL | + CODVENDA_COMPOSICAO_BASE INTEGER |
| 651 | ADD_COL | + CUSTO_EXTRA DOUBLE PRECISION |
| 651 | ADD_COL | + CUSTO_EXTRA_TOTAL DOUBLE PRECISION |
| 655 | ADD_COL | + CODFORMULA_PERFIL INTEGER |
| 659 | ADD_COL | + NAO_RETORNA_ESTOQUE_AO_CANCELAR DOM_BOOLEAN |
| 675 | ADD_COL | + CUSTO_VENDA DOUBLE PRECISION |
| 719 | ADD_COL | + CUSTO_VENDA_UNITARIO double precision |
| 721 | RENAME_COL | × CUSTO_LOJA → CUSTO_VENDA_TOTAL |
| 721 | ADD_COL | + COMPOSICAO DOM_BOOLEAN |
| 722 | ADD_COL | + COMP_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + LARG_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + ESPESSURA_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + QTDADEPECA_FORMULA VARCHAR(500) |
| 722 | ADD_COL | + COMP_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + LARG_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + ESPESSURA_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + QTDADEPECA_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + QUANT_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO DOUBLE PRECISION |
| 722 | ADD_COL | + TOTAL_COMPOSICAO DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_PERC DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_VENDA_PERC DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_VENDA_EXTRA DOUBLE PRECISION |
| 722 | ADD_COL | + PERC_LUCRO_DESEJADO DOUBLE PRECISION |
| 722 | ADD_COL | + VALOR_POR_PECA DOUBLE PRECISION |
| 722 | ADD_COL | + CUSTO_VENDA_MINIMO DOUBLE PRECISION |
| 722 | ADD_COL | + VALOR_LUCRO DOUBLE PRECISION |
| 722 | ADD_COL | + PERC_MARGEM_CONTRIBUICAO DOUBLE PRECISION |
| 722 | ADD_COL | + MARKUP DOUBLE PRECISION |
| 723 | ADD_COL | + TOTAL double precision |
| 724 | ADD_COL | + PESO DOUBLE PRECISION |
| 725 | ADD_COL | + COMPOSICAO DOM_BOOLEAN |
| 736 | ADD_COL | + VDESC double precision |
| 736 | ADD_COL | + VOUTRO double precision |
| 736 | ADD_COL | + PDESC double precision |
| 736 | ADD_COL | + POUTRO double precision |
| 736 | ADD_COL | + VALOR_RELATORIO double precision |
| 736 | ADD_COL | + TOTAL_RELATORIO double precision |
| 736 | ADD_COL | + CUSTO_FABR double precision |
| 736 | ADD_COL | + VALOR_COMPRA double precision |
| 740 | ADD_COL | + MARGEM DOUBLE PRECISION |
| 742 | ADD_COL | + MARGEM DOUBLE PRECISION |
| 749 | ADD_COL | + CODPRODUTO_ORIGEM VARCHAR(15) |

