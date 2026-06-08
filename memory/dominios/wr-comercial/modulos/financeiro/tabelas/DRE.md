---
table: DRE
module: financeiro
created_at_version: 495
last_modified_version: 1381
target_version: 1468
columns_count: 15
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DRE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 495;
- **Última mudança:** UPDATE 1381;
- **Total colunas (versão 1468):** 15

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v495 | v495 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v495 | v495 |
| 3 | `REGIME` | `VARCHAR(20)` | NULL |  | v495 | v495 |
| 4 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v495 | v495 |
| 5 | `DT_FIM` | `TIMESTAMP` | NULL |  | v495 | v495 |
| 6 | `DT_FECHAMENTO` | `TIMESTAMP` | NULL |  | v495 | v495 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v495 | v495 |
| 8 | `CODRESULTADO_EXERCICIO` | `INTEGER` | NULL |  | v495 | v495 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v730 | v730 |
| 10 | `TOTAL_RECEITAS` | `DOUBLE PRECISION, ADD TOTAL_DESPESAS DOUBLE PRECISION, ADD SALDO DOUBLE PRECISION` | NULL |  | v948 | v948 |
| 11 | `META_LUCROBRUTO` | `FLOAT` | NULL |  | v1381 | v1381 |
| 12 | `META_LUCROLIQUIDO` | `FLOAT` | NULL |  | v1381 | v1381 |
| 13 | `META_CMV` | `FLOAT` | NULL |  | v1381 | v1381 |
| 14 | `META_EBITIDA` | `FLOAT` | NULL |  | v1381 | v1381 |
| 15 | `META_TAXAROLAGEM` | `FLOAT` | NULL |  | v1381 | v1381 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 495 | CREATE | CREATE TABLE com 8 colunas |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |
| 948 | ADD_COL | + TOTAL_RECEITAS DOUBLE PRECISION, ADD TOTAL_DESPESAS DOUBLE PRECISION, ADD SALDO DOUBLE PRECISION |
| 1381 | ADD_COL | + META_LUCROBRUTO FLOAT |
| 1381 | ADD_COL | + META_LUCROLIQUIDO FLOAT |
| 1381 | ADD_COL | + META_CMV FLOAT |
| 1381 | ADD_COL | + META_EBITIDA FLOAT |
| 1381 | ADD_COL | + META_TAXAROLAGEM FLOAT |

