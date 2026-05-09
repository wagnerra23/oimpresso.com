---
table: WR_APP
module: wr_metadata
created_at_version: 1340
last_modified_version: 1430
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_APP`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1340;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1340 | v1340 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1340 | v1340 |
| 3 | `CODMODULO` | `INTEGER` | NULL |  | v1340 | v1340 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1340 | v1340 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1343 | v1343 |
| 6 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1340 | CREATE | CREATE TABLE com 4 colunas |
| 1343 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1430 | ADD_COL | + PATH VARCHAR(255) |

