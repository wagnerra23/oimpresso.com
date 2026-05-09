---
table: TABFIPE_VEICULO
module: equipamento
created_at_version: 526
last_modified_version: 526
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TABFIPE_VEICULO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 526;
- **Última mudança:** UPDATE 526;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v526 | v526 |
| 2 | `CODTABFIPE_MARCA` | `INTEGER` | NOT NULL | v526 | v526 |
| 3 | `"KEY"` | `VARCHAR(50)` | NULL | v526 | v526 |
| 4 | `NAME` | `VARCHAR(100)` | NULL | v526 | v526 |
| 5 | `FIPE_NAME` | `VARCHAR(100)` | NULL | v526 | v526 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 526 | CREATE | CREATE TABLE com 5 colunas |

