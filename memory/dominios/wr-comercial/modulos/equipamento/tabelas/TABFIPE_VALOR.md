---
table: TABFIPE_VALOR
module: equipamento
created_at_version: 1214
last_modified_version: 1214
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TABFIPE_VALOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1214;
- **Última mudança:** UPDATE 1214;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1214 | v1214 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1214 | v1214 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1214 | ADD_COL | + ATIVO VARCHAR(1) |
| 1214 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

