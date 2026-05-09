---
table: AGENDA_LIDO
module: agenda
created_at_version: 417
last_modified_version: 417
target_version: 1468
columns_count: 3
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_LIDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 417;
- **Última mudança:** UPDATE 417;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODAGENDA` | `VARCHAR(40)` | NOT NULL | v417 | v417 |
| 2 | `CODUSUARIO` | `INTEGER` | NOT NULL | v417 | v417 |
| 3 | `DT_LIDO` | `TIMESTAMP` | NULL | v417 | v417 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 417 | CREATE | CREATE TABLE com 3 colunas |

