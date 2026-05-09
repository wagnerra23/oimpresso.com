---
table: CLIENTES_PRODUTO
module: cadastros
created_at_version: 102
last_modified_version: 786
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CLIENTES_PRODUTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 102;
- **Última mudança:** UPDATE 786;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 2 | `PERC_DESCONTO` | `DOUBLE PRECISION` | NULL |  | v388 | v388 |
| 3 | `TEM_MARGEM_FIXA_CONTIBUICAO` | `VARCHAR(1)` | NULL |  | v786 | v786 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 299 | ADD_COL | + PRODUTO_TIPO VARCHAR(15) |
| 325 | DROP_COL | - PRODUTO_TIPO |
| 388 | ADD_COL | + PERC_DESCONTO DOUBLE PRECISION |
| 786 | ADD_COL | + TEM_MARGEM_FIXA_CONTIBUICAO VARCHAR(1) |

