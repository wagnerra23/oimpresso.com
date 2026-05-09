---
table: TELEFONES
module: cadastros
created_at_version: 744
last_modified_version: 744
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TELEFONES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 744;
- **Última mudança:** UPDATE 744;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ATIVO` | `VARCHAR(1)` | NULL |  | v744 | v744 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v744 | v744 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 744 | ADD_COL | + ATIVO VARCHAR(1) |
| 744 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

