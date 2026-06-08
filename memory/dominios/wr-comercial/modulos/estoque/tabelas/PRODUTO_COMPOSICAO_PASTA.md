---
table: PRODUTO_COMPOSICAO_PASTA
module: estoque
created_at_version: 631
last_modified_version: 631
target_version: 1468
columns_count: 1
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_COMPOSICAO_PASTA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 631;
- **Última mudança:** UPDATE 631;
- **Total colunas (versão 1468):** 1

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `FORMULA_DESC` | `VARCHAR(50)` | NULL |  | v631 | v631 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 631 | ADD_COL | + FORMULA_DESC VARCHAR(50) |

