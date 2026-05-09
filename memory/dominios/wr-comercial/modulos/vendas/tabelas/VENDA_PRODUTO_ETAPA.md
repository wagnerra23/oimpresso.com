---
table: VENDA_PRODUTO_ETAPA
module: vendas
created_at_version: 1231
last_modified_version: 1251
target_version: 1468
columns_count: 15
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_PRODUTO_ETAPA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1231;
- **Última mudança:** UPDATE 1251;
- **Total colunas (versão 1468):** 15

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1231 | v1231 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1231 | v1231 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL | v1231 | v1231 |
| 4 | `CODVENDA` | `VARCHAR(30)` | NULL | v1231 | v1251 |
| 5 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | v1231 | v1231 |
| 6 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | v1231 | v1231 |
| 7 | `TEMPO_HORAS` | `DOUBLE PRECISION` | NULL | v1231 | v1231 |
| 8 | `ORDEM` | `DOUBLE PRECISION` | NULL | v1231 | v1231 |
| 9 | `DESCRICAO` | `VARCHAR(100)` | NULL | v1231 | v1231 |
| 10 | `CODPRODUTO` | `VARCHAR(15)` | NULL | v1233 | v1233 |
| 11 | `CODPRODUTO_COMPOSICAO` | `INTEGER` | NULL | v1233 | v1233 |
| 12 | `CODETAPA_ORIGINAL` | `INTEGER` | NULL | v1233 | v1233 |
| 13 | `CODPRODUTO_ETAPA` | `INTEGER` | NULL | v1233 | v1233 |
| 14 | `TEMPO_STRING` | `VARCHAR(150)` | NULL | v1233 | v1233 |
| 15 | `TEMPO_MINUTOS` | `INTEGER` | NULL | v1233 | v1233 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1231 | CREATE | CREATE TABLE com 9 colunas |
| 1233 | ADD_COL | + CODPRODUTO VARCHAR(15) |
| 1233 | ADD_COL | + CODPRODUTO_COMPOSICAO INTEGER |
| 1233 | ADD_COL | + CODETAPA_ORIGINAL INTEGER |
| 1233 | ADD_COL | + CODPRODUTO_ETAPA INTEGER |
| 1233 | ADD_COL | + TEMPO_STRING VARCHAR(150) |
| 1233 | ADD_COL | + TEMPO_MINUTOS INTEGER |
| 1251 | ALTER_TYPE | ~ CODVENDA TYPE VARCHAR(30) |

