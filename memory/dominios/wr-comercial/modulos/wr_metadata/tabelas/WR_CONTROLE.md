---
table: WR_CONTROLE
module: wr_metadata
created_at_version: 1337
last_modified_version: 1435
target_version: 1468
columns_count: 9
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_CONTROLE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1337;
- **Última mudança:** UPDATE 1435;
- **Total colunas (versão 1468):** 9

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1337 | v1337 |
| 2 | `DESCRICAO` | `VARCHAR(255)` | NULL |  | v1337 | v1337 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1337 | v1337 |
| 4 | `CONFIGURACAO` | `VARCHAR(255)` | NULL |  | v1337 | v1337 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1337 | v1337 |
| 6 | `OBSERVACAO` | `VARCHAR(1000)` | NULL |  | v1340 | v1340 |
| 7 | `REFERENCIA` | `VARCHAR(50)` | NULL |  | v1340 | v1340 |
| 8 | `NUVEM_TAGS` | `VARCHAR(1000)` | NULL |  | v1340 | v1430 |
| 9 | `PATH_APP` | `VARCHAR(255)` | NULL |  | v1435 | v1435 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1337 | CREATE | CREATE TABLE com 5 colunas |
| 1340 | ADD_COL | + OBSERVACAO VARCHAR(1000) |
| 1340 | ADD_COL | + REFERENCIA VARCHAR(50) |
| 1340 | ADD_COL | + CODWR_APP INTEGER |
| 1340 | ADD_COL | + TAGS VARCHAR(1000) |
| 1430 | RENAME_COL | × TAGS → NUVEM_TAGS |
| 1431 | RENAME_COL | × TAGS → NUVEM_TAGS |
| 1435 | ADD_COL | + PATH_APP VARCHAR(255) |
| 1435 | DROP_COL | - CODWR_APP |

