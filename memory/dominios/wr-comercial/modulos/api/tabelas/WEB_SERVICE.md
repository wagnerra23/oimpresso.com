---
table: WEB_SERVICE
module: api
created_at_version: 975
last_modified_version: 988
target_version: 1468
columns_count: 8
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WEB_SERVICE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `api` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 975;
- **Última mudança:** UPDATE 988;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v975 | v975 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v975 | v975 |
| 3 | `LOGIN` | `VARCHAR(500)` | NULL |  | v975 | v975 |
| 4 | `SENHA` | `VARCHAR(255)` | NULL |  | v975 | v975 |
| 5 | `WEB_SERVICE` | `VARCHAR(1000)` | NULL |  | v975 | v975 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v975 | v975 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v975 | v975 |
| 8 | `LINK` | `VARCHAR(1000)` | NULL |  | v988 | v988 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 975 | CREATE | CREATE TABLE com 5 colunas |
| 975 | ADD_COL | + ATIVO VARCHAR(1) |
| 975 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 975 | ADD_COL | + WEB_SERVICE VARCHAR(1000) |
| 988 | ADD_COL | + LINK VARCHAR(1000) |

