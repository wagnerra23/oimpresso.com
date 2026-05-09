---
table: EQUIPAMENTO_ELETRODOMESTICO
module: equipamento
created_at_version: 44
last_modified_version: 758
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_ELETRODOMESTICO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 44;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(10)` | NOT NULL |  | v44 | v44 |
| 2 | `NUMERO_SERIE` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 3 | `NUMERO_NF` | `INTEGER` | NULL |  | v44 | v44 |
| 4 | `DT_COMPRA` | `TIMESTAMP` | NULL |  | v44 | v44 |
| 5 | `MODELO` | `VARCHAR(100)` | NULL |  | v44 | v44 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 44 | CREATE | CREATE TABLE com 6 colunas |
| 758 | DROP_COL | - DEFEITO |

