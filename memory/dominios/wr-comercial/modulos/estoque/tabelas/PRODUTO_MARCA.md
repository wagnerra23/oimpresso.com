---
table: PRODUTO_MARCA
module: estoque
created_at_version: 728
last_modified_version: 1250
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_MARCA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 728;
- **Última mudança:** UPDATE 1250;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 3 | `OIMPRESSO_ATIVO` | `VARCHAR(1)` | NULL |  | v1250 | v1250 |
| 4 | `OIMPRESSO_CODIGO` | `VARCHAR(15)` | NULL |  | v1250 | v1250 |
| 5 | `OIMPRESSO_DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1250 | v1250 |
| 6 | `OIMPRESSO_UPDATED_AT` | `TIMESTAMP` | NULL |  | v1250 | v1250 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1250 | ADD_COL | + OIMPRESSO_ATIVO VARCHAR(1) |
| 1250 | ADD_COL | + OIMPRESSO_CODIGO VARCHAR(15) |
| 1250 | ADD_COL | + OIMPRESSO_DT_ALTERACAO TIMESTAMP |
| 1250 | ADD_COL | + OIMPRESSO_UPDATED_AT TIMESTAMP |

