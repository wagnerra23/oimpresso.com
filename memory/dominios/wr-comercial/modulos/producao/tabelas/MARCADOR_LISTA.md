---
table: MARCADOR_LISTA
module: producao
created_at_version: 390
last_modified_version: 390
target_version: 1468
columns_count: 4
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MARCADOR_LISTA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 390;
- **Última mudança:** UPDATE 390;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `MODO_MARCA` | `VARCHAR(20)` | NULL | v390 | v390 |
| 2 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | v390 | v390 |
| 3 | `FORCAR_EXIBICAO` | `VARCHAR(1)` | NULL | v390 | v390 |
| 4 | `SQLDESPESAS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL | v390 | v390 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 390 | ADD_COL | + MODO_MARCA VARCHAR(20) |
| 390 | ADD_COL | + CODPLANOCONTAS VARCHAR(15) |
| 390 | ADD_COL | + FORCAR_EXIBICAO VARCHAR(1) |
| 390 | ADD_COL | + SQLDESPESAS BLOB SUB_TYPE 1 SEGMENT SIZE 80 |

