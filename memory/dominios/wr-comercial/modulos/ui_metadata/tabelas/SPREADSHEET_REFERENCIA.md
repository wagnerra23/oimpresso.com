---
table: SPREADSHEET_REFERENCIA
module: ui_metadata
created_at_version: 834
last_modified_version: 883
target_version: 1468
columns_count: 7
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SPREADSHEET_REFERENCIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 834;
- **Última mudança:** UPDATE 883;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v834 | v834 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v834 | v834 |
| 3 | `SPREADSHEET` | `VARCHAR(100)` | NULL |  | v834 | v834 |
| 4 | `REFERENCIA` | `VARCHAR(255)` | NULL |  | v834 | v834 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v834 | v834 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v834 | v834 |
| 7 | `TEM_FORMULA` | `VARCHAR(1)` | NULL |  | v883 | v883 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 834 | CREATE | CREATE TABLE com 6 colunas |
| 883 | ADD_COL | + TEM_FORMULA VARCHAR(1) |

