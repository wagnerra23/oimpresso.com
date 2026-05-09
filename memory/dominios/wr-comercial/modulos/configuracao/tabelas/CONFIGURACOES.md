---
table: CONFIGURACOES
module: configuracao
created_at_version: 118
last_modified_version: 723
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 118;
- **Última mudança:** UPDATE 723;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODEMPRESA` | `INTEGER` | NOT NULL | v118 | v118 |
| 2 | `CONFIG` | `VARCHAR(255) CHARACTER SET WIN1252` | NOT NULL | v118 | v552 |
| 3 | `VALOR` | `VARCHAR(5000)` | NOT NULL | v118 | v142 |
| 4 | `CODUSUARIO` | `integer default 0` | NOT NULL | v181 | v181 |
| 5 | `DT_ALTERACAO` | `timestamp` | NULL | v723 | v723 |
| 6 | `CODUSUARIO_ALTERACAO` | `integer` | NULL | v723 | v723 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 118 | CREATE | CREATE TABLE com 3 colunas |
| 142 | ALTER_TYPE | ~ VALOR TYPE VARCHAR(5000) |
| 181 | ADD_COL | + CODUSUARIO integer default 0 |
| 552 | ALTER_TYPE | ~ CONFIG TYPE VARCHAR(255) CHARACTER SET WIN1252 |
| 723 | ADD_COL | + DT_ALTERACAO timestamp |
| 723 | ADD_COL | + CODUSUARIO_ALTERACAO integer |

