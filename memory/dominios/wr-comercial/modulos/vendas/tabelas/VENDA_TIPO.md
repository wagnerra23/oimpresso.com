---
table: VENDA_TIPO
module: vendas
created_at_version: 188
last_modified_version: 1382
target_version: 1468
columns_count: 16
foreign_keys_count: 2
foreign_keys:
  CODNF_NATUREZA_OPERACAO_PADRAO: NF_NATUREZA_OPERACAO
  CODPRODUTO_TABELA: PRODUTO_TABELA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_TIPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 188;
- **Última mudança:** UPDATE 1382;
- **Total colunas (versão 1468):** 16

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_NATUREZA_OPERACAO_PADRAO` | [`NF_NATUREZA_OPERACAO`](../../nfe/tabelas/NF_NATUREZA_OPERACAO.md) |
| `CODPRODUTO_TABELA` | [`PRODUTO_TABELA`](../../estoque/tabelas/PRODUTO_TABELA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TIPO_PADRAO` | `char` | NULL |  | v260 | v260 |
| 2 | `II_DADOSADICIONAIS_NFE` | `VARCHAR(1)` | NULL |  | v381 | v381 |
| 3 | `NF_FINALIDADE` | `varchar (1)` | NULL |  | v318 | v318 |
| 4 | `PODE_SER_FATURADO` | `VARCHAR(1)` | NULL |  | v347 | v347 |
| 5 | `MODELO` | `varchar (20)` | NULL |  | v381 | v381 |
| 6 | `NF_FRETE_POR_CONTA` | `VARCHAR(1)` | NULL |  | v403 | v403 |
| 7 | `PODE_SER_PRODUZIDO` | `VARCHAR(1)` | NULL |  | v461 | v461 |
| 8 | `PREVISAO` | `CHAR(1)` | NULL |  | v574 | v574 |
| 9 | `CODNF_NATUREZA_OPERACAO_PADRAO` | `INTEGER` | NULL | → `NF_NATUREZA_OPERACAO` | v662 | v662 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 11 | `PODE_EMITIR_NOTAFISCAL` | `VARCHAR(1)` | NULL |  | v796 | v796 |
| 12 | `PRODUTO_ESTOQUE_LOCAL` | `VARCHAR(15)` | NULL |  | v758 | v808 |
| 13 | `CODPRODUTO_TABELA` | `INTEGER` | NULL | → `PRODUTO_TABELA` | v811 | v811 |
| 14 | `OPERACAO` | `VARCHAR(50)` | NULL |  | v944 | v944 |
| 15 | `SUBNIVEL` | `VARCHAR(50)` | NULL |  | v1047 | v1047 |
| 16 | `PODE_IMPRIMIR` | `VARCHAR(1)` | NULL |  | v1382 | v1382 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 188 | ALTER_TYPE | ~ DESCRICAO TYPE varchar(60) |
| 228 | ADD_COL | + GERA_PAGTO varchar(1) |
| 228 | ADD_COL | + CODPLANOCONTAS_PAGTO varchar(15) |
| 260 | ADD_COL | + TIPO_PADRAO char |
| 263 | ADD_COL | + IMPORTACAO VARCHAR(1) |
| 276 | ADD_COL | + II_DADOSADICIONAIS_NFE VARCHAR(1) |
| 301 | ADD_COL | + IBPTAX_DADOSADICIONAIS_NFE VARCHAR(1) |
| 311 | ADD_COL | + INDUSTRIALIZACAO varchar(1) |
| 318 | ADD_COL | + NF_FINALIDADE varchar (1) |
| 347 | ADD_COL | + PODE_SER_FATURADO VARCHAR(1) |
| 381 | ADD_COL | + MODELO varchar (20) |
| 381 | DROP_COL | - IMPORTACAO |
| 381 | DROP_COL | - INDUSTRIALIZACAO |
| 381 | ADD_COL | + II_DADOSADICIONAIS_NFE VARCHAR(1) |
| 403 | ADD_COL | + NF_FRETE_POR_CONTA VARCHAR(1) |
| 461 | ADD_COL | + PODE_SER_PRODUZIDO VARCHAR(1) |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 574 | ADD_COL | + PREVISAO CHAR(1) |
| 662 | ADD_COL | + CODNF_NATUREZA_OPERACAO_PADRAO INTEGER |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 758 | DROP_COL | - ESTOQUE_LOCAL |
| 758 | ADD_COL | + ESTOQUE_LOCAL VARCHAR(15) |
| 796 | ADD_COL | + PODE_EMITIR_NOTAFISCAL VARCHAR(1) |
| 799 | DROP_COL | - CODPLANOCONTAS_PAGTO |
| 799 | DROP_COL | - IBPTAX_DADOSADICIONAIS_NFE |
| 801 | ADD_COL | + TEM_BLOQUEIO_NF VARCHAR(1) |
| 808 | RENAME_COL | × ESTOQUE_LOCAL → PRODUTO_ESTOQUE_LOCAL |
| 811 | ADD_COL | + CODPRODUTO_TABELA INTEGER |
| 815 | RENAME_COL | × ESTOQUE_LOCAL → PRODUTO_ESTOQUE_LOCAL |
| 830 | DROP_COL | - GERA_PAGTO |
| 944 | ADD_COL | + OPERACAO VARCHAR(50) |
| 1047 | ADD_COL | + SUBNIVEL VARCHAR(50) |
| 1061 | DROP_COL | - TEM_BLOQUEIO_NF |
| 1382 | ADD_COL | + PODE_IMPRIMIR VARCHAR(1) |

