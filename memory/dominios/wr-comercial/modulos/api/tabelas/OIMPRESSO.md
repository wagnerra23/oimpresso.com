---
table: OIMPRESSO
module: api
created_at_version: 1192
last_modified_version: 1230
target_version: 1468
columns_count: 7
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `OIMPRESSO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `api` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1192;
- **Última mudança:** UPDATE 1230;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1192 | v1192 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1192 | v1192 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1192 | v1192 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1192 | v1192 |
| 5 | `TABELA` | `VARCHAR(255), ADD OBSERVACAO BLOB SUB_TYPE 1 SEGMENT SIZE 80, ADD CODUSUARIO INTEGER` | NULL |  | v1219 | v1219 |
| 6 | `DATA` | `TIMESTAMP` | NULL |  | v1204 | v1219 |
| 7 | `ARQUIVO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80;CREATE TABLE PRODUTO_BAIXA_AUTOMATICA ( CODIGO INTEGER , DT_ALTERACAO TIMESTAMP, ATIVO VARCHAR(1), CODPRODUTO VARCHAR(15), CODPRODUTO_ETAPA INTEGER, CODPRODUTO_COMPOSICAO INTEGER )` | NOT NULL |  | v1230 | v1230 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1192 | CREATE | CREATE TABLE com 13 colunas |
| 1197 | ADD_COL | + PRIORIDADE_SINCRONIZACAO VARCHAR(20) |
| 1204 | ADD_COL | + DT_ULTIMA_ATUALIZACAO_PESSOAS TIMESTAMP |
| 1219 | DROP_COL | - LINK |
| 1219 | DROP_COL | - WEB_SERVICE |
| 1219 | DROP_COL | - LOGIN |
| 1219 | DROP_COL | - SENHA |
| 1219 | DROP_COL | - CLIENT_ID |
| 1219 | DROP_COL | - CLIENT_SECRET |
| 1219 | DROP_COL | - PESSOA_TRANSPORTADORA_TIPO |
| 1219 | DROP_COL | - PESSOA_CLIENTE_TIPO |
| 1219 | DROP_COL | - PESSOA_FORNECEDOR_TIPO |
| 1219 | RENAME_COL | × DT_ULTIMA_ATUALIZACAO_PESSOAS → DT_ULTIMA_ATUALIZACAO |
| 1219 | DROP_COL | - PRIORIDADE_SINCRONIZACAO |
| 1219 | ADD_COL | + TABELA VARCHAR(255), ADD OBSERVACAO BLOB SUB_TYPE 1 SEGMENT SIZE 80, ADD CODUSUARIO INTEGER |
| 1219 | RENAME_COL | × DT_ULTIMA_ATUALIZACAO → DATA |
| 1230 | ADD_COL | + ARQUIVO BLOB SUB_TYPE 1 SEGMENT SIZE 80;CREATE TABLE PRODUTO_BAIXA_AUTOMATICA ( CODIGO INTEGER , DT_ALTERACAO TIMESTAMP, ATIVO VARCHAR(1), CODPRODUTO VARCHAR(15), CODPRODUTO_ETAPA INTEGER, CODPRODUTO_COMPOSICAO INTEGER ) |

