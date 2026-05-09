---
table: SINTEGRA_R60M
module: nfe
created_at_version: 735
last_modified_version: 735
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_R60M`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 735;
- **Última mudança:** UPDATE 735;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v735 | v735 |
| 2 | `CODSINTEGRA` | `INTEGER` | NULL | v735 | v735 |
| 3 | `DT_EMISSAO` | `DATE` | NULL | v735 | v735 |
| 4 | `NUM_SERIE` | `VARCHAR(20)` | NULL | v735 | v735 |
| 5 | `NUM_ORDEM` | `INTEGER` | NULL | v735 | v735 |
| 6 | `MODELO_DOC` | `VARCHAR(2)` | NULL | v735 | v735 |
| 7 | `COO_INICIAL` | `INTEGER` | NULL | v735 | v735 |
| 8 | `COO_FINAL` | `INTEGER` | NULL | v735 | v735 |
| 9 | `CRZ` | `INTEGER` | NULL | v735 | v735 |
| 10 | `CRO` | `INTEGER` | NULL | v735 | v735 |
| 11 | `TOTAL_BRUTO` | `DOUBLE PRECISION` | NULL | v735 | v735 |
| 12 | `GRANDE_TOTAL` | `DOUBLE PRECISION` | NULL | v735 | v735 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 735 | CREATE | CREATE TABLE com 12 colunas |

