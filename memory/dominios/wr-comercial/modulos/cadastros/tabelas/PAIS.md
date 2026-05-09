---
table: PAIS
module: cadastros
created_at_version: 178
last_modified_version: 1024
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PAIS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 178;
- **Última mudança:** UPDATE 1024;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `varchar (4)` | NOT NULL |  | v178 | v178 |
| 2 | `DESCRICAO` | `varchar (100)` | NULL |  | v178 | v178 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1024 | v1024 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1024 | v1024 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 178 | CREATE | CREATE TABLE com 2 colunas |
| 1024 | ADD_COL | + ATIVO VARCHAR(1) |
| 1024 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

