---
table: VENDA_FINANCEIRO_TEF
module: vendas
created_at_version: 1143
last_modified_version: 1169
target_version: 1468
columns_count: 19
foreign_keys_count: 1
foreign_keys:
  CODVENDA: VENDA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_FINANCEIRO_TEF`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1143;
- **Última mudança:** UPDATE 1169;
- **Total colunas (versão 1468):** 19

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1143 | v1143 |
| 2 | `CODVENDA` | `VARCHAR(10)` | NOT NULL | → `VENDA` | v1143 | v1143 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1143 | v1143 |
| 4 | `NSU` | `VARCHAR(100)` | NULL |  | v1153 | v1153 |
| 5 | `TEF_STATUS` | `VARCHAR(20)` | NULL |  | v1153 | v1153 |
| 6 | `REDE` | `VARCHAR(20)` | NULL |  | v1153 | v1153 |
| 7 | `VALOR_TOTAL` | `DOUBLE PRECISION` | NULL |  | v1153 | v1153 |
| 8 | `QTD_PARCELAS` | `INTEGER` | NULL |  | v1153 | v1153 |
| 9 | `CNPJ_CREDENCIADORA` | `VARCHAR(20)` | NULL |  | v1153 | v1153 |
| 10 | `ARQ_RESPOSTA` | `BLOB SUB_TYPE 1 SEGMENT SIZE 8192` | NULL |  | v1153 | v1153 |
| 11 | `ARQ_IMPRESSAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 8192` | NULL |  | v1155 | v1155 |
| 12 | `NSU_TRANSACAO_CANCELADA` | `VARCHAR(100)` | NULL |  | v1155 | v1155 |
| 13 | `TIPO` | `VARCHAR(20)` | NULL |  | v1155 | v1155 |
| 14 | `DATA_HORA_TRANSACAO_COMPROVANTE` | `TIMESTAMP` | NULL |  | v1155 | v1155 |
| 15 | `DATA_HORA_TRANSACAO_CANCELADA` | `TIMESTAMP` | NULL |  | v1155 | v1155 |
| 16 | `MODALIDADE_PAGTO_DESCRITA` | `VARCHAR(1000)` | NULL |  | v1155 | v1155 |
| 17 | `MODALIDADE_PAGTO_EXTENSO` | `VARCHAR(1000)` | NULL |  | v1155 | v1155 |
| 18 | `INSTITUICAO` | `VARCHAR(50)` | NULL |  | v1155 | v1155 |
| 19 | `MOTIVO` | `VARCHAR(250)` | NULL |  | v1169 | v1169 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1143 | CREATE | CREATE TABLE com 53 colunas |
| 1151 | DROP_COL | - VALOR |
| 1151 | DROP_COL | - DOCUMENTO |
| 1151 | DROP_COL | - VENCTO |
| 1151 | DROP_COL | - STATUS |
| 1151 | DROP_COL | - TIPO |
| 1151 | DROP_COL | - DATA |
| 1151 | DROP_COL | - RAZAOSOCIAL |
| 1151 | DROP_COL | - HISTORICO |
| 1151 | DROP_COL | - EMISSAO |
| 1151 | DROP_COL | - DATAPAGTO |
| 1151 | DROP_COL | - CODPLANOCONTAS |
| 1151 | DROP_COL | - TIPOPAGTO |
| 1151 | DROP_COL | - CODCONDICAOPAGTO |
| 1151 | DROP_COL | - CONDICAOPAGTO |
| 1151 | DROP_COL | - CONTATOS |
| 1151 | DROP_COL | - CODCHEQUE |
| 1151 | DROP_COL | - CHEQUE_CODBANCO |
| 1151 | DROP_COL | - CHEQUE_BANCO |
| 1151 | DROP_COL | - CHEQUE_NOME |
| 1151 | DROP_COL | - CHEQUE_REPASSADO |
| 1151 | DROP_COL | - CHEQUE_CNPJCPF |
| 1151 | DROP_COL | - CHEQUE_STATUS |
| 1151 | DROP_COL | - CHEQUE_COMPE |
| 1151 | DROP_COL | - CHEQUE_AGENCIA |
| 1151 | DROP_COL | - CHEQUE_C1 |
| 1151 | DROP_COL | - CHEQUE_CONTA |
| 1151 | DROP_COL | - CHEQUE_NUMERO |
| 1151 | DROP_COL | - CHEQUE_C2 |
| 1151 | DROP_COL | - CHEQUE_C3 |
| 1151 | DROP_COL | - CHEQUE_DT_CADASTRO |
| 1151 | DROP_COL | - CHEQUE_DT_BOM_PARA |
| 1151 | DROP_COL | - CHEQUE_DT_REPASSADO |
| 1151 | DROP_COL | - CHEQUE_TIPO |
| 1151 | DROP_COL | - ATUALIZADO |
| 1151 | DROP_COL | - CODCONTA |
| 1151 | DROP_COL | - PESSOA_RESPONSAVEL_CODIGO |
| 1151 | DROP_COL | - PESSOA_RESPONSAVEL_TIPO |
| 1151 | DROP_COL | - PESSOA_RESPONSAVEL_SEQUENCIA |
| 1151 | DROP_COL | - PESSOA_FORNECEDOR_CODIGO |
| 1151 | DROP_COL | - PESSOA_FORNECEDOR_TIPO |
| 1151 | DROP_COL | - PESSOA_FORNECEDOR_SEQUENCIA |
| 1151 | DROP_COL | - PARCELA |
| 1151 | DROP_COL | - PREVISAO |
| 1151 | DROP_COL | - GERADO_DO_FINANCEIRO |
| 1151 | DROP_COL | - PARCELA_ALTERADA |
| 1151 | DROP_COL | - NSU |
| 1151 | DROP_COL | - REDE |
| 1151 | DROP_COL | - CNPJ_CREDENCIADORA |
| 1151 | DROP_COL | - TEF_STATUS |
| 1151 | DROP_COL | - LANCAMENTO_FUTURO |
| 1153 | ADD_COL | + NSU VARCHAR(100) |
| 1153 | ADD_COL | + TEF_STATUS VARCHAR(20) |
| 1153 | ADD_COL | + REDE VARCHAR(20) |
| 1153 | ADD_COL | + VALOR_TOTAL DOUBLE PRECISION |
| 1153 | ADD_COL | + QTD_PARCELAS INTEGER |
| 1153 | ADD_COL | + CNPJ_CREDENCIADORA VARCHAR(20) |
| 1153 | ADD_COL | + ARQ_RESPOSTA BLOB SUB_TYPE 1 SEGMENT SIZE 8192 |
| 1155 | ADD_COL | + ARQ_IMPRESSAO BLOB SUB_TYPE 1 SEGMENT SIZE 8192 |
| 1155 | ADD_COL | + NSU_TRANSACAO_CANCELADA VARCHAR(100) |
| 1155 | ADD_COL | + TIPO VARCHAR(20) |
| 1155 | ADD_COL | + DATA_HORA_TRANSACAO_COMPROVANTE TIMESTAMP |
| 1155 | ADD_COL | + DATA_HORA_TRANSACAO_CANCELADA TIMESTAMP |
| 1155 | ADD_COL | + MODALIDADE_PAGTO_DESCRITA VARCHAR(1000) |
| 1155 | ADD_COL | + MODALIDADE_PAGTO_EXTENSO VARCHAR(1000) |
| 1155 | ADD_COL | + INSTITUICAO VARCHAR(50) |
| 1169 | ADD_COL | + MOTIVO VARCHAR(250) |

