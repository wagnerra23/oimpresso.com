---
table: HISTORICO_CHAMADAS
module: wr_metadata
created_at_version: 354
last_modified_version: 354
target_version: 1468
columns_count: 7
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_CHAMADAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 354;
- **Última mudança:** UPDATE 354;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `RAMAL` | `INTEGER` | NOT NULL |  | v354 | v354 |
| 2 | `TIPO` | `SMALLINT` | NOT NULL |  | v354 | v354 |
| 3 | `TELEFONE` | `VARCHAR(20)` | NOT NULL |  | v354 | v354 |
| 4 | `DATAHORA` | `TIMESTAMP` | NOT NULL |  | v354 | v354 |
| 5 | `TEMPO` | `INTEGER` | NULL |  | v354 | v354 |
| 6 | `GRAVADA` | `VARCHAR(1)` | NULL |  | v354 | v354 |
| 7 | `DESVIADA` | `VARCHAR(1)` | NULL |  | v354 | v354 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 354 | CREATE | CREATE TABLE com 7 colunas |

