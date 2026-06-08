---
table: PESSOAS_TIPO
module: cadastros
created_at_version: 206
last_modified_version: 216
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_TIPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 206;
- **Última mudança:** UPDATE 216;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `CHAR(3)` | NOT NULL |  | v206 | v206 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v206 | v206 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v206 | v206 |
| 4 | `ATIVO` | `varchar(1)` | NULL |  | v216 | v216 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 206 | CREATE | CREATE TABLE com 3 colunas |
| 216 | ADD_COL | + ATIVO varchar(1) |

