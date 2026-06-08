---
table: FORNECEDOR
module: cadastros
created_at_version: 6
last_modified_version: 110
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FORNECEDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 6;
- **Última mudança:** UPDATE 110;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `PROXIMIDADE` | `VARCHAR(50)` | NULL |  | v6 | v6 |
| 2 | `CRT` | `VARCHAR(50)` | NULL |  | v110 | v110 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 6 | ADD_COL | + PROXIMIDADE VARCHAR(50) |
| 110 | ADD_COL | + CRT VARCHAR(50) |

