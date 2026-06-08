---
table: PRODUTO_GRADE_MODELO
module: estoque
created_at_version: 194
last_modified_version: 1314
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_GRADE_MODELO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 194;
- **Última mudança:** UPDATE 1314;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TIPO` | `varchar(15)` | NULL |  | v194 | v194 |
| 2 | `TIPOSMEDIDAS` | `VARCHAR(3)` | NULL |  | v353 | v353 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 5 | `MIGRADO` | `VARCHAR(1)` | NULL |  | v1314 | v1314 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 194 | ADD_COL | + TIPO varchar(15) |
| 353 | ADD_COL | + TIPOSMEDIDAS VARCHAR(3) |
| 353 | ALTER_TYPE | ~ T1 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T2 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T3 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T4 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T5 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T6 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T7 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T8 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T9 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T10 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T11 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T12 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T13 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T14 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T15 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T16 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T17 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T18 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T19 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T20 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T21 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T22 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T23 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T24 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T25 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T26 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T27 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T28 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T29 TYPE VARCHAR(30) |
| 353 | ALTER_TYPE | ~ T30 TYPE VARCHAR(30) |
| 355 | ALTER_TYPE | ~ T30 TYPE VARCHAR(30) |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1314 | ADD_COL | + MIGRADO VARCHAR(1) |

