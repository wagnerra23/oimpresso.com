---
table: AGENDA_FILTRO
module: agenda
created_at_version: 112
last_modified_version: 554
target_version: 1468
columns_count: 13
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_FILTRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 112;
- **Última mudança:** UPDATE 554;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v112 | v112 |
| 2 | `DESCRICAO` | `VARCHAR(20)` | NULL | v112 | v112 |
| 3 | `PARENT` | `INTEGER` | NULL | v112 | v112 |
| 4 | `CODUSUARIO` | `INTEGER` | NOT NULL | v112 | v112 |
| 5 | `CODEMPRESA` | `INTEGER` | NULL | v112 | v112 |
| 6 | `FILTRO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL | v112 | v112 |
| 7 | `IMAGEINDEX` | `INTEGER` | NULL | v112 | v112 |
| 8 | `PATH` | `VARCHAR(255)` | NULL | v554 | v554 |
| 9 | `CODTIPO_IMPRESSAO` | `INTEGER` | NULL | v554 | v554 |
| 10 | `TIPO_IMPRESSAO` | `VARCHAR(100)` | NULL | v554 | v554 |
| 11 | `CODACABAMENTO` | `INTEGER` | NULL | v554 | v554 |
| 12 | `ACABAMENTO` | `VARCHAR(150)` | NULL | v554 | v554 |
| 13 | `LOCAL` | `VARCHAR(150)` | NULL | v554 | v554 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 112 | CREATE | CREATE TABLE com 7 colunas |
| 554 | ADD_COL | + PATH VARCHAR(255) |
| 554 | ADD_COL | + CODTIPO_IMPRESSAO INTEGER |
| 554 | ADD_COL | + TIPO_IMPRESSAO VARCHAR(100) |
| 554 | ADD_COL | + CODACABAMENTO INTEGER |
| 554 | ADD_COL | + ACABAMENTO VARCHAR(150) |
| 554 | ADD_COL | + LOCAL VARCHAR(150) |

