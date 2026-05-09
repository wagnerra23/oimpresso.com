---
table: TIPOEQUIPAMENTOS
module: equipamento
created_at_version: 1215
last_modified_version: 1215
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TIPOEQUIPAMENTOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1215;
- **Última mudança:** UPDATE 1215;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1215 | v1215 |
| 2 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1215 | v1215 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1215 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1215 | ADD_COL | + ATIVO VARCHAR(1) |

