---
table: FINANCEIRO_CENTRO_CUSTO
module: financeiro
created_at_version: 966
last_modified_version: 1348
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_CENTRO_CUSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 966;
- **Última mudança:** UPDATE 1348;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODFINANCEIRO` | `INTEGER` | NOT NULL | v966 | v966 |
| 2 | `PERCENTUAL` | `DOUBLE PRECISION` | NULL | v966 | v966 |
| 3 | `TIPO` | `VARCHAR(15)` | NULL | v966 | v966 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL | v966 | v966 |
| 5 | `CODCENTRO_CUSTO` | `INTEGER` | NOT NULL | v966 | v1224 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1273 | v1273 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL | v1273 | v1273 |
| 8 | `CODEMPRESA` | `INTEGER` | NULL | v1274 | v1274 |
| 9 | `TABELA` | `VARCHAR(255)` | NULL | v1348 | v1348 |
| 10 | `CODTABELA` | `VARCHAR(10)` | NULL | v1274 | v1319 |
| 11 | `CODPEDIDO` | `VARCHAR(50)` | NOT NULL | v1322 | v1322 |
| 12 | `MODULO` | `VARCHAR(100)` | NULL | v1322 | v1322 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 966 | CREATE | CREATE TABLE com 5 colunas |
| 1224 | RENAME_COL | × CODCENTRO_TRABALHO → CODCENTRO_CUSTO |
| 1255 | RENAME_COL | × CODCENTRO_TRABALHO → CODCENTRO_CUSTO |
| 1273 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1273 | ADD_COL | + ATIVO VARCHAR(1) |
| 1274 | ADD_COL | + CODVENDA VARCHAR(10) |
| 1274 | ADD_COL | + CODEMPRESA INTEGER |
| 1276 | ADD_COL | + TABELA VARCHAR(50) |
| 1319 | RENAME_COL | × CODVENDA → CODTABELA |
| 1322 | ADD_COL | + CODPEDIDO VARCHAR(50) |
| 1322 | ADD_COL | + CODCENTRO_CUSTO_PAI INTEGER |
| 1322 | ADD_COL | + MODULO VARCHAR(100) |
| 1348 | ADD_COL | + TABELA VARCHAR(255) |
| 1348 | DROP_COL | - CODCENTRO_CUSTO_PAI |

