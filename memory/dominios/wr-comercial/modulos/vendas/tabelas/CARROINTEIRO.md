---
table: CARROINTEIRO
module: vendas
created_at_version: 15
last_modified_version: 758
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CARROINTEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 15;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v15 | v15 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v15 | v758 |
| 3 | `CODMARCA` | `INTEGER` | NULL |  | v15 | v15 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 15 | CREATE | CREATE TABLE com 4 colunas |
| 758 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(50) |
| 758 | DROP_COL | - CODTIPOFILME |

