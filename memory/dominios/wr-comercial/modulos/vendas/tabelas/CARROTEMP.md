---
table: CARROTEMP
module: vendas
created_at_version: 15
last_modified_version: 758
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CARROTEMP`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 15;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODCARRO` | `INTEGER` | NOT NULL | v15 | v15 |
| 2 | `CODLOCAL` | `INTEGER` | NOT NULL | v15 | v15 |
| 3 | `CODMARCA` | `INTEGER` | NOT NULL | v15 | v15 |
| 4 | `COMP` | `DOUBLE PRECISION` | NULL | v15 | v15 |
| 5 | `LARG` | `DOUBLE PRECISION` | NULL | v15 | v15 |
| 6 | `VALOR` | `DOUBLE PRECISION` | NULL | v15 | v15 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 15 | CREATE | CREATE TABLE com 6 colunas |
| 758 | DROP_COL | - CODTIPOFILME |

