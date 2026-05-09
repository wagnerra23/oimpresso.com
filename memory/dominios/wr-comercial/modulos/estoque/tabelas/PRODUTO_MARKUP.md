---
table: PRODUTO_MARKUP
module: estoque
created_at_version: 502
last_modified_version: 784
target_version: 1468
columns_count: 9
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_MARKUP`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 502;
- **Última mudança:** UPDATE 784;
- **Total colunas (versão 1468):** 9

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v502 | v502 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v502 | v502 |
| 3 | `PERC_CUSTO_FIXO` | `DOUBLE PRECISION` | NULL |  | v502 | v502 |
| 4 | `PERC_CUSTO_FINANCEIRO` | `DOUBLE PRECISION` | NULL |  | v502 | v502 |
| 5 | `PERC_LUCRO_DESEJADO` | `DOUBLE PRECISION` | NULL |  | v502 | v502 |
| 6 | `PERC_CUSTO_VARIAVEL` | `DOUBLE PRECISION` | NULL |  | v502 | v502 |
| 7 | `MARKUP` | `DOUBLE PRECISION` | NULL |  | v502 | v502 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v783 | v783 |
| 9 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v784 | v784 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 502 | CREATE | CREATE TABLE com 7 colunas |
| 783 | ADD_COL | + ATIVO VARCHAR(1) |
| 783 | ADD_COL | + ATIVO VARCHAR(1) |
| 784 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

