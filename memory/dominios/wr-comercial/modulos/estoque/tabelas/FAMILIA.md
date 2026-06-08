---
table: FAMILIA
module: estoque
created_at_version: 752
last_modified_version: 752
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FAMILIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 752;
- **Última mudança:** UPDATE 752;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v752 | v752 |
| 2 | `DESCRICAO` | `VARCHAR(32)` | NULL |  | v752 | v752 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v752 | v752 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v752 | v752 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 752 | CREATE | CREATE TABLE com 4 colunas |

