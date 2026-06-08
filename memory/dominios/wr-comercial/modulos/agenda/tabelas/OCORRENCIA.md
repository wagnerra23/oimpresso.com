---
table: OCORRENCIA
module: agenda
created_at_version: 659
last_modified_version: 975
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `OCORRENCIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 659;
- **Última mudança:** UPDATE 975;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TODOS_ATIVOS` | `VARCHAR(1)` | NULL |  | v659 | v659 |
| 2 | `TEM_RESSARCIMENTO` | `VARCHAR(1)` | NULL |  | v975 | v975 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 659 | ADD_COL | + TODOS_ATIVOS VARCHAR(1) |
| 975 | ADD_COL | + TEM_RESSARCIMENTO VARCHAR(1) |

