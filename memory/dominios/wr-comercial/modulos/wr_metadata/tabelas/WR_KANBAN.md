---
table: WR_KANBAN
module: wr_metadata
created_at_version: 1284
last_modified_version: 1444
target_version: 1468
columns_count: 13
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_KANBAN`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1284;
- **Última mudança:** UPDATE 1444;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1284 | v1284 |
| 2 | `TAG_TELA` | `INTEGER` | NULL |  | v1284 | v1284 |
| 3 | `COLUNA` | `VARCHAR(255)` | NULL |  | v1284 | v1284 |
| 4 | `DESCRICAO` | `VARCHAR(255)` | NULL |  | v1284 | v1284 |
| 5 | `ORDEM` | `INTEGER` | NULL |  | v1284 | v1284 |
| 6 | `STYLE` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v1284 | v1284 |
| 7 | `COR` | `INTEGER` | NULL |  | v1284 | v1284 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1284 | v1284 |
| 9 | `DT_ALTERACAO` | `timestamp` | NULL |  | v1284 | v1284 |
| 10 | `COLUNA_FECHADA` | `VARCHAR(1)` | NULL |  | v1284 | v1284 |
| 11 | `CHAVE` | `VARCHAR(50)` | NULL |  | v1291 | v1291 |
| 12 | `TABELA_JOIN` | `VARCHAR(255)` | NULL |  | v1444 | v1444 |
| 13 | `PATH` | `VARCHAR(255)` | NULL |  | v1444 | v1444 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1284 | CREATE | CREATE TABLE com 10 colunas |
| 1291 | ADD_COL | + CHAVE VARCHAR(50) |
| 1444 | ADD_COL | + TABELA_JOIN VARCHAR(255) |
| 1444 | ADD_COL | + PATH VARCHAR(255) |

