---
table: NF_ENTRADA_CENTRO_TRABALHO
module: nfe
created_at_version: 659
last_modified_version: 751
target_version: 1468
columns_count: 8
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_CENTRO_TRABALHO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 659;
- **Última mudança:** UPDATE 751;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v659 | v659 |
| 2 | `CODNF_ENTRADA_PRODUTO` | `INTEGER` | NOT NULL | v659 | v659 |
| 3 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | v659 | v659 |
| 4 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | v659 | v659 |
| 5 | `DESCRICAO` | `VARCHAR(150)` | NULL | v659 | v659 |
| 6 | `TEMPO` | `DOUBLE PRECISION` | NULL | v659 | v659 |
| 7 | `CODPRODUTO_CT_PRE_REQUISITO` | `INTEGER` | NULL | v659 | v659 |
| 8 | `SEQUENCIA` | `INTEGER` | NULL | v659 | v659 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 659 | CREATE | CREATE TABLE com 13 colunas |
| 675 | ADD_COL | + CUSTO_VENDA DOUBLE PRECISION |
| 675 | DROP_COL | - CUSTO |
| 751 | DROP_COL | - CUSTO_VENDA |
| 751 | DROP_COL | - CUSTO_EXTRA |
| 751 | DROP_COL | - CUSTO_EXTRA_TOTAL |
| 751 | DROP_COL | - MARGEM |
| 751 | DROP_COL | - VALOR |

