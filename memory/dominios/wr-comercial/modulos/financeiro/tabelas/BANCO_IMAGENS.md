---
table: BANCO_IMAGENS
module: financeiro
created_at_version: 56
last_modified_version: 56
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BANCO_IMAGENS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 56;
- **Última mudança:** UPDATE 56;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v56 | v56 |
| 2 | `CAMINHO` | `VARCHAR(500)` | NULL |  | v56 | v56 |
| 3 | `TAGS` | `VARCHAR(200)` | NULL |  | v56 | v56 |
| 4 | `CODCATEGORIA` | `INTEGER` | NULL |  | v56 | v56 |
| 5 | `OBSERVACAO` | `VARCHAR(200)` | NULL |  | v56 | v56 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 56 | CREATE | CREATE TABLE com 5 colunas |

