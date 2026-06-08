---
table: WR_BLOCO
module: wr_metadata
created_at_version: 1439
last_modified_version: 1439
target_version: 1468
columns_count: 7
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_BLOCO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1439;
- **Última mudança:** UPDATE 1439;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `codigo` | `INTEGER` | NOT NULL |  | v1439 | v1439 |
| 2 | `slug` | `VARCHAR(255)` | NOT NULL |  | v1439 | v1439 |
| 3 | `descricao` | `VARCHAR(100)` | NOT NULL |  | v1439 | v1439 |
| 4 | `observacao` | `VARCHAR(500)` | NULL |  | v1439 | v1439 |
| 5 | `ordem` | `INTEGER DEFAULT 0` | NULL |  | v1439 | v1439 |
| 6 | `ativo` | `VARCHAR(1) DEFAULT 'S'` | NOT NULL |  | v1439 | v1439 |
| 7 | `dt_alteracao` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | NULL |  | v1439 | v1439 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1439 | CREATE | CREATE TABLE com 7 colunas |

