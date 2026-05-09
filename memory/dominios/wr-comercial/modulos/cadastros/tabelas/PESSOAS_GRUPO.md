---
table: PESSOAS_GRUPO
module: cadastros
created_at_version: 811
last_modified_version: 811
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_GRUPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 811;
- **Última mudança:** UPDATE 811;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v811 | v811 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v811 | v811 |
| 3 | `ATIVO` | `VARCHAR(10)` | NULL |  | v811 | v811 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v811 | v811 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 811 | CREATE | CREATE TABLE com 4 colunas |

