---
table: LOCAL
module: cadastros
created_at_version: 172
last_modified_version: 729
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `LOCAL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 172;
- **Última mudança:** UPDATE 729;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `timestamp` | NULL |  | v319 | v319 |
| 2 | `ATIVO` | `VARCHAR(1)` | NULL |  | v729 | v729 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 172 | ALTER_TYPE | ~ DESCRICAO TYPE varchar (150) |
| 319 | ADD_COL | + DT_ALTERACAO timestamp |
| 729 | ADD_COL | + ATIVO VARCHAR(1) |

