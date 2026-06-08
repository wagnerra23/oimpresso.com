---
table: FINANCEIRO_HISTORICO
module: financeiro
created_at_version: 12
last_modified_version: 499
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_HISTORICO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 499;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 2 | `CREDITO` | `double precision` | NULL |  | v246 | v246 |
| 3 | `AGRUPADOR` | `integer` | NULL |  | v424 | v424 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | ALTER_TYPE | ~ HISTORICO TYPE VARCHAR(600) CHARACTER SET NONE |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 227 | ALTER_TYPE | ~ RAZAOSOCIAL TYPE varchar (150) |
| 246 | ADD_COL | + CREDITO double precision |
| 424 | ADD_COL | + AGRUPADOR integer |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |

