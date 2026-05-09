---
table: HISTORICO_IMPRESSOES
module: wr_metadata
created_at_version: 1162
last_modified_version: 1162
target_version: 1468
columns_count: 8
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_IMPRESSOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1162;
- **Última mudança:** UPDATE 1162;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1162 | v1162 |
| 2 | `CODEMPRESA` | `VARCHAR(250) CHARACTER SET NONE` | NULL | v1162 | v1162 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v1162 | v1162 |
| 4 | `DATA` | `TIMESTAMP` | NULL | v1162 | v1162 |
| 5 | `FORMULARIO` | `VARCHAR(250) CHARACTER SET NONE` | NULL | v1162 | v1162 |
| 6 | `TABELA` | `VARCHAR(50) CHARACTER SET NONE` | NULL | v1162 | v1162 |
| 7 | `CHAVE_PK` | `VARCHAR(250) CHARACTER SET NONE` | NULL | v1162 | v1162 |
| 8 | `RELATORIO` | `VARCHAR(200)` | NULL | v1162 | v1162 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1162 | CREATE | CREATE TABLE com 8 colunas |

