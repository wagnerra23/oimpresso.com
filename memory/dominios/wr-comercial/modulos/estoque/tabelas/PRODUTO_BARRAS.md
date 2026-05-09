---
table: PRODUTO_BARRAS
module: estoque
created_at_version: 17
last_modified_version: 440
target_version: 1468
columns_count: 1
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_BARRAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 17;
- **Última mudança:** UPDATE 440;
- **Total colunas (versão 1468):** 1

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `FAMILIA` | `integer` | NULL |  | v261 | v261 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 17 | ALTER_TYPE | ~ CODBARRAS TYPE NUMERIC(15) |
| 261 | ADD_COL | + FAMILIA integer |
| 431 | ALTER_TYPE | ~ CODBARRAS TYPE varchar (50) |
| 440 | ALTER_TYPE | ~ CODBARRAS TYPE varchar(60) |

