---
table: ACABAMENTO
module: producao
created_at_version: 455
last_modified_version: 729
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `ACABAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 455;
- **Última mudança:** UPDATE 729;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v455 | v455 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v455 | v455 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v729 | v729 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v729 | v729 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 455 | CREATE | CREATE TABLE com 2 colunas |
| 729 | ADD_COL | + ATIVO VARCHAR(1) |
| 729 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

