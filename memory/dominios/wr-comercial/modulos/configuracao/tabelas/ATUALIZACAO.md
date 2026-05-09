---
table: ATUALIZACAO
module: configuracao
created_at_version: 475
last_modified_version: 475
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `ATUALIZACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 475;
- **Última mudança:** UPDATE 475;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v475 | v475 |
| 2 | `ARQUIVO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v475 | v475 |
| 3 | `VERSAO` | `VARCHAR(20)` | NULL |  | v475 | v475 |
| 4 | `DT_DOWNLOAD` | `TIMESTAMP` | NULL |  | v475 | v475 |
| 5 | `VERSAO_OBRIGATORIA` | `VARCHAR(20)` | NULL |  | v475 | v475 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 475 | CREATE | CREATE TABLE com 5 colunas |
| 475 | ADD_COL | + VERSAO_OBRIGATORIA VARCHAR(20) |

