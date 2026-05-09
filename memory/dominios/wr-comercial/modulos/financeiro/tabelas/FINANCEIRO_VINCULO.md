---
table: FINANCEIRO_VINCULO
module: financeiro
created_at_version: 608
last_modified_version: 961
target_version: 1468
columns_count: 13
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_VINCULO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 608;
- **Última mudança:** UPDATE 961;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v608 | v608 |
| 2 | `ORIGEM_CODIGO` | `INTEGER` | NOT NULL | v608 | v608 |
| 3 | `ORIGEM_CODPEDIDO` | `VARCHAR(10)` | NOT NULL | v608 | v608 |
| 4 | `ORIGEM_CODEMPRESA` | `VARCHAR(10)` | NOT NULL | v608 | v608 |
| 5 | `DESTINO_CODIGO` | `INTEGER` | NOT NULL | v608 | v608 |
| 6 | `DESTINO_CODPEDIDO` | `VARCHAR(10)` | NOT NULL | v608 | v608 |
| 7 | `DESTINO_CODEMPRESA` | `VARCHAR(10)` | NOT NULL | v608 | v608 |
| 8 | `OBSERVACAO` | `VARCHAR(1000)` | NULL | v608 | v608 |
| 9 | `CODUSUARIO` | `INTEGER` | NOT NULL | v608 | v608 |
| 10 | `DT_VINCULO` | `TIMESTAMP` | NULL | v608 | v608 |
| 11 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v608 | v608 |
| 12 | `PERCENTUAL` | `DOUBLE PRECISION` | NULL | v961 | v961 |
| 13 | `TOTAL` | `DOUBLE PRECISION` | NULL | v961 | v961 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 608 | CREATE | CREATE TABLE com 11 colunas |
| 961 | ADD_COL | + PERCENTUAL DOUBLE PRECISION |
| 961 | ADD_COL | + TOTAL DOUBLE PRECISION |

