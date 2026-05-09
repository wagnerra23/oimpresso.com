---
table: EQUIPAMENTO_FIPE_VALOR
module: equipamento
created_at_version: 1458
last_modified_version: 1460
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_FIPE_VALOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1458;
- **Última mudança:** UPDATE 1460;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1458 | v1458 |
| 2 | `DESCRICAO` | `VARCHAR(100)` | NULL | v1458 | v1458 |
| 3 | `CODTABFIPE_VALOR` | `INTEGER` | NULL | v1458 | v1458 |
| 4 | `CODTABFIPE_VALOR_EQUIPAMENTO` | `INTEGER` | NULL | v1458 | v1458 |
| 5 | `CODEQUIPAMENTO` | `INTEGER` | NULL | v1458 | v1458 |
| 6 | `FIPE_PERCENTUAL` | `DOUBLE PRECISION` | NULL | v1458 | v1458 |
| 7 | `VALOR_FIPE` | `DOUBLE PRECISION` | NULL | v1458 | v1458 |
| 8 | `VALOR_MERCADO` | `DOUBLE PRECISION` | NULL | v1458 | v1458 |
| 9 | `VALOR_BASE_EQUIPAMENTO` | `DOUBLE PRECISION` | NULL | v1458 | v1458 |
| 10 | `VALOR_CONTRIBUICAO_ASSOCIADO` | `DOUBLE PRECISION` | NULL | v1458 | v1458 |
| 11 | `OBSERVACAO` | `VARCHAR(500)` | NULL | v1458 | v1458 |
| 12 | `ALTERA_VALOR_PELA_FIPE` | `VARCHAR(1)` | NULL | v1460 | v1460 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1458 | CREATE | CREATE TABLE com 11 colunas |
| 1460 | ADD_COL | + ALTERA_VALOR_PELA_FIPE VARCHAR(1) |

