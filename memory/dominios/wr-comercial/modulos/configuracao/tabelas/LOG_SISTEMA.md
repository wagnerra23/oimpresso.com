---
table: LOG_SISTEMA
module: configuracao
created_at_version: 503
last_modified_version: 503
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `LOG_SISTEMA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 503;
- **Última mudança:** UPDATE 503;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v503 | v503 |
| 2 | `TIPO` | `VARCHAR(50)` | NULL |  | v503 | v503 |
| 3 | `DATA` | `TIMESTAMP` | NULL |  | v503 | v503 |
| 4 | `MENSAGEM` | `VARCHAR(5000)` | NULL |  | v503 | v503 |
| 5 | `STATUS_EXECUCAO` | `VARCHAR(30)` | NULL |  | v503 | v503 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 503 | CREATE | CREATE TABLE com 5 colunas |

