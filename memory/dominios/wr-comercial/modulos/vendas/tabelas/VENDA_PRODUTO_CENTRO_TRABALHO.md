---
table: VENDA_PRODUTO_CENTRO_TRABALHO
module: vendas
created_at_version: 502
last_modified_version: 758
target_version: 1468
columns_count: 8
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_PRODUTO_CENTRO_TRABALHO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 502;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v502 | v502 |
| 2 | `CODVENDA` | `VARCHAR(10)` | NOT NULL | v502 | v502 |
| 3 | `CODVENDA_PRODUTO` | `INTEGER` | NOT NULL | v502 | v502 |
| 4 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | v502 | v502 |
| 5 | `TEMPO` | `DOUBLE PRECISION` | NULL | v502 | v502 |
| 6 | `PRE_REQUISITO_CENTRO_TRABALHO` | `INTEGER` | NULL | v531 | v531 |
| 7 | `CODVENDA_PRODUTO_CT_PRE_REQ` | `INTEGER` | NULL | v580 | v580 |
| 8 | `SEQUENCIA` | `INTEGER, ADD DESCRICAO VARCHAR(150)` | NULL | v580 | v580 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 495 | CREATE | CREATE TABLE com 6 colunas |
| 502 | CREATE | CREATE TABLE com 6 colunas |
| 531 | ADD_COL | + PRE_REQUISITO_CENTRO_TRABALHO INTEGER |
| 580 | ADD_COL | + CODVENDA_PRODUTO_CT_PRE_REQ INTEGER |
| 580 | ADD_COL | + SEQUENCIA INTEGER, ADD DESCRICAO VARCHAR(150) |
| 636 | ADD_COL | + CUSTO double precision |
| 639 | ADD_COL | + MARGEM DOUBLE PRECISION |
| 651 | ADD_COL | + CUSTO_EXTRA DOUBLE PRECISION |
| 651 | ADD_COL | + CUSTO_EXTRA_TOTAL DOUBLE PRECISION |
| 678 | RENAME_COL | × CUSTO → CUSTO_VENDA |
| 758 | DROP_COL | - VALOR |
| 758 | DROP_COL | - CUSTO_VENDA |
| 758 | DROP_COL | - MARGEM |
| 758 | DROP_COL | - CUSTO_EXTRA |
| 758 | DROP_COL | - CUSTO_EXTRA_TOTAL |

