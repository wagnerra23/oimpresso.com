---
table: NF_CEST
module: nfe
created_at_version: 959
last_modified_version: 959
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_CEST`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 959;
- **Última mudança:** UPDATE 959;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(9)` | NOT NULL | v959 | v959 |
| 2 | `DESCRICAO` | `VARCHAR(501)` | NULL | v959 | v959 |
| 3 | `CODNF_NCM` | `VARCHAR(13)` | NULL | v959 | v959 |
| 4 | `ATIVO` | `VARCHAR(3)` | NULL | v959 | v959 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v959 | v959 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 959 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(500) CHARACTER SET WIN1252 |
| 959 | CREATE | CREATE TABLE com 5 colunas |

