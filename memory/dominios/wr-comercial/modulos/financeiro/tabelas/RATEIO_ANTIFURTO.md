---
table: RATEIO_ANTIFURTO
module: financeiro
created_at_version: 726
last_modified_version: 735
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `RATEIO_ANTIFURTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 726;
- **Última mudança:** UPDATE 735;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `QUANT_COTA` | `DOUBLE PRECISION` | NULL |  | v735 | v735 |
| 2 | `VALOR_COTA` | `DOUBLE PRECISION` | NULL |  | v735 | v735 |
| 3 | `TOTAL` | `DOUBLE PRECISION` | NULL |  | v735 | v735 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 726 | ADD_COL | + QUANT_COTA DOUBLE PRECISION |
| 726 | ADD_COL | + VALOR_COTA DOUBLE PRECISION |
| 726 | ADD_COL | + TOTAL DOUBLE PRECISION |
| 735 | ADD_COL | + QUANT_COTA DOUBLE PRECISION |
| 735 | ADD_COL | + VALOR_COTA DOUBLE PRECISION |
| 735 | ADD_COL | + TOTAL DOUBLE PRECISION |

