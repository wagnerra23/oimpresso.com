---
table: AGENDA_TAREFAS
module: agenda
created_at_version: 12
last_modified_version: 12
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_TAREFAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 12;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(40)` | NOT NULL |  | v12 | v12 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v12 | v12 |
| 3 | `OBSERVACAO` | `VARCHAR(255)` | NULL |  | v12 | v12 |
| 4 | `PRIORIDADE` | `INTEGER` | NULL |  | v12 | v12 |
| 5 | `COMPLETO` | `DOUBLE PRECISION` | NULL |  | v12 | v12 |
| 6 | `DT_COMPLETO` | `TIMESTAMP` | NULL |  | v12 | v12 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | CREATE | CREATE TABLE com 1 colunas |
| 12 | ADD_COL | + DESCRICAO VARCHAR(50) |
| 12 | ADD_COL | + OBSERVACAO VARCHAR(255) |
| 12 | ADD_COL | + PRIORIDADE INTEGER |
| 12 | ADD_COL | + COMPLETO DOUBLE PRECISION |
| 12 | ADD_COL | + DT_COMPLETO TIMESTAMP |
| 12 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(50) |
| 12 | ALTER_TYPE | ~ OBSERVACAO TYPE VARCHAR(255) |
| 12 | ALTER_TYPE | ~ PRIORIDADE TYPE INTEGER |
| 12 | ALTER_TYPE | ~ COMPLETO TYPE DOUBLE PRECISION |
| 12 | ALTER_TYPE | ~ DT_COMPLETO TYPE TIMESTAMP |

