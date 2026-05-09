---
table: NF_ENTRADA_PARCELAS
module: nfe
created_at_version: 34
last_modified_version: 1427
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_PARCELAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 34;
- **Última mudança:** UPDATE 1427;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v34 | v34 |
| 2 | `DT_COMPETENCIA` | `TIMESTAMP` | NULL |  | v1327 | v1327 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 34 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 227 | ALTER_TYPE | ~ RAZAOSOCIAL TYPE varchar (150) |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 789 | ADD_COL | + TEM_VALIDACAO VARCHAR(1), ADD VALIDACAO_RESTRITIVA INTEGER, ADD VALIDACAO_INFORMATIVA INTEGER |
| 797 | DROP_COL | - TEM_VALIDACAO |
| 797 | DROP_COL | - VALIDACAO_RESTRITIVA |
| 797 | DROP_COL | - VALIDACAO_INFORMATIVA |
| 1327 | ADD_COL | + DT_COMPETENCIA TIMESTAMP |
| 1427 | ALTER_TYPE | ~ HISTORICO TYPE VARCHAR(600) CHARACTER SET WIN1252 |

