---
table: HISTORICO_ADICIONA_SEGUIDOR
module: wr_metadata
created_at_version: 1240
last_modified_version: 1244
target_version: 1468
columns_count: 15
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_ADICIONA_SEGUIDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1240;
- **Última mudança:** UPDATE 1244;
- **Total colunas (versão 1468):** 15

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1240 | v1240 |
| 2 | `CODEMPRESA` | `VARCHAR(250)` | NULL | v1240 | v1240 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v1240 | v1240 |
| 4 | `DATA` | `TIMESTAMP` | NULL | v1240 | v1240 |
| 5 | `EVENTO` | `VARCHAR(50)` | NULL | v1240 | v1240 |
| 6 | `MODULO` | `VARCHAR(50)` | NULL | v1240 | v1240 |
| 7 | `TABELA` | `VARCHAR(50)` | NULL | v1240 | v1240 |
| 8 | `CODTABELA` | `VARCHAR(50)` | NULL | v1240 | v1240 |
| 9 | `CODPESSOA` | `VARCHAR(50)` | NULL | v1240 | v1240 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL | v1240 | v1240 |
| 11 | `IS_CLIENTE` | `VARCHAR(1)` | NULL | v1240 | v1240 |
| 12 | `IS_RESPONSAVEL` | `VARCHAR(1)` | NULL | v1240 | v1240 |
| 13 | `CHAVE_PK1` | `INTEGER` | NULL | v1244 | v1244 |
| 14 | `CHAVE_PK2` | `VARCHAR(40)` | NULL | v1244 | v1244 |
| 15 | `CHAVE_PK3` | `VARCHAR(15)` | NULL | v1244 | v1244 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1240 | CREATE | CREATE TABLE com 12 colunas |
| 1244 | ADD_COL | + CHAVE_PK1 INTEGER |
| 1244 | ADD_COL | + CHAVE_PK2 VARCHAR(40) |
| 1244 | ADD_COL | + CHAVE_PK3 VARCHAR(15) |

