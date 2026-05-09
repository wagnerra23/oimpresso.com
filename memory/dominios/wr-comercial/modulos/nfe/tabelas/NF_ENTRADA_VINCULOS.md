---
table: NF_ENTRADA_VINCULOS
module: nfe
created_at_version: 1123
last_modified_version: 1165
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_VINCULOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1123;
- **Última mudança:** UPDATE 1165;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1123 | v1123 |
| 2 | `TIPO_VINCULO` | `VARCHAR(40)` | NULL | v1123 | v1123 |
| 3 | `CODNF_ENTRADA_PRINCIPAL` | `CHAR(30)` | NULL | v1123 | v1123 |
| 4 | `CODNF_ENTRADA_VINCULADA` | `VARCHAR(30)` | NULL | v1123 | v1123 |
| 5 | `PORCENTAGEM_RATEIO` | `DOUBLE PRECISION` | NULL | v1165 | v1165 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1123 | CREATE | CREATE TABLE com 4 colunas |
| 1165 | ADD_COL | + PORCENTAGEM_RATEIO DOUBLE PRECISION |

