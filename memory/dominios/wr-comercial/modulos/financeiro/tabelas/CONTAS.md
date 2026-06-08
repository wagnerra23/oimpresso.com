---
table: CONTAS
module: financeiro
created_at_version: 147
last_modified_version: 1466
target_version: 1468
columns_count: 57
foreign_keys_count: 5
foreign_keys:
  CODBANCO_CONFIGURACAO: BANCOS
  CODCONTA_TRANSFERENCIA_AUTO: CONTAS
  CODCONTA_VINCULADA: CONTAS
  CODEMAIL_MODELO: EMAIL_MODELO
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 147;
- **Última mudança:** UPDATE 1466;
- **Total colunas (versão 1468):** 57

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO_CONFIGURACAO` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |
| `CODCONTA_TRANSFERENCIA_AUTO` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODCONTA_VINCULADA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEMAIL_MODELO` | [`EMAIL_MODELO`](../../agenda/tabelas/EMAIL_MODELO.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODEMPRESA` | `INTEGER` | NULL | → `EMPRESA` | v147 | v147 |
| 2 | `CODIGO_TRANSMISSAO` | `varchar(50)` | NULL |  | v187 | v187 |
| 3 | `CARTEIRA_GERA_REMESSA` | `varchar(1)` | NULL |  | v193 | v193 |
| 4 | `VARIACAO_GERA_REMESSA` | `varchar(1)` | NULL |  | v193 | v193 |
| 5 | `LAYOUT_ARQUIVO` | `varchar(3)` | NULL |  | v193 | v193 |
| 6 | `CODIGO_CEDENTE` | `varchar(20)` | NULL |  | v196 | v196 |
| 7 | `CARAC_TITULO` | `integer` | NULL |  | v213 | v213 |
| 8 | `EXECUTA_ARQUIVO_RETORNO` | `varchar(255)` | NULL |  | v235 | v235 |
| 9 | `TIPO_CARTEIRA_MANUAL` | `varchar(1)` | NULL |  | v246 | v246 |
| 10 | `RESPONSAVEL_EMISSAO` | `integer` | NULL |  | v267 | v267 |
| 11 | `MENSAGEM_PROTESTO` | `VARCHAR(500)` | NULL |  | v298 | v298 |
| 12 | `MENSAGEM_MULTA` | `VARCHAR(500)` | NULL |  | v298 | v298 |
| 13 | `MENSAGEM_JUROS` | `VARCHAR(500)` | NULL |  | v298 | v298 |
| 14 | `IMPR_HISTORICO_PARCELA` | `VARCHAR(1)` | NULL |  | v298 | v298 |
| 15 | `IMPR_PLANO_DE_CONTAS` | `VARCHAR(1)` | NULL |  | v298 | v298 |
| 16 | `CODCONTA_VINCULADA` | `integer` | NULL | → `CONTAS` | v303 | v303 |
| 17 | `ESPECIE` | `varchar(15)` | NULL |  | v303 | v303 |
| 18 | `TOLERANCIA` | `INTEGER` | NULL |  | v308 | v308 |
| 19 | `IGNORAR_RETORNO_SEM_LIQUIDACAO` | `VARCHAR(1)` | NULL |  | v308 | v308 |
| 20 | `DT_BALANCO` | `timestamp` | NULL |  | v385 | v385 |
| 21 | `GERA_DEBITO_TARIFA` | `VARCHAR(1)` | NULL |  | v394 | v394 |
| 22 | `COOPERATIVA` | `VARCHAR(1)` | NULL |  | v400 | v400 |
| 23 | `AGENCIA_COOPERATIVA` | `VARCHAR(10)` | NULL |  | v404 | v404 |
| 24 | `CONTA_COOPERATIVA` | `VARCHAR(20)` | NULL |  | v404 | v404 |
| 25 | `DIGITO_AG_COOPERATIVA` | `VARCHAR(2)` | NULL |  | v404 | v404 |
| 26 | `DIGITO_CC_COOPERATIVA` | `VARCHAR(1)` | NULL |  | v404 | v404 |
| 27 | `CODIGO_CEDENTE_COOPERATIVA` | `varchar(20)` | NULL |  | v423 | v423 |
| 28 | `BAIXA_DEVOLUCAO` | `INTEGER` | NULL |  | v428 | v428 |
| 29 | `STATUS` | `VARCHAR(10)` | NULL |  | v454 | v454 |
| 30 | `EMAIL_ASSUNTO` | `VARCHAR(100)` | NULL |  | v468 | v468 |
| 31 | `EMAIL_MENSAGEM` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v468 | v468 |
| 32 | `EMAIL_EXIBIR_DOCUMENTO` | `VARCHAR(1)` | NULL |  | v470 | v470 |
| 33 | `EMAIL_EXIBIR_VENCIMENTO` | `VARCHAR(1)` | NULL |  | v470 | v470 |
| 34 | `EMAIL_EXIBIR_NOTA` | `VARCHAR(1)` | NULL |  | v470 | v470 |
| 35 | `EMAIL_EXIBIR_VALOR` | `VARCHAR(1)` | NULL |  | v470 | v470 |
| 36 | `EMAIL_EXIBIR_HISTORICO` | `VARCHAR(1)` | NULL |  | v470 | v470 |
| 37 | `EMAIL_TIPO_EXIBICAO_DADOS` | `INTEGER` | NULL |  | v472 | v472 |
| 38 | `CODEMAIL_MODELO` | `integer` | NULL | → `EMAIL_MODELO` | v561 | v561 |
| 39 | `TIPO_CONVENIO` | `VARCHAR(50)` | NULL |  | v623 | v623 |
| 40 | `CODBANCO_CONFIGURACAO` | `INTEGER` | NULL | → `BANCOS` | v679 | v679 |
| 41 | `DESCONTO` | `DOUBLE PRECISION` | NULL |  | v695 | v695 |
| 42 | `DiaDESCONTO` | `integer` | NULL |  | v695 | v695 |
| 43 | `MENSAGEM_DESCONTO` | `VARCHAR(500)` | NULL |  | v695 | v695 |
| 44 | `AGENCIA_CONTA_DV` | `VARCHAR(1)` | NULL |  | v700 | v700 |
| 45 | `CODCONTA_TRANSFERENCIA_AUTO` | `INTEGER` | NULL | → `CONTAS` | v701 | v701 |
| 46 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 47 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 48 | `MULTA_DIAS_TOLERANCIA` | `INTEGER` | NULL |  | v743 | v743 |
| 49 | `CLIENTID` | `VARCHAR(255), ADD CLIENTSECRET VARCHAR(255), ADD KEYFILE BLOB SUB_TYPE 1 SEGMENT SIZE 80, ADD CERTFILE BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v1140 | v1140 |
| 50 | `VERSAO_ARQUIVO` | `INTEGER` | NULL |  | v1156 | v1156 |
| 51 | `VERSAO_LAYOUT` | `INTEGER` | NULL |  | v1156 | v1156 |
| 52 | `PIX` | `VARCHAR(100)` | NULL |  | v1290 | v1290 |
| 53 | `TEM_WS` | `VARCHAR(1)` | NULL |  | v1311 | v1311 |
| 54 | `WS_SCOPO` | `VARCHAR(500)` | NULL |  | v1311 | v1311 |
| 55 | `ENDERECO` | `VARCHAR(100)` | NULL |  | v1349 | v1349 |
| 56 | `INDICADORPIX` | `VARCHAR(1)` | NULL |  | v1383 | v1383 |
| 57 | `APPKEY` | `VARCHAR(255)` | NULL |  | v1466 | v1466 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 147 | ADD_COL | + CODEMPRESA INTEGER |
| 187 | ADD_COL | + CODIGO_TRANSMISSAO varchar(50) |
| 193 | ADD_COL | + CARTEIRA_GERA_REMESSA varchar(1) |
| 193 | ADD_COL | + VARIACAO_GERA_REMESSA varchar(1) |
| 193 | ADD_COL | + LAYOUT_ARQUIVO varchar(3) |
| 196 | ADD_COL | + CODIGO_CEDENTE varchar(20) |
| 213 | ADD_COL | + CARAC_TITULO integer |
| 229 | ALTER_TYPE | ~ NOME_CEDENTE TYPE varchar(250) |
| 235 | ADD_COL | + EXECUTA_ARQUIVO_RETORNO varchar(255) |
| 246 | ADD_COL | + TIPO_CARTEIRA_MANUAL varchar(1) |
| 252 | ALTER_TYPE | ~ DIGITO_AG TYPE varchar(2) |
| 252 | ALTER_TYPE | ~ LOCAL_DE_PAGAMENTO TYPE varchar(100) |
| 267 | ADD_COL | + RESPONSAVEL_EMISSAO integer |
| 298 | ADD_COL | + MENSAGEM_PROTESTO VARCHAR(500) |
| 298 | ADD_COL | + MENSAGEM_MULTA VARCHAR(500) |
| 298 | ADD_COL | + MENSAGEM_JUROS VARCHAR(500) |
| 298 | ADD_COL | + IMPR_HISTORICO_PARCELA VARCHAR(1) |
| 298 | ADD_COL | + IMPR_PLANO_DE_CONTAS VARCHAR(1) |
| 303 | ADD_COL | + CODCONTA_VINCULADA integer |
| 303 | ADD_COL | + ESPECIE varchar(15) |
| 308 | ADD_COL | + TOLERANCIA INTEGER |
| 308 | ADD_COL | + IGNORAR_RETORNO_SEM_LIQUIDACAO VARCHAR(1) |
| 385 | ADD_COL | + DT_BALANCO timestamp |
| 394 | ADD_COL | + GERA_DEBITO_TARIFA VARCHAR(1) |
| 400 | ADD_COL | + COOPERATIVA VARCHAR(1) |
| 404 | ADD_COL | + AGENCIA_COOPERATIVA VARCHAR(10) |
| 404 | ADD_COL | + CONTA_COOPERATIVA VARCHAR(20) |
| 404 | ADD_COL | + DIGITO_AG_COOPERATIVA VARCHAR(2) |
| 404 | ADD_COL | + DIGITO_CC_COOPERATIVA VARCHAR(1) |
| 423 | ADD_COL | + CODIGO_CEDENTE_COOPERATIVA varchar(20) |
| 428 | ADD_COL | + BAIXA_DEVOLUCAO INTEGER |
| 435 | ALTER_TYPE | ~ LOCAL_DE_PAGAMENTO TYPE VARCHAR(150) |
| 435 | ALTER_TYPE | ~ DEMONSTRATIVO TYPE VARCHAR(1000) |
| 454 | ADD_COL | + STATUS VARCHAR(10) |
| 468 | ADD_COL | + EMAIL_ASSUNTO VARCHAR(100) |
| 468 | ADD_COL | + EMAIL_MENSAGEM BLOB SUB_TYPE 1 SEGMENT SIZE 1024 |
| 470 | ADD_COL | + EMAIL_EXIBIR_DOCUMENTO VARCHAR(1) |
| 470 | ADD_COL | + EMAIL_EXIBIR_VENCIMENTO VARCHAR(1) |
| 470 | ADD_COL | + EMAIL_EXIBIR_NOTA VARCHAR(1) |
| 470 | ADD_COL | + EMAIL_EXIBIR_VALOR VARCHAR(1) |
| 470 | ADD_COL | + EMAIL_EXIBIR_HISTORICO VARCHAR(1) |
| 472 | ADD_COL | + EMAIL_TIPO_EXIBICAO_DADOS INTEGER |
| 530 | ALTER_TYPE | ~ DEMONSTRATIVO TYPE VARCHAR(1000) CHARACTER SET WIN1252 |
| 561 | ADD_COL | + CODEMAIL_MODELO integer |
| 623 | ADD_COL | + TIPO_CONVENIO VARCHAR(50) |
| 679 | ADD_COL | + CODBANCO_CONFIGURACAO INTEGER |
| 695 | ADD_COL | + DESCONTO DOUBLE PRECISION |
| 695 | ADD_COL | + DiaDESCONTO integer |
| 695 | ADD_COL | + MENSAGEM_DESCONTO VARCHAR(500) |
| 700 | ADD_COL | + AGENCIA_CONTA_DV VARCHAR(1) |
| 701 | ADD_COL | + CODCONTA_TRANSFERENCIA_AUTO INTEGER |
| 704 | ALTER_TYPE | ~ DIGITO_CC TYPE VARCHAR(2) |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 743 | ADD_COL | + MULTA_DIAS_TOLERANCIA INTEGER |
| 1140 | ADD_COL | + CLIENTID VARCHAR(255), ADD CLIENTSECRET VARCHAR(255), ADD KEYFILE BLOB SUB_TYPE 1 SEGMENT SIZE 80, ADD CERTFILE BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 1156 | ADD_COL | + VERSAO_ARQUIVO INTEGER |
| 1156 | ADD_COL | + VERSAO_LAYOUT INTEGER |
| 1290 | ADD_COL | + PIX VARCHAR(100) |
| 1311 | ADD_COL | + TEM_WS VARCHAR(1) |
| 1311 | ADD_COL | + WS_SCOPO VARCHAR(500) |
| 1349 | ADD_COL | + ENDERECO VARCHAR(100) |
| 1383 | ADD_COL | + INDICADORPIX VARCHAR(1) |
| 1466 | ADD_COL | + APPKEY VARCHAR(255) |

