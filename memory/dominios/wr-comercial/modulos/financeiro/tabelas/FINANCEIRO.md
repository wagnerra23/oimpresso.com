---
table: FINANCEIRO
module: financeiro
created_at_version: 9
last_modified_version: 1367
target_version: 1468
columns_count: 21
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO_CONTA: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 9;
- **Última mudança:** UPDATE 1367;
- **Total colunas (versão 1468):** 21

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO_CONTA` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `COMISSAO_PAGA` | `VARCHAR(1)` | NULL |  | v12 | v12 |
| 2 | `RECIBO_IMPRESSO` | `INTEGER` | NULL |  | v36 | v36 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 4 | `AGRUPADOR` | `integer` | NULL |  | v182 | v182 |
| 5 | `CREDITO` | `double precision` | NULL |  | v246 | v246 |
| 6 | `DT_EMISSAO_VENDA` | `TIMESTAMP` | NULL |  | v311 | v311 |
| 7 | `PREVISAO` | `double precision` | NULL |  | v381 | v381 |
| 8 | `DT_PREVISAO` | `timestamp` | NULL |  | v414 | v414 |
| 9 | `LANCAMENTO_FUTURO` | `varchar(1)` | NULL |  | v417 | v417 |
| 10 | `CHEQUE_COMPE` | `INTEGER` | NULL |  | v420 | v420 |
| 11 | `DT_COMPETENCIA` | `DATE` | NULL |  | v478 | v478 |
| 12 | `EM_EXTRATO` | `DOM_BOOLEAN` | NULL |  | v478 | v478 |
| 13 | `CONCILIADO` | `DOM_BOOLEAN` | NULL |  | v478 | v478 |
| 14 | `DT_CONCILIADO` | `timestamp` | NULL |  | v603 | v603 |
| 15 | `CODUSUARIO_CONTA` | `INTEGER` | NULL | → `USUARIO` | v607 | v607 |
| 16 | `TEM_CREDITO` | `VARCHAR(1)` | NULL |  | v860 | v860 |
| 17 | `ativo` | `VARCHAR(1)` | NULL |  | v1203 | v1203 |
| 18 | `COMISSAO_STATUS` | `VARCHAR(20)` | NULL |  | v1118 | v1118 |
| 19 | `IS_TRANSFERENCIA` | `VARCHAR(1)` | NULL |  | v1200 | v1200 |
| 20 | `PODE_ENVIAR` | `VARCHAR(1)` | NULL |  | v1312 | v1312 |
| 21 | `RETORNO` | `INTEGER` | NULL |  | v1367 | v1367 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 9 | DROP_COL | - CODCHEQUE |
| 9 | DROP_COL | - CHEQUE_CODBANCO |
| 9 | DROP_COL | - CHEQUE_BANCO |
| 9 | DROP_COL | - CHEQUE_NOME |
| 9 | DROP_COL | - CHEQUE_REPASSADO |
| 9 | DROP_COL | - CHEQUE_CNPJCPF |
| 9 | DROP_COL | - CHEQUE_STATUS |
| 9 | DROP_COL | - CHEQUE_COMPE |
| 9 | DROP_COL | - CHEQUE_AGENCIA |
| 9 | DROP_COL | - CHEQUE_CONTA |
| 9 | DROP_COL | - CHEQUE_C1 |
| 9 | DROP_COL | - CHEQUE_C2 |
| 9 | DROP_COL | - CHEQUE_C3 |
| 9 | DROP_COL | - CHEQUE_DT_CADASTRO |
| 9 | DROP_COL | - CHEQUE_DT_BOM_PARA |
| 9 | DROP_COL | - CHEQUE_DT_REPASSADO |
| 9 | DROP_COL | - CHEQUE_TIPO |
| 9 | DROP_COL | - CHEQUE_DEVOLVIDO |
| 9 | DROP_COL | - CHEQUE_MOTIVO |
| 9 | DROP_COL | - BOLETO_CARTEIRA |
| 9 | DROP_COL | - BOLETO_TIPO |
| 9 | DROP_COL | - BOLETO_ESPECIE |
| 9 | DROP_COL | - BOLETO_DEMONSTRATIVO |
| 9 | DROP_COL | - BOLETO_ABATIMENTO |
| 9 | DROP_COL | - BOLETO_JUROS_MORA |
| 9 | DROP_COL | - BOLETO_MULTA |
| 9 | DROP_COL | - BOLETO_DESCONTO |
| 9 | DROP_COL | - BOLETO_PROTESTO |
| 9 | DROP_COL | - BOLETO_ACEITE |
| 9 | DROP_COL | - BOLETO_OCORENCIA |
| 9 | DROP_COL | - BOLETO_REMESSA |
| 9 | DROP_COL | - BOLETO_RETORNO |
| 9 | DROP_COL | - BOLETO_TIPOOCORRENCIA |
| 12 | ALTER_TYPE | ~ HISTORICO TYPE VARCHAR(600) CHARACTER SET NONE |
| 12 | ADD_COL | + COMISSAO_PAGA VARCHAR(1) |
| 36 | ADD_COL | + RECIBO_IMPRESSO INTEGER |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 182 | ADD_COL | + AGRUPADOR integer |
| 227 | ALTER_TYPE | ~ RAZAOSOCIAL TYPE varchar (150) |
| 246 | ADD_COL | + CREDITO double precision |
| 302 | ADD_COL | + DT_EMISSAO_VENDA TIMESTAMP |
| 311 | ADD_COL | + DT_EMISSAO_VENDA TIMESTAMP |
| 362 | ALTER_TYPE | ~ MOTIVO_EXCLUSAO TYPE VARCHAR(1000) |
| 381 | ADD_COL | + PREVISAO double precision |
| 414 | ADD_COL | + DT_PREVISAO timestamp |
| 417 | ADD_COL | + LANCAMENTO_FUTURO varchar(1) |
| 420 | ADD_COL | + CHEQUE_COMPE INTEGER |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 444 | ALTER_TYPE | ~ MOTIVO_EXCLUSAO TYPE VARCHAR(1000) |
| 478 | ADD_COL | + DT_COMPETENCIA DATE |
| 478 | ADD_COL | + EM_EXTRATO DOM_BOOLEAN |
| 478 | ADD_COL | + CONCILIADO DOM_BOOLEAN |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 603 | ADD_COL | + DT_CONCILIADO timestamp |
| 607 | ADD_COL | + CODUSUARIO_CONTA INTEGER |
| 618 | ADD_COL | + IS_CREDITO DOM_BOOLEAN |
| 629 | DROP_COL | - IS_CREDITO |
| 758 | ALTER_TYPE | ~ CONDICAOPAGTO TYPE VARCHAR(50) |
| 758 | DROP_COL | - BOLETO_ABATIMENTO |
| 758 | DROP_COL | - BOLETO_ACEITE |
| 758 | DROP_COL | - BOLETO_CARTEIRA |
| 758 | DROP_COL | - BOLETO_DEMONSTRATIVO |
| 758 | DROP_COL | - BOLETO_DESCONTO |
| 758 | DROP_COL | - BOLETO_ESPECIE |
| 758 | DROP_COL | - BOLETO_JUROS_MORA |
| 758 | DROP_COL | - BOLETO_MULTA |
| 758 | DROP_COL | - BOLETO_PROTESTO |
| 758 | DROP_COL | - BOLETO_TIPO |
| 758 | DROP_COL | - CHEQUE_AGENCIA |
| 758 | DROP_COL | - CHEQUE_REPASSADO |
| 758 | DROP_COL | - CHEQUE_CNPJCPF |
| 758 | DROP_COL | - CHEQUE_STATUS |
| 758 | DROP_COL | - CHEQUE_C1 |
| 758 | DROP_COL | - CHEQUE_CONTA |
| 758 | DROP_COL | - CHEQUE_C2 |
| 758 | DROP_COL | - CHEQUE_C3 |
| 758 | DROP_COL | - CHEQUE_DT_CADASTRO |
| 758 | DROP_COL | - CHEQUE_DT_BOM_PARA |
| 758 | DROP_COL | - CHEQUE_DT_REPASSADO |
| 758 | DROP_COL | - CHEQUE_DEVOLVIDO |
| 758 | DROP_COL | - CHEQUE_MOTIVO |
| 758 | DROP_COL | - CHEQUE_BANCO |
| 758 | DROP_COL | - CHEQUE_CODBANCO |
| 758 | DROP_COL | - CHEQUE_NOME |
| 758 | DROP_COL | - CHEQUE_TIPO |
| 758 | DROP_COL | - CODCHEQUE |
| 860 | ADD_COL | + TEM_CREDITO VARCHAR(1) |
| 1090 | ADD_COL | + ATIVO VARCHAR(1) |
| 1118 | ADD_COL | + COMISSAO_STATUS VARCHAR(20) |
| 1200 | ADD_COL | + IS_TRANSFERENCIA VARCHAR(1) |
| 1203 | ADD_COL | + ativo VARCHAR(1) |
| 1312 | ADD_COL | + PODE_ENVIAR VARCHAR(1) |
| 1347 | ADD_COL | + RETORNO INTEGER |
| 1367 | ADD_COL | + RETORNO INTEGER |

