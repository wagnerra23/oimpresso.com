---
table: TIPOOCORRENCIA
module: agenda
created_at_version: 1213
last_modified_version: 1213
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TIPOOCORRENCIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1213;
- **Última mudança:** UPDATE 1213;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1213 | v1213 |
| 2 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1213 | v1213 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1213 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1213 | ADD_COL | + ATIVO VARCHAR(1) |

