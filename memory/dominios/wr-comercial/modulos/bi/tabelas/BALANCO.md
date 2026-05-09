---
table: BALANCO
module: bi
created_at_version: 24
last_modified_version: 1064
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BALANCO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 24;
- **Última mudança:** UPDATE 1064;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ESTOQUE_LOCAL` | `VARCHAR(15)` | NULL |  | v24 | v24 |
| 2 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1064 | v1064 |
| 3 | `DT_ALTERACAO` | `TimeStamp` | NULL |  | v1064 | v1064 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 24 | ADD_COL | + ESTOQUE_LOCAL VARCHAR(15) |
| 164 | ALTER_TYPE | ~ DESCRICAO TYPE varchar(150) |
| 1064 | ADD_COL | + ATIVO VARCHAR(1) |
| 1064 | ADD_COL | + DT_ALTERACAO TimeStamp |

