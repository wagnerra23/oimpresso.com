---
table: META_DETALHE
module: bi
created_at_version: 1147
last_modified_version: 1148
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `META_DETALHE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1147;
- **Última mudança:** UPDATE 1148;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODMETA` | `INTEGER` | NOT NULL | v1147 | v1147 |
| 2 | `TABELA` | `VARCHAR(100)` | NOT NULL | v1147 | v1147 |
| 3 | `CODTABELA` | `VARCHAR(20)` | NOT NULL | v1147 | v1147 |
| 4 | `PERCENTUAL` | `DOUBLE PRECISION` | NULL | v1147 | v1147 |
| 5 | `VALOR` | `DOUBLE PRECISION` | NULL | v1147 | v1147 |
| 6 | `QUANT` | `DOUBLE PRECISION` | NULL | v1148 | v1148 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1147 | CREATE | CREATE TABLE com 5 colunas |
| 1148 | ADD_COL | + QUANT DOUBLE PRECISION |

