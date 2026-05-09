---
table: SITUACAO
module: wr_metadata
created_at_version: 871
last_modified_version: 1336
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SITUACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 871;
- **Última mudança:** UPDATE 1336;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CONTRIBUINTE` | `CHAR(1)` | NULL |  | v871 | v871 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1213 | v1213 |
| 3 | `COTA` | `INTEGER` | NULL |  | v1336 | v1336 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 871 | ADD_COL | + CONTRIBUINTE CHAR(1) |
| 1213 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1336 | ADD_COL | + COTA INTEGER |

