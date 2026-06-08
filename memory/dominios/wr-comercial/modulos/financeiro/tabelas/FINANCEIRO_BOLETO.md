---
table: FINANCEIRO_BOLETO
module: financeiro
created_at_version: 11
last_modified_version: 1328
target_version: 1468
columns_count: 10
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_BOLETO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 11;
- **Última mudança:** UPDATE 1328;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DATA_ARQUIVO` | `TIMESTAMP` | NULL |  | v11 | v11 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 3 | `DT_GERACAO_ARQUIVO` | `TIMESTAMP` | NULL |  | v1312 | v1312 |
| 4 | `CODIGO_DATA_ARQUIVO` | `VARCHAR(50)` | NULL |  | v1328 | v1328 |
| 5 | `CHAVE` | `VARCHAR(100)` | NULL |  | v1328 | v1328 |
| 6 | `RETORNO` | `INTEGER` | NULL |  | v1328 | v1328 |
| 7 | `REMESSA` | `INTEGER` | NULL |  | v1328 | v1328 |
| 8 | `TEM_MIGRADO` | `VARCHAR(1)` | NULL |  | v1328 | v1328 |
| 9 | `CODIGO_MIGRADO` | `INTEGER` | NULL |  | v1328 | v1328 |
| 10 | `CODIGO_BKP` | `INTEGER` | NULL |  | v1328 | v1328 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 11 | ADD_COL | + DATA_ARQUIVO TIMESTAMP |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1312 | ADD_COL | + DT_GERACAO_ARQUIVO TIMESTAMP |
| 1328 | ADD_COL | + CODIGO_DATA_ARQUIVO VARCHAR(50) |
| 1328 | ADD_COL | + CHAVE VARCHAR(100) |
| 1328 | ADD_COL | + RETORNO INTEGER |
| 1328 | ADD_COL | + REMESSA INTEGER |
| 1328 | ADD_COL | + TEM_MIGRADO VARCHAR(1) |
| 1328 | ADD_COL | + CODIGO_MIGRADO INTEGER |
| 1328 | ADD_COL | + CODIGO_BKP INTEGER |

