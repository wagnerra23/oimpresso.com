---
table: NF_NCM_ST_UF
module: nfe
created_at_version: 1463
last_modified_version: 1463
target_version: 1468
columns_count: 9
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_NCM_ST_UF`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1463;
- **Última mudança:** UPDATE 1463;
- **Total colunas (versão 1468):** 9

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `integer` | NOT NULL | v1463 | v1463 |
| 2 | `CODNF_NCM` | `VARCHAR(10)` | NULL | v1463 | v1463 |
| 3 | `CODNF_CEST` | `VARCHAR(9)` | NULL | v1463 | v1463 |
| 4 | `UF_DESTINO` | `VARCHAR(2)` | NULL | v1463 | v1463 |
| 5 | `TEM_ST` | `CHAR(1)` | NULL | v1463 | v1463 |
| 6 | `MVA` | `NUMERIC(10,2)` | NULL | v1463 | v1463 |
| 7 | `ALIQUOTA_ST` | `NUMERIC(10,2)` | NULL | v1463 | v1463 |
| 8 | `ATIVO` | `CHAR(1)` | NULL | v1463 | v1463 |
| 9 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1463 | v1463 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1463 | CREATE | CREATE TABLE com 9 colunas |

