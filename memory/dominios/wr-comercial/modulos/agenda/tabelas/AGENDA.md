---
id: dominios-wr-comercial-modulos-agenda-tabelas-agenda
table: AGENDA
module: agenda
created_at_version: 12
last_modified_version: 861
target_version: 1468
columns_count: 99
foreign_keys_count: 23
foreign_keys:
  CODAGENDA_COMPOSICAO: AGENDA
  CODAGENDA_FAQ: AGENDA_FAQ
  CODAGENDA_TITULO: AGENDA_TITULO
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODCLIENTE: PESSOAS
  CODCONDICAOPAGTO: CONDICAOPAGTO
  CODEMAIL: EMAIL
  CODEMAIL_ANEXO: EMAIL_ANEXO
  CODEMAIL_CRM_DATABASE: EMAIL
  CODEMPRESA: EMPRESA
  CODFINANCEIRO: FINANCEIRO
  CODFUNCIONARIO: FUNCIONARIO
  CODLOTE: LOTE
  CODPRODUCAO: PRODUCAO
  CODPRODUTO: PRODUTO
  CODSTATUS: STATUS
  CODUSUARIO: USUARIO
  CODUSUARIO_ALTERADO: USUARIO
  CODUSUARIO_CRIADOR: USUARIO
  CODUSUARIO_RESPONSAVEL: USUARIO
  CODVENDA: VENDA
  CODVENDA_ORIGINAL: VENDA
  CODVENDA_PRODUTO_ORIGINAL: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 861;
- **Total colunas (versão 1468):** 99

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODAGENDA_COMPOSICAO` | [`AGENDA`](../../agenda/tabelas/AGENDA.md) |
| `CODAGENDA_FAQ` | [`AGENDA_FAQ`](../../agenda/tabelas/AGENDA_FAQ.md) |
| `CODAGENDA_TITULO` | [`AGENDA_TITULO`](../../agenda/tabelas/AGENDA_TITULO.md) |
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODCLIENTE` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODCONDICAOPAGTO` | [`CONDICAOPAGTO`](../../financeiro/tabelas/CONDICAOPAGTO.md) |
| `CODEMAIL` | [`EMAIL`](../../agenda/tabelas/EMAIL.md) |
| `CODEMAIL_ANEXO` | [`EMAIL_ANEXO`](../../agenda/tabelas/EMAIL_ANEXO.md) |
| `CODEMAIL_CRM_DATABASE` | [`EMAIL`](../../agenda/tabelas/EMAIL.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODFINANCEIRO` | [`FINANCEIRO`](../../financeiro/tabelas/FINANCEIRO.md) |
| `CODFUNCIONARIO` | [`FUNCIONARIO`](../../cadastros/tabelas/FUNCIONARIO.md) |
| `CODLOTE` | [`LOTE`](../../estoque/tabelas/LOTE.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODSTATUS` | [`STATUS`](../../wr_metadata/tabelas/STATUS.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_ALTERADO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_CRIADOR` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_RESPONSAVEL` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_ORIGINAL` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO_ORIGINAL` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(40)` | NOT NULL |  | v12 | v12 |
| 2 | `SEQUENCIA` | `INTEGER` | NULL |  | v12 | v12 |
| 3 | `PARENTID` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v12 | v12 |
| 4 | `CODAGENDA_TITULO` | `INTEGER` | NULL | → `AGENDA_TITULO` | v12 | v12 |
| 5 | `CAPTION` | `VARCHAR(255)` | NULL |  | v12 | v12 |
| 6 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 7 | `DT_FIM` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 8 | `H_MINIMO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 9 | `H_MAXIMO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 10 | `IMAGEMINDEX` | `INTEGER` | NULL |  | v12 | v12 |
| 11 | `COLOR` | `INTEGER` | NULL |  | v12 | v12 |
| 12 | `IMAGE` | `INTEGER` | NULL |  | v12 | v12 |
| 13 | `TAREFA_COMPLETA` | `INTEGER` | NULL |  | v12 | v12 |
| 14 | `TAREFA_INDEX` | `INTEGER` | NULL |  | v12 | v12 |
| 15 | `STATUS` | `INTEGER` | NULL |  | v12 | v12 |
| 16 | `EVENTO_TIPO` | `INTEGER` | NULL |  | v12 | v12 |
| 17 | `RECURRENCE_INDEX` | `INTEGER` | NULL |  | v12 | v12 |
| 18 | `REMINDER_DATE` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 19 | `OPTIONS` | `INTEGER` | NULL |  | v12 | v12 |
| 20 | `MENSSAGE` | `VARCHAR(5000)` | NULL |  | v12 | v325 |
| 21 | `LOCATION` | `VARCHAR(255)` | NULL |  | v12 | v12 |
| 22 | `CODCLIENTE` | `VARCHAR(15)` | NULL | → `PESSOAS` | v12 | v12 |
| 23 | `TELEFONE` | `VARCHAR(12)` | NULL |  | v12 | v12 |
| 24 | `TAREFA_STATUS` | `INTEGER` | NULL |  | v12 | v12 |
| 25 | `TAREFA_LINK` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v12 | v12 |
| 26 | `CODFINANCEIRO` | `VARCHAR(10)` | NULL | → `FINANCEIRO` | v12 | v12 |
| 27 | `CODEMPRESA` | `VARCHAR(10)` | NULL | → `EMPRESA` | v12 | v12 |
| 28 | `DT_FATURAMENTO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 29 | `CODUSUARIO` | `VARCHAR(10)` | NULL | → `USUARIO` | v12 | v12 |
| 30 | `BLOQUEIO` | `VARCHAR(1)` | NULL |  | v12 | v12 |
| 31 | `CODFUNCIONARIO` | `VARCHAR(15)` | NULL | → `FUNCIONARIO` | v12 | v12 |
| 32 | `FUNCIONARIO` | `VARCHAR(150)` | NULL |  | v12 | v289 |
| 33 | `CODPERGUNTA` | `VARCHAR(15)` | NULL |  | v758 | v758 |
| 34 | `PERGUNTA` | `VARCHAR(150)` | NULL |  | v758 | v758 |
| 35 | `CODRESPOSTA` | `VARCHAR(15)` | NULL |  | v758 | v758 |
| 36 | `RESPOSTA` | `VARCHAR(150)` | NULL |  | v758 | v758 |
| 37 | `SOLICITANTE` | `VARCHAR(150)` | NULL |  | v12 | v12 |
| 38 | `CODVENDA` | `VARCHAR(15)` | NULL | → `VENDA` | v12 | v12 |
| 39 | `OCORRENCIA` | `VARCHAR(100)` | NULL |  | v12 | v12 |
| 40 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v12 | v12 |
| 41 | `DT_EMISSAO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 42 | `ID_ALTERACAO` | `INTEGER` | NULL |  | v111 | v111 |
| 43 | `ID_ALTERACAO_DIA` | `INTEGER` | NULL |  | v111 | v111 |
| 44 | `LIDO` | `SMALLINT` | NULL |  | v111 | v111 |
| 45 | `PROTOCOLO` | `VARCHAR(50)` | NULL |  | v111 | v111 |
| 46 | `CODUSUARIO_RESPONSAVEL` | `INTEGER` | NULL | → `USUARIO` | v113 | v113 |
| 47 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v114 | v114 |
| 48 | `PRODUTO` | `varchar (300)` | NULL |  | v114 | v163 |
| 49 | `CODUSUARIO_CRIADOR` | `INTEGER` | NULL | → `USUARIO` | v115 | v115 |
| 50 | `CODUSUARIO_ALTERADO` | `INTEGER` | NULL | → `USUARIO` | v115 | v115 |
| 51 | `PARENT_ID` | `VARCHAR(40)` | NULL |  | v119 | v119 |
| 52 | `RECURRENCE_INFO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v119 | v119 |
| 53 | `GROUP_ID` | `VARCHAR(40)` | NULL |  | v119 | v119 |
| 54 | `REMINDER_MINUTES` | `INTEGER` | NULL |  | v119 | v119 |
| 55 | `REMINDER_RESOURCES_DATA` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v119 | v119 |
| 56 | `CODAGENDA_COMPOSICAO` | `VARCHAR(40)` | NULL | → `AGENDA` | v123 | v123 |
| 57 | `QUANT` | `DOUBLE PRECISION, ADD LARG DOUBLE PRECISION, ADD COMP DOUBLE PRECISION, ADD ESPESSURA DOUBLE PRECISION, ADD QTDADEPECA DOUBLE PRECISION, ADD CODCOMPOSICAO INTEGER, ADD COMPOSICAO VARCHAR(150)` | NULL |  | v128 | v128 |
| 58 | `PATH` | `VARCHAR(255)` | NULL |  | v129 | v129 |
| 59 | `CODSTATUS` | `INTEGER` | NULL | → `STATUS` | v144 | v144 |
| 60 | `LOCAL` | `varchar (150)` | NULL |  | v159 | v172 |
| 61 | `MENSALIDADE` | `double precision` | NULL |  | v173 | v173 |
| 62 | `CODCONDICAOPAGTO` | `integer` | NULL | → `CONDICAOPAGTO` | v175 | v175 |
| 63 | `TIPO_AGENDAMENTO` | `smallint` | NULL |  | v343 | v343 |
| 64 | `REMETENTE_NOME` | `varchar(255)` | NULL |  | v231 | v231 |
| 65 | `REMETENTE_ENDERECO` | `varchar(255)` | NULL |  | v231 | v231 |
| 66 | `CODEMAIL_ANEXO` | `integer` | NULL | → `EMAIL_ANEXO` | v231 | v231 |
| 67 | `IS_EMAIL` | `varchar(1)` | NULL |  | v231 | v231 |
| 68 | `VISUALIZA` | `integer` | NULL |  | v287 | v287 |
| 69 | `ORDENACAO` | `DOUBLE PRECISION` | NULL |  | v293 | v293 |
| 70 | `ACTUAL_START` | `integer` | NULL |  | v343 | v343 |
| 71 | `ACTUAL_FINISH` | `integer` | NULL |  | v343 | v343 |
| 72 | `CODVENDA_ORIGINAL` | `VARCHAR(15)` | NULL | → `VENDA` | v349 | v349 |
| 73 | `CODVENDA_PRODUTO_ORIGINAL` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v349 | v349 |
| 74 | `FRENTE_VERSO` | `VARCHAR(1)` | NULL |  | v352 | v352 |
| 75 | `DT_PREVISAO_ENTREGA_TERCEIRO` | `TIMESTAMP` | NULL |  | v359 | v359 |
| 76 | `QTDPECAS_NORMAL` | `DOUBLE PRECISION` | NULL |  | v361 | v361 |
| 77 | `QTDPECAS_DEFEITO` | `DOUBLE PRECISION` | NULL |  | v361 | v361 |
| 78 | `QTD_PONTOS` | `DOUBLE PRECISION` | NULL |  | v361 | v361 |
| 79 | `GANTT_DT_INICIO` | `timestamp` | NULL |  | v396 | v396 |
| 80 | `GANTT_DT_FIM` | `timestamp` | NULL |  | v396 | v396 |
| 81 | `GANTT_ACTUAL_START` | `integer` | NULL |  | v396 | v396 |
| 82 | `GANTT_ACTUAL_FINISH` | `integer` | NULL |  | v396 | v396 |
| 83 | `CODEMAIL` | `integer` | NULL | → `EMAIL` | v396 | v396 |
| 84 | `CODEMAIL_CRM_DATABASE` | `integer` | NULL | → `EMAIL` | v396 | v396 |
| 85 | `KANBAN_DT_INICIO` | `timestamp` | NULL |  | v396 | v396 |
| 86 | `KANBAN_DT_FIM` | `timestamp` | NULL |  | v396 | v396 |
| 87 | `KANBAN_ACTUAL_START` | `integer` | NULL |  | v396 | v396 |
| 88 | `KANBAN_ACTUAL_FINISH` | `integer` | NULL |  | v396 | v396 |
| 89 | `TEMPO_ESTIMADO` | `integer` | NULL |  | v396 | v396 |
| 90 | `TEMPO_GASTO` | `integer` | NULL |  | v396 | v396 |
| 91 | `DT_PROMETIDO_PARA` | `timestamp` | NULL |  | v396 | v396 |
| 92 | `CODAGENDA_FAQ` | `VARCHAR(15)` | NULL | → `AGENDA_FAQ` | v402 | v402 |
| 93 | `CODLOTE` | `integer` | NULL | → `LOTE` | v417 | v417 |
| 94 | `TIPO_IMPRESSAO` | `VARCHAR(100)` | NULL |  | v432 | v432 |
| 95 | `ACABAMENTO` | `VARCHAR(150)` | NULL |  | v456 | v456 |
| 96 | `CODPRODUCAO` | `INTEGER` | NULL | → `PRODUCAO` | v519 | v519 |
| 97 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v519 | v519 |
| 98 | `MIGRADO_PRODUCAO_2019` | `INTEGER` | NULL |  | v758 | v758 |
| 99 | `CONDICAOPAGTO` | `VARCHAR(100)` | NULL |  | v861 | v861 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| (...) | (...) | _Mostrando últimas 100 de 166 eventos_ |
| 12 | ALTER_TYPE | ~ TELEFONE TYPE VARCHAR(12) |
| 12 | ALTER_TYPE | ~ TAREFA_STATUS TYPE INTEGER |
| 12 | ALTER_TYPE | ~ CODFINANCEIRO TYPE VARCHAR(10) |
| 12 | ALTER_TYPE | ~ CODEMPRESA TYPE VARCHAR(10) |
| 12 | ALTER_TYPE | ~ DT_FATURAMENTO TYPE TIMESTAMP |
| 12 | ALTER_TYPE | ~ CODUSUARIO TYPE VARCHAR(10) |
| 12 | ALTER_TYPE | ~ BLOQUEIO TYPE VARCHAR(1) |
| 12 | ALTER_TYPE | ~ CODFUNCIONARIO TYPE VARCHAR(15) |
| 12 | ALTER_TYPE | ~ FUNCIONARIO TYPE VARCHAR(15) |
| 12 | ALTER_TYPE | ~ CODPERGUNTA TYPE VARCHAR(15) |
| 12 | ALTER_TYPE | ~ PERGUNTA TYPE VARCHAR(150) |
| 12 | ALTER_TYPE | ~ CODRESPOSTA TYPE VARCHAR(15) |
| 12 | ALTER_TYPE | ~ RESPOSTA TYPE VARCHAR(150) |
| 12 | ALTER_TYPE | ~ SOLICITANTE TYPE VARCHAR(150) |
| 12 | ALTER_TYPE | ~ CODVENDA TYPE VARCHAR(15) |
| 12 | ALTER_TYPE | ~ HISTORICO TYPE VARCHAR(255) |
| 12 | ALTER_TYPE | ~ OCORRENCIA TYPE VARCHAR(100) |
| 12 | ALTER_TYPE | ~ VALOR TYPE DOUBLE PRECISION |
| 12 | ADD_COL | + DT_EMISSAO TIMESTAMP |
| 12 | ALTER_TYPE | ~ MENSSAGE TYPE VARCHAR(1000) CHARACTER SET NONE |
| 12 | DROP_COL | - HISTORICO |
| 12 | ALTER_TYPE | ~ AGENDA_FAQ TYPE VARCHAR(600) CHARACTER SET NONE |
| 99 | ALTER_TYPE | ~ FUNCIONARIO TYPE VARCHAR(50) |
| 111 | ADD_COL | + ID_ALTERACAO INTEGER |
| 111 | ADD_COL | + ID_ALTERACAO_DIA INTEGER |
| 111 | ADD_COL | + LIDO SMALLINT |
| 111 | ADD_COL | + PROTOCOLO VARCHAR(50) |
| 113 | ADD_COL | + CODUSUARIO_RESPONSAVEL INTEGER |
| 114 | ADD_COL | + CODPRODUTO VARCHAR(15) |
| 114 | ADD_COL | + PRODUTO VARCHAR(150) |
| 114 | DROP_COL | - PRODUCAO_DESC |
| 115 | ADD_COL | + CODUSUARIO_CRIADOR INTEGER |
| 115 | ADD_COL | + CODUSUARIO_ALTERADO INTEGER |
| 119 | DROP_COL | - RECURRENCE_INFO |
| 119 | ADD_COL | + PARENT_ID VARCHAR(40) |
| 119 | ADD_COL | + RECURRENCE_INFO BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 119 | ADD_COL | + GROUP_ID VARCHAR(40) |
| 119 | ADD_COL | + REMINDER_MINUTES INTEGER |
| 119 | DROP_COL | - REMINDER_MINUTES_BEFORE_START |
| 119 | DROP_COL | - REMINDER_RESOURCES_DATA |
| 119 | ADD_COL | + REMINDER_RESOURCES_DATA BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 123 | ADD_COL | + CODAGENDA_COMPOSICAO VARCHAR(40) |
| 128 | ADD_COL | + QUANT DOUBLE PRECISION, ADD LARG DOUBLE PRECISION, ADD COMP DOUBLE PRECISION, ADD ESPESSURA DOUBLE PRECISION, ADD QTDADEPECA DOUBLE PRECISION, ADD CODCOMPOSICAO INTEGER, ADD COMPOSICAO VARCHAR(150) |
| 129 | ADD_COL | + PATH VARCHAR(255) |
| 144 | ADD_COL | + CODSTATUS INTEGER |
| 159 | ADD_COL | + LOCAL varchar (50) |
| 163 | ALTER_TYPE | ~ PRODUTO TYPE varchar (300) |
| 172 | ALTER_TYPE | ~ LOCAL TYPE varchar (150) |
| 173 | ADD_COL | + MENSALIDADE double precision |
| 175 | ADD_COL | + CODCONDICAOPAGTO integer |
| 176 | ADD_COL | + TIPO_AGENDAMENTO smallint |
| 231 | ADD_COL | + REMETENTE_NOME varchar(255) |
| 231 | ADD_COL | + REMETENTE_ENDERECO varchar(255) |
| 231 | ADD_COL | + CODEMAIL_ANEXO integer |
| 231 | ADD_COL | + IS_EMAIL varchar(1) |
| 287 | ADD_COL | + VISUALIZA integer |
| 289 | ALTER_TYPE | ~ FUNCIONARIO TYPE VARCHAR(150) |
| 293 | ADD_COL | + ORDENACAO DOUBLE PRECISION |
| 325 | ALTER_TYPE | ~ MENSSAGE TYPE VARCHAR(5000) |
| 343 | ADD_COL | + ACTUAL_START integer |
| 343 | ADD_COL | + ACTUAL_FINISH integer |
| 343 | ADD_COL | + TIPO_AGENDAMENTO smallint |
| 349 | ADD_COL | + CODVENDA_ORIGINAL VARCHAR(15) |
| 349 | ADD_COL | + CODVENDA_PRODUTO_ORIGINAL INTEGER |
| 352 | ADD_COL | + FRENTE_VERSO VARCHAR(1) |
| 359 | ADD_COL | + DT_PREVISAO_ENTREGA_TERCEIRO TIMESTAMP |
| 361 | ADD_COL | + QTDPECAS_NORMAL DOUBLE PRECISION |
| 361 | ADD_COL | + QTDPECAS_DEFEITO DOUBLE PRECISION |
| 361 | ADD_COL | + QTD_PONTOS DOUBLE PRECISION |
| 368 | ADD_COL | + GANTT_DT_INICIO timestamp |
| 368 | ADD_COL | + GANTT_DT_FIM timestamp |
| 368 | ADD_COL | + GANTT_ACTUAL_START integer |
| 368 | ADD_COL | + GANTT_ACTUAL_FINISH integer |
| 383 | ADD_COL | + CODEMAIL integer |
| 383 | ADD_COL | + CODEMAIL_CRM_DATABASE integer |
| 396 | ADD_COL | + GANTT_DT_INICIO timestamp |
| 396 | ADD_COL | + GANTT_DT_FIM timestamp |
| 396 | ADD_COL | + GANTT_ACTUAL_START integer |
| 396 | ADD_COL | + GANTT_ACTUAL_FINISH integer |
| 396 | ADD_COL | + CODEMAIL integer |
| 396 | ADD_COL | + CODEMAIL_CRM_DATABASE integer |
| 396 | ADD_COL | + KANBAN_DT_INICIO timestamp |
| 396 | ADD_COL | + KANBAN_DT_FIM timestamp |
| 396 | ADD_COL | + KANBAN_ACTUAL_START integer |
| 396 | ADD_COL | + KANBAN_ACTUAL_FINISH integer |
| 396 | ADD_COL | + TEMPO_ESTIMADO integer |
| 396 | ADD_COL | + TEMPO_GASTO integer |
| 396 | ADD_COL | + DT_PROMETIDO_PARA timestamp |
| 402 | ADD_COL | + CODAGENDA_FAQ VARCHAR(15) |
| 417 | ADD_COL | + CODLOTE integer |
| 432 | ADD_COL | + TIPO_IMPRESSAO VARCHAR(100) |
| 456 | ADD_COL | + ACABAMENTO VARCHAR(150) |
| 519 | ADD_COL | + CODPRODUCAO INTEGER |
| 519 | ADD_COL | + CODCENTRO_TRABALHO INTEGER |
| 758 | ADD_COL | + CODPERGUNTA VARCHAR(15) |
| 758 | ADD_COL | + PERGUNTA VARCHAR(150) |
| 758 | ADD_COL | + CODRESPOSTA VARCHAR(15) |
| 758 | ADD_COL | + RESPOSTA VARCHAR(150) |
| 758 | ADD_COL | + MIGRADO_PRODUCAO_2019 INTEGER |
| 861 | ADD_COL | + CONDICAOPAGTO VARCHAR(100) |

