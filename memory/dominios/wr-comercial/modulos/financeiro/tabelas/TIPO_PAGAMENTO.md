---
table: TIPO_PAGAMENTO
module: financeiro
created_at_version: 400
last_modified_version: 1153
target_version: 1468
columns_count: 23
foreign_keys_count: 1
foreign_keys:
  CODCONTA_PADRAO: CONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TIPO_PAGAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 400;
- **Última mudança:** UPDATE 1153;
- **Total colunas (versão 1468):** 23

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONTA_PADRAO` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v400 | v400 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NOT NULL |  | v400 | v400 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v401 | v401 |
| 4 | `TIPO_PADRAO` | `varchar(1)` | NULL |  | v414 | v414 |
| 5 | `TIPO_DT_PREVISAO` | `varchar (15)` | NULL |  | v414 | v414 |
| 6 | `PREVISAO_DIA_FECHAMENTO` | `smallint` | NULL |  | v414 | v414 |
| 7 | `PREVISAO_DIA_CREDITO` | `smallint` | NULL |  | v414 | v414 |
| 8 | `SEM_DT_PAGTO` | `VARCHAR(1)` | NULL |  | v464 | v464 |
| 9 | `PROVISORIO` | `VARCHAR(1)` | NULL |  | v476 | v476 |
| 10 | `CODCONTA_PADRAO` | `INTEGER` | NULL | → `CONTAS` | v606 | v606 |
| 11 | `TAXA_PERC` | `DOUBLE PRECISION` | NULL |  | v606 | v606 |
| 12 | `TAXA_VALOR` | `DOUBLE PRECISION` | NULL |  | v606 | v606 |
| 13 | `PESSOA_BANCO_CODIGO` | `VARCHAR(10)` | NULL |  | v629 | v629 |
| 14 | `PESSOA_BANCO_TIPO` | `VARCHAR(3)` | NULL |  | v629 | v629 |
| 15 | `PESSOA_BANCO_SEQUENCIA` | `INTEGER` | NULL |  | v629 | v629 |
| 16 | `TRANSFERIR_PESSOA_BANCO` | `DOM_BOOLEAN` | NULL |  | v629 | v629 |
| 17 | `TRANSFERIR_RECEBIDO` | `varchar(1)` | NULL |  | v693 | v693 |
| 18 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 19 | `TRANSFERIR_PESSOA_NOME` | `VARCHAR(1)` | NULL |  | v869 | v869 |
| 20 | `NF_TIPO_PAGTO` | `VARCHAR(2)` | NULL |  | v988 | v988 |
| 21 | `TEM_TEF` | `VARCHAR(1)` | NULL |  | v1153 | v1153 |
| 22 | `OBRIGACAO_DOC_FISCAL` | `VARCHAR(20)` | NULL |  | v1144 | v1144 |
| 23 | `TIPO_DOC_FISCAL` | `VARCHAR(10)` | NULL |  | v1144 | v1144 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 400 | CREATE | CREATE TABLE com 2 colunas |
| 401 | ADD_COL | + ATIVO VARCHAR(1) |
| 414 | ADD_COL | + TIPO_PADRAO varchar(1) |
| 414 | ADD_COL | + TIPO_DT_PREVISAO varchar (15) |
| 414 | ADD_COL | + PREVISAO_DIA_FECHAMENTO smallint |
| 414 | ADD_COL | + PREVISAO_DIA_CREDITO smallint |
| 464 | ADD_COL | + SEM_DT_PAGTO VARCHAR(1) |
| 476 | ADD_COL | + PROVISORIO VARCHAR(1) |
| 606 | ADD_COL | + CODCONTA_PADRAO INTEGER |
| 606 | ADD_COL | + TAXA_PERC DOUBLE PRECISION |
| 606 | ADD_COL | + TAXA_VALOR DOUBLE PRECISION |
| 629 | ADD_COL | + PESSOA_BANCO_CODIGO VARCHAR(10) |
| 629 | ADD_COL | + PESSOA_BANCO_TIPO VARCHAR(3) |
| 629 | ADD_COL | + PESSOA_BANCO_SEQUENCIA INTEGER |
| 629 | ADD_COL | + TRANSFERIR_PESSOA_BANCO DOM_BOOLEAN |
| 693 | ADD_COL | + TRANSFERIR_RECEBIDO varchar(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 869 | ADD_COL | + TRANSFERIR_PESSOA_NOME VARCHAR(1) |
| 988 | ADD_COL | + TIPO_PAGTO_NF INTEGER |
| 988 | DROP_COL | - TIPO_PAGTO_NF |
| 988 | ADD_COL | + NF_TIPO_PAGTO VARCHAR(2) |
| 1144 | ADD_COL | + TEM_TEF VARCHAR(1) |
| 1144 | ADD_COL | + OBRIGACAO_DOC_FISCAL VARCHAR(20) |
| 1144 | ADD_COL | + TIPO_DOC_FISCAL VARCHAR(10) |
| 1153 | ADD_COL | + TEM_TEF VARCHAR(1) |

