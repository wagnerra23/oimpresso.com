---
table: DASHBOARDS_ATALHO_RAPIDO
module: bi
created_at_version: 1406
last_modified_version: 1406
target_version: 1468
columns_count: 2
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DASHBOARDS_ATALHO_RAPIDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1406;
- **Última mudança:** UPDATE 1406;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODDASHBOARD` | `INTEGER` | NOT NULL | v1406 | v1406 |
| 2 | `CODATALHO_RAPIDO` | `INTEGER` | NOT NULL | v1406 | v1406 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 994 | CREATE | CREATE TABLE com 2 colunas |
| 1406 | CREATE | CREATE TABLE com 2 colunas |

