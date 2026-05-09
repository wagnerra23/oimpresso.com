---
table: ARQUIVOS
module: ui_metadata
created_at_version: 295
last_modified_version: 690
target_version: 1468
columns_count: 10
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `ARQUIVOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 295;
- **Última mudança:** UPDATE 690;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `DESCRICAO` | `VARCHAR(255)` | NOT NULL | v295 | v295 |
| 2 | `TIPO` | `VARCHAR(30)` | NOT NULL | v295 | v295 |
| 3 | `ARQUIVO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL | v295 | v295 |
| 4 | `FORM` | `VARCHAR(40)` | NULL | v295 | v304 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v295 | v295 |
| 6 | `CODEMPRESA` | `integer` | NULL | v326 | v326 |
| 7 | `CODUSUARIO` | `integer` | NULL | v329 | v329 |
| 8 | `OBSERVACAO` | `VARCHAR(6000)` | NULL | v331 | v331 |
| 9 | `CODARQUIVOS_RELATORIO` | `INTEGER` | NULL | v624 | v624 |
| 10 | `MD5` | `VARCHAR(50)` | NULL | v690 | v690 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 295 | CREATE | CREATE TABLE com 5 colunas |
| 304 | ALTER_TYPE | ~ FORM TYPE VARCHAR(40) |
| 326 | ADD_COL | + CODEMPRESA integer |
| 327 | ADD_COL | + CODUSUARIO integer |
| 329 | ADD_COL | + CODUSUARIO integer |
| 331 | ADD_COL | + OBSERVACAO VARCHAR(6000) |
| 624 | ADD_COL | + CODARQUIVOS_RELATORIO INTEGER |
| 687 | ADD_COL | + MD5 VARCHAR(50) |
| 690 | ADD_COL | + MD5 VARCHAR(50) |

