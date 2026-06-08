---
table: PRODUCAO_MOTIVO
module: producao
created_at_version: 1051
last_modified_version: 1051
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_MOTIVO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1051;
- **Última mudança:** UPDATE 1051;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1051 | v1051 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1051 | v1051 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1051 | v1051 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1051 | v1051 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1051 | CREATE | CREATE TABLE com 4 colunas |

