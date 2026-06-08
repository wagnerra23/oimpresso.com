---
table: DRE_CLASSIFICACAO
module: financeiro
created_at_version: 1007
last_modified_version: 1379
target_version: 1468
columns_count: 9
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DRE_CLASSIFICACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1007;
- **Última mudança:** UPDATE 1379;
- **Total colunas (versão 1468):** 9

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1007 | v1007 |
| 2 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v1007 | v1007 |
| 3 | `TIPO` | `VARCHAR(20)` | NULL |  | v1007 | v1007 |
| 4 | `SEQUENCIA` | `INTEGER` | NULL |  | v1007 | v1007 |
| 5 | `COR` | `INTEGER` | NULL |  | v1007 | v1007 |
| 6 | `COR_FONT` | `INTEGER` | NULL |  | v1007 | v1007 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1007 | v1007 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1007 | v1007 |
| 9 | `TIPO_DRE` | `VARCHAR(150)` | NULL |  | v1379 | v1379 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 495 | CREATE | CREATE TABLE com 4 colunas |
| 513 | ADD_COL | + COR INTEGER |
| 513 | ADD_COL | + COR_FONT INTEGER |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |
| 730 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1007 | CREATE | CREATE TABLE com 8 colunas |
| 1379 | ADD_COL | + TIPO_DRE VARCHAR(150) |

