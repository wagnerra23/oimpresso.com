---
table: WR_API_NUMMUS
module: wr_metadata
created_at_version: 1375
last_modified_version: 1375
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_API_NUMMUS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1375;
- **Última mudança:** UPDATE 1375;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1375 | v1375 |
| 2 | `CODVENDA` | `VARCHAR(15)` | NULL | v1375 | v1375 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v1375 | v1375 |
| 4 | `TIPO` | `VARCHAR(50)` | NULL | v1375 | v1375 |
| 5 | `DESCRICAO` | `VARCHAR(500)` | NULL | v1375 | v1375 |
| 6 | `VALORCASHBACK` | `DOUBLE PRECISION` | NULL | v1375 | v1375 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1375 | CREATE | CREATE TABLE com 6 colunas |

