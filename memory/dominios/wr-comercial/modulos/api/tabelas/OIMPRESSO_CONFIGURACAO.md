---
table: OIMPRESSO_CONFIGURACAO
module: api
created_at_version: 1219
last_modified_version: 1248
target_version: 1468
columns_count: 14
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `OIMPRESSO_CONFIGURACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `api` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1219;
- **Última mudança:** UPDATE 1248;
- **Total colunas (versão 1468):** 14

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1219 | v1219 |
| 2 | `LOGIN` | `VARCHAR(500)` | NULL |  | v1219 | v1219 |
| 3 | `SENHA` | `VARCHAR(255)` | NULL |  | v1219 | v1219 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1219 | v1219 |
| 5 | `PRIORIDADE_SINCRONIZACAO` | `VARCHAR(20)` | NULL |  | v1219 | v1219 |
| 6 | `CLIENT_ID` | `VARCHAR(100)` | NULL |  | v1219 | v1219 |
| 7 | `CLIENT_SECRET` | `VARCHAR(100)` | NULL |  | v1219 | v1219 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1225 | v1225 |
| 9 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1225 | v1225 |
| 10 | `TOKEN_DT_EXPIRACAO` | `TIMESTAMP` | NULL |  | v1225 | v1225 |
| 11 | `TOKEN_WEB_SERVICE` | `VARCHAR(5000) CHARACTER SET WIN1252` | NULL |  | v1225 | v1248 |
| 12 | `PESSOA_CLIENTE_TIPO` | `VARCHAR(3)` | NULL |  | v1248 | v1248 |
| 13 | `PESSOA_FORNECEDOR_TIPO` | `VARCHAR(3)` | NULL |  | v1248 | v1248 |
| 14 | `PESSOA_TRANSPORTADORA_TIPO` | `VARCHAR(3)` | NULL |  | v1248 | v1248 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1219 | CREATE | CREATE TABLE com 8 colunas |
| 1225 | ADD_COL | + ATIVO VARCHAR(1) |
| 1225 | ADD_COL | + DESCRICAO VARCHAR(50) |
| 1225 | ADD_COL | + TOKEN_DT_EXPIRACAO TIMESTAMP |
| 1225 | ADD_COL | + TOKEN_WEB_SERVICE VARCHAR(500) |
| 1225 | DROP_COL | - WEB_SERVICE |
| 1248 | ALTER_TYPE | ~ TOKEN_WEB_SERVICE TYPE VARCHAR(5000) CHARACTER SET WIN1252 |
| 1248 | ADD_COL | + PESSOA_CLIENTE_TIPO VARCHAR(3) |
| 1248 | ADD_COL | + PESSOA_FORNECEDOR_TIPO VARCHAR(3) |
| 1248 | ADD_COL | + PESSOA_TRANSPORTADORA_TIPO VARCHAR(3) |

