---
table: BI_ACOES
module: bi
created_at_version: 1265
last_modified_version: 1266
target_version: 1468
columns_count: 22
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BI_ACOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1265;
- **Última mudança:** UPDATE 1266;
- **Total colunas (versão 1468):** 22

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1265 | v1265 |
| 2 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v1265 | v1265 |
| 3 | `TABELA` | `VARCHAR(50)` | NULL |  | v1265 | v1265 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1265 | v1265 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1265 | v1265 |
| 6 | `MODULO` | `VARCHAR(50)` | NULL |  | v1265 | v1265 |
| 7 | `FILTRO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 4096` | NULL |  | v1266 | v1266 |
| 8 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v1266 | v1266 |
| 9 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v1266 | v1266 |
| 10 | `GRAFICO_TIPO` | `VARCHAR(20)` | NULL |  | v1266 | v1266 |
| 11 | `WIDTH` | `INTEGER` | NULL |  | v1266 | v1266 |
| 12 | `HEIGHT` | `INTEGER` | NULL |  | v1266 | v1266 |
| 13 | `TEM_PERIODO` | `VARCHAR(1)` | NULL |  | v1266 | v1266 |
| 14 | `TEM_QUANT_REGISTROS` | `VARCHAR(1)` | NULL |  | v1266 | v1266 |
| 15 | `SQL` | `VARCHAR(5000)` | NULL |  | v1266 | v1266 |
| 16 | `FORMATO` | `VARCHAR(50)` | NULL |  | v1266 | v1266 |
| 17 | `PERIODO` | `VARCHAR(20)` | NULL |  | v1266 | v1266 |
| 18 | `BLOCO` | `VARCHAR(20)` | NULL |  | v1266 | v1266 |
| 19 | `AGRUPAMENTO` | `VARCHAR(500)` | NULL |  | v1266 | v1266 |
| 20 | `CAMPOPERIODO` | `VARCHAR(255)` | NULL |  | v1266 | v1266 |
| 21 | `CAMPO_CATEGORIA` | `VARCHAR(100)` | NULL |  | v1266 | v1266 |
| 22 | `TAG_TELA` | `INTEGER` | NULL |  | v1266 | v1266 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1265 | CREATE | CREATE TABLE com 6 colunas |
| 1266 | ADD_COL | + FILTRO BLOB SUB_TYPE 1 SEGMENT SIZE 4096 |
| 1266 | ADD_COL | + QUANT_REGISTROS INTEGER |
| 1266 | ADD_COL | + GRAFICO_PERIODO VARCHAR(10) |
| 1266 | ADD_COL | + GRAFICO_TIPO VARCHAR(20) |
| 1266 | ADD_COL | + WIDTH INTEGER |
| 1266 | ADD_COL | + HEIGHT INTEGER |
| 1266 | ADD_COL | + TEM_PERIODO VARCHAR(1) |
| 1266 | ADD_COL | + TEM_QUANT_REGISTROS VARCHAR(1) |
| 1266 | ADD_COL | + SQL VARCHAR(5000) |
| 1266 | ADD_COL | + FORMATO VARCHAR(50) |
| 1266 | ADD_COL | + PERIODO VARCHAR(20) |
| 1266 | ADD_COL | + BLOCO VARCHAR(20) |
| 1266 | ADD_COL | + AGRUPAMENTO VARCHAR(500) |
| 1266 | ADD_COL | + CAMPOPERIODO VARCHAR(255) |
| 1266 | ADD_COL | + CAMPO_CATEGORIA VARCHAR(100) |
| 1266 | ADD_COL | + TAG_TELA INTEGER |

