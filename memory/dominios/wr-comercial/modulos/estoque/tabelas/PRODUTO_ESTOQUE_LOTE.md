---
table: PRODUTO_ESTOQUE_LOTE
module: estoque
created_at_version: 233
last_modified_version: 233
target_version: 1468
columns_count: 7
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_ESTOQUE_LOTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 233;
- **Última mudança:** UPDATE 233;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | v233 | v233 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | v233 | v233 |
| 3 | `LOTE` | `INTEGER` | NOT NULL | v233 | v233 |
| 4 | `DT_ENTRADA` | `TIMESTAMP` | NULL | v233 | v233 |
| 5 | `DT_FINALIZADO` | `TIMESTAMP` | NULL | v233 | v233 |
| 6 | `CODNF_ENTRADA` | `VARCHAR(10)` | NULL | v233 | v233 |
| 7 | `QUANT` | `DOUBLE PRECISION` | NULL | v233 | v233 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 233 | CREATE | CREATE TABLE com 7 colunas |

