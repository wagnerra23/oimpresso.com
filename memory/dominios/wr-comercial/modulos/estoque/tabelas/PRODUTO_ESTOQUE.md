---
table: PRODUTO_ESTOQUE
module: estoque
created_at_version: 24
last_modified_version: 102
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_ESTOQUE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 24;
- **Última mudança:** UPDATE 102;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `PRINCIPAL` | `DOUBLE PRECISION` | NULL |  | v24 | v24 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 24 | DROP_COL | - SIMPLES |
| 24 | DROP_COL | - FISCAL |
| 24 | ADD_COL | + PRINCIPAL DOUBLE PRECISION |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

