---
table: ANEXO
module: ui_metadata
created_at_version: 480
last_modified_version: 1124
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `ANEXO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 480;
- **Última mudança:** UPDATE 1124;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v480 | v480 |
| 2 | `DESCRICAO` | `VARCHAR(255)` | NULL | v480 | v480 |
| 3 | `TIPO` | `VARCHAR(30)` | NULL | v480 | v480 |
| 4 | `CODEMPRESA` | `INTEGER` | NULL | v480 | v480 |
| 5 | `CODUSUARIO` | `INTEGER` | NULL | v480 | v480 |
| 6 | `CODTABELA` | `VARCHAR(255)` | NULL | v480 | v480 |
| 7 | `CODARQUIVOS_ANEXOS` | `INTEGER` | NULL | v480 | v480 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v480 | v480 |
| 9 | `DT_CADASTRO` | `TIMESTAMP` | NULL | v480 | v480 |
| 10 | `CODPESSOA` | `VARCHAR(15)` | NULL | v522 | v522 |
| 11 | `TIPO_PASTA` | `VARCHAR(30)` | NULL | v1124 | v1124 |
| 12 | `CODPRODUTO` | `VARCHAR(15)` | NULL | v1124 | v1124 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 480 | CREATE | CREATE TABLE com 9 colunas |
| 520 | ADD_COL | + CODPESSOA VARCHAR(15) |
| 522 | ADD_COL | + CODPESSOA VARCHAR(15) |
| 1124 | ADD_COL | + TIPO_PASTA VARCHAR(30) |
| 1124 | ADD_COL | + CODPRODUTO VARCHAR(15) |

