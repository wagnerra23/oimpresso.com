---
table: COMISSAO_META
module: financeiro
created_at_version: 564
last_modified_version: 564
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMISSAO_META`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 564;
- **Última mudança:** UPDATE 564;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v564 | v564 |
| 2 | `TIPO` | `VARCHAR(11) CHARACTER SET NONE` | NULL |  | v564 | v564 |
| 3 | `PORCENTAGEM` | `DOUBLE PRECISION` | NULL |  | v564 | v564 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v564 | v564 |
| 5 | `DE` | `DOUBLE PRECISION` | NULL |  | v564 | v564 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 564 | CREATE | CREATE TABLE com 5 colunas |

