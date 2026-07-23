---
id: dominios-wr-comercial-modulos-equipamento-tabelas-equipamento-rateio-financeiro
table: EQUIPAMENTO_RATEIO_FINANCEIRO
module: equipamento
created_at_version: 1119
last_modified_version: 1385
target_version: 1468
columns_count: 56
foreign_keys_count: 5
foreign_keys:
  CODCONDICAOPAGTO: CONDICAOPAGTO
  CODCONTA: CONTAS
  CODEQUIPAMENTO: EQUIPAMENTO
  CODEQUIPAMENTO_RATEIO: EQUIPAMENTO_RATEIO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_RATEIO_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1119;
- **Última mudança:** UPDATE 1385;
- **Total colunas (versão 1468):** 56

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONDICAOPAGTO` | [`CONDICAOPAGTO`](../../financeiro/tabelas/CONDICAOPAGTO.md) |
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEQUIPAMENTO` | [`EQUIPAMENTO`](../../equipamento/tabelas/EQUIPAMENTO.md) |
| `CODEQUIPAMENTO_RATEIO` | [`EQUIPAMENTO_RATEIO`](../../equipamento/tabelas/EQUIPAMENTO_RATEIO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1119 | v1119 |
| 2 | `CODEQUIPAMENTO_RATEIO` | `VARCHAR(10)` | NOT NULL | → `EQUIPAMENTO_RATEIO` | v1119 | v1119 |
| 3 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v1119 | v1119 |
| 4 | `DOCUMENTO` | `VARCHAR(20)` | NULL |  | v1119 | v1119 |
| 5 | `VENCTO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 6 | `STATUS` | `VARCHAR(20) default 0` | NULL |  | v1119 | v1119 |
| 7 | `TIPO` | `VARCHAR(10)` | NULL |  | v1119 | v1119 |
| 8 | `DATA` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 9 | `RAZAOSOCIAL` | `VARCHAR(150)` | NULL |  | v1119 | v1119 |
| 10 | `HISTORICO` | `VARCHAR(100)` | NULL |  | v1119 | v1119 |
| 11 | `EMISSAO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 12 | `DATAPAGTO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 13 | `CODPLANOCONTAS` | `VARCHAR(30)` | NULL | → `PLANOCONTAS` | v1119 | v1119 |
| 14 | `TIPOPAGTO` | `VARCHAR(50)` | NULL |  | v1119 | v1119 |
| 15 | `CODCONDICAOPAGTO` | `INTEGER` | NULL | → `CONDICAOPAGTO` | v1119 | v1119 |
| 16 | `CONDICAOPAGTO` | `VARCHAR(30)` | NULL |  | v1119 | v1119 |
| 17 | `CONTATOS` | `VARCHAR(400)` | NULL |  | v1119 | v1119 |
| 18 | `CODCHEQUE` | `INTEGER` | NULL |  | v1119 | v1119 |
| 19 | `CHEQUE_CODBANCO` | `INTEGER` | NULL |  | v1119 | v1119 |
| 20 | `CHEQUE_BANCO` | `VARCHAR(50)` | NULL |  | v1119 | v1119 |
| 21 | `CHEQUE_NOME` | `VARCHAR(50)` | NULL |  | v1119 | v1119 |
| 22 | `CHEQUE_REPASSADO` | `VARCHAR(50)` | NULL |  | v1119 | v1119 |
| 23 | `CHEQUE_CNPJCPF` | `VARCHAR(18)` | NULL |  | v1119 | v1119 |
| 24 | `CHEQUE_STATUS` | `VARCHAR(10)` | NULL |  | v1119 | v1119 |
| 25 | `CHEQUE_COMPE` | `INTEGER` | NULL |  | v1119 | v1119 |
| 26 | `CHEQUE_AGENCIA` | `INTEGER` | NULL |  | v1119 | v1119 |
| 27 | `CHEQUE_C1` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 28 | `CHEQUE_CONTA` | `VARCHAR(15)` | NULL |  | v1119 | v1119 |
| 29 | `CHEQUE_NUMERO` | `VARCHAR(20)` | NULL |  | v1119 | v1119 |
| 30 | `CHEQUE_C2` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 31 | `CHEQUE_C3` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 32 | `CHEQUE_DT_CADASTRO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 33 | `CHEQUE_DT_BOM_PARA` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 34 | `CHEQUE_DT_REPASSADO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 35 | `CHEQUE_TIPO` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 36 | `ATUALIZADO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 37 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 38 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v1119 | v1119 |
| 39 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v1119 | v1119 |
| 40 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v1119 | v1119 |
| 41 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v1119 | v1119 |
| 42 | `PESSOA_FORNECEDOR_CODIGO` | `VARCHAR(10)` | NULL |  | v1119 | v1119 |
| 43 | `PESSOA_FORNECEDOR_TIPO` | `VARCHAR(3)` | NULL |  | v1119 | v1119 |
| 44 | `PESSOA_FORNECEDOR_SEQUENCIA` | `INTEGER` | NULL |  | v1119 | v1119 |
| 45 | `PARCELA` | `INTEGER` | NULL |  | v1119 | v1119 |
| 46 | `PREVISAO` | `DOUBLE PRECISION` | NULL |  | v1119 | v1119 |
| 47 | `GERADO_DO_FINANCEIRO` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 48 | `PARCELA_ALTERADA` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 49 | `NSU` | `VARCHAR(100)` | NULL |  | v1119 | v1119 |
| 50 | `REDE` | `VARCHAR(50)` | NULL |  | v1119 | v1119 |
| 51 | `CNPJ_CREDENCIADORA` | `VARCHAR(20)` | NULL |  | v1119 | v1119 |
| 52 | `TEF_STATUS` | `VARCHAR(20)` | NULL |  | v1119 | v1119 |
| 53 | `VALOR_DIA` | `DOUBLE PRECISION` | NULL |  | v1119 | v1119 |
| 54 | `QUANT_DIA` | `INTEGER` | NULL |  | v1119 | v1119 |
| 55 | `CODEQUIPAMENTO` | `INTEGER` | NULL | → `EQUIPAMENTO` | v1119 | v1119 |
| 56 | `VALOR_SUB` | `DOUBLE PRECISION` | NULL |  | v1385 | v1385 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1119 | CREATE | CREATE TABLE com 55 colunas |
| 1385 | ADD_COL | + VALOR_SUB DOUBLE PRECISION |

