---
table: KPI
module: bi
created_at_version: 1242
last_modified_version: 1256
target_version: 1468
columns_count: 10
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `KPI`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1242;
- **Última mudança:** UPDATE 1256;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `COMPETENCIA` | `VARCHAR(7)` | NULL |  | v1242 | v1242 |
| 2 | `TEXT1` | `VARCHAR(255)` | NULL |  | v1242 | v1242 |
| 3 | `TEXT2` | `VARCHAR(255)` | NULL |  | v1242 | v1242 |
| 4 | `TEXT3` | `VARCHAR(255)` | NULL |  | v1242 | v1242 |
| 5 | `TEXT4` | `VARCHAR(255)` | NULL |  | v1242 | v1242 |
| 6 | `TAG_ESTILO` | `INTEGER` | NULL |  | v1243 | v1243 |
| 7 | `GROUPINDEX` | `INTEGER` | NULL |  | v1243 | v1243 |
| 8 | `INDEXINGROUP` | `INTEGER` | NULL |  | v1243 | v1243 |
| 9 | `TAG` | `INTEGER` | NULL |  | v1252 | v1252 |
| 10 | `FAVORITO` | `VARCHAR(1)` | NULL |  | v1256 | v1256 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1242 | ADD_COL | + COMPETENCIA VARCHAR(7) |
| 1242 | ADD_COL | + TEXT1 VARCHAR(255) |
| 1242 | ADD_COL | + TEXT2 VARCHAR(255) |
| 1242 | ADD_COL | + TEXT3 VARCHAR(255) |
| 1242 | ADD_COL | + TEXT4 VARCHAR(255) |
| 1243 | ADD_COL | + TAG_ESTILO INTEGER |
| 1243 | ADD_COL | + GROUPINDEX INTEGER |
| 1243 | ADD_COL | + INDEXINGROUP INTEGER |
| 1243 | DROP_COL | - COLUNA1 |
| 1243 | DROP_COL | - COLUNA2 |
| 1243 | DROP_COL | - COLUNA3 |
| 1243 | DROP_COL | - COLUNA4 |
| 1243 | DROP_COL | - COLUNA5 |
| 1243 | DROP_COL | - COLUNA6 |
| 1243 | DROP_COL | - COLUNA7 |
| 1243 | DROP_COL | - TEM_QUANT_REGISTROS |
| 1252 | ADD_COL | + TAG INTEGER |
| 1256 | ADD_COL | + FAVORITO VARCHAR(1) |

