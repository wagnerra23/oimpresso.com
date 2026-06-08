---
table: NF_REGIME_ESPECIAL_TRIBUTACAO
module: nfe
created_at_version: 552
last_modified_version: 728
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_REGIME_ESPECIAL_TRIBUTACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 552;
- **Última mudança:** UPDATE 728;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v552 | v552 |
| 2 | `DESCRICAO` | `VARCHAR(60)` | NULL |  | v552 | v552 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 552 | CREATE | CREATE TABLE com 2 colunas |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

