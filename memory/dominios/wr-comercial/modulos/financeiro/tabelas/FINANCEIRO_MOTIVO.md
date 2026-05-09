---
table: FINANCEIRO_MOTIVO
module: financeiro
created_at_version: 1304
last_modified_version: 1304
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_MOTIVO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1304;
- **Última mudança:** UPDATE 1304;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1304 | v1304 |
| 2 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1304 | v1304 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1304 | v1304 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1304 | v1304 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1294 | CREATE | CREATE TABLE com 4 colunas |
| 1304 | CREATE | CREATE TABLE com 4 colunas |

