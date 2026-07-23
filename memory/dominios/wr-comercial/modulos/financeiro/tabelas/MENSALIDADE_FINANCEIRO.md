---
id: dominios-wr-comercial-modulos-financeiro-tabelas-mensalidade-financeiro
table: MENSALIDADE_FINANCEIRO
module: financeiro
created_at_version: 489
last_modified_version: 1468
target_version: 1468
columns_count: 29
foreign_keys_count: 5
foreign_keys:
  CODCONDICAOPAGTO: CONDICAOPAGTO
  CODCONTA: CONTAS
  CODEQUIPAMENTO: EQUIPAMENTO
  CODMENSALIDADE: MENSALIDADE
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSALIDADE_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 489;
- **Última mudança:** UPDATE 1468;
- **Total colunas (versão 1468):** 29

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONDICAOPAGTO` | [`CONDICAOPAGTO`](../../financeiro/tabelas/CONDICAOPAGTO.md) |
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEQUIPAMENTO` | [`EQUIPAMENTO`](../../equipamento/tabelas/EQUIPAMENTO.md) |
| `CODMENSALIDADE` | [`MENSALIDADE`](../../financeiro/tabelas/MENSALIDADE.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DESCONTO_ACRESCIMO` | `DOUBLE PRECISION` | NULL |  | v489 | v489 |
| 2 | `CODIGO` | `INTEGER` | NOT NULL |  | v489 | v489 |
| 3 | `CODMENSALIDADE` | `INTEGER` | NOT NULL | → `MENSALIDADE` | v489 | v489 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v489 | v489 |
| 5 | `DOCUMENTO` | `VARCHAR(20)` | NULL |  | v489 | v489 |
| 6 | `DT_VENCTO` | `TIMESTAMP` | NULL |  | v489 | v489 |
| 7 | `STATUS` | `VARCHAR(20)` | NULL |  | v489 | v489 |
| 8 | `TIPO` | `VARCHAR(10)` | NULL |  | v489 | v489 |
| 9 | `RAZAOSOCIAL` | `VARCHAR(150)` | NULL |  | v489 | v489 |
| 10 | `HISTORICO` | `VARCHAR(100)` | NULL |  | v489 | v489 |
| 11 | `DT_EMISSAO` | `TIMESTAMP` | NULL |  | v489 | v489 |
| 12 | `TIPOPAGTO` | `VARCHAR(30)` | NULL |  | v489 | v489 |
| 13 | `CODCONDICAOPAGTO` | `INTEGER` | NULL | → `CONDICAOPAGTO` | v489 | v489 |
| 14 | `CONDICAOPAGTO` | `VARCHAR(30)` | NULL |  | v489 | v489 |
| 15 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v489 | v489 |
| 16 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v489 | v489 |
| 17 | `CODPLANOCONTAS` | `VARCHAR(30) CHARACTER SET WIN1252` | NULL | → `PLANOCONTAS` | v489 | v499 |
| 18 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v489 | v489 |
| 19 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v489 | v489 |
| 20 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v489 | v489 |
| 21 | `CODPEDIDO` | `VARCHAR(50)` | NULL |  | v1459 | v1459 |
| 22 | `VALOR_CONTRIBUICAO_ASSOCIADO` | `DOUBLE PRECISION` | NULL |  | v1461 | v1461 |
| 23 | `VALOR_OUTRAS_CONTRIBUICOES` | `DOUBLE PRECISION` | NULL |  | v1461 | v1461 |
| 24 | `CODEQUIPAMENTO` | `INTEGER` | NULL | → `EQUIPAMENTO` | v1461 | v1461 |
| 25 | `PLACA` | `VARCHAR(10)` | NULL |  | v1461 | v1461 |
| 26 | `MARCAMODELO` | `VARCHAR(50)` | NULL |  | v1461 | v1461 |
| 27 | `MODELO` | `VARCHAR(50)` | NULL |  | v1461 | v1461 |
| 28 | `ANO` | `VARCHAR(10)` | NULL |  | v1461 | v1461 |
| 29 | `EMAIL` | `VARCHAR(500) CHARACTER SET NONE` | NULL |  | v1465 | v1468 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 260 | ADD_COL | + DESCONTO_ACRESCIMO double precision |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 489 | CREATE | CREATE TABLE com 20 colunas |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 1459 | ADD_COL | + CODPEDIDO VARCHAR(50) |
| 1460 | ADD_COL | + VALOR_CONTRIBUICAO_ASSOCIADO DOUBLE PRECISION |
| 1460 | ADD_COL | + VALOR_OUTRAS_CONTRIBUICOES DOUBLE PRECISION |
| 1461 | ADD_COL | + CODEQUIPAMENTO INTEGER |
| 1461 | ADD_COL | + VALOR_CONTRIBUICAO_ASSOCIADO DOUBLE PRECISION |
| 1461 | ADD_COL | + VALOR_OUTRAS_CONTRIBUICOES DOUBLE PRECISION |
| 1461 | ADD_COL | + PLACA VARCHAR(10) |
| 1461 | ADD_COL | + MARCAMODELO VARCHAR(50) |
| 1461 | ADD_COL | + MODELO VARCHAR(50) |
| 1461 | ADD_COL | + ANO VARCHAR(10) |
| 1465 | ADD_COL | + EMAIL VARCHAR(50) |
| 1468 | ALTER_TYPE | ~ EMAIL TYPE VARCHAR(500) CHARACTER SET NONE |

