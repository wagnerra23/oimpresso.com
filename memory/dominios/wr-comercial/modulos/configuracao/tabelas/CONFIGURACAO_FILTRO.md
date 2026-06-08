---
table: CONFIGURACAO_FILTRO
module: configuracao
created_at_version: 812
last_modified_version: 925
target_version: 1468
columns_count: 19
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_FILTRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 812;
- **Última mudança:** UPDATE 925;
- **Total colunas (versão 1468):** 19

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v812 | v812 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v812 | v812 |
| 3 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v812 | v812 |
| 4 | `FORM` | `VARCHAR(500)` | NULL |  | v812 | v812 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v812 | v812 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v812 | v812 |
| 7 | `ORDEM` | `DOUBLE PRECISION` | NULL |  | v812 | v812 |
| 8 | `PODE_APARECER_PARA_TODOS` | `VARCHAR(1)` | NULL |  | v812 | v812 |
| 9 | `SQLWHERE` | `VARCHAR(500)` | NULL |  | v813 | v813 |
| 10 | `TEM_AUTOCHECK` | `VARCHAR(1), ADD TEM_RADIOITEM VARCHAR(1), ADD GROUPINDEX INTEGER` | NULL |  | v814 | v814 |
| 11 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v814 | v814 |
| 12 | `TEM_PERIODO` | `VARCHAR(1)` | NULL |  | v916 | v916 |
| 13 | `TEM_QUANT_REGISTROS` | `VARCHAR(1)` | NULL |  | v916 | v916 |
| 14 | `NAME` | `VARCHAR(100)` | NULL |  | v916 | v916 |
| 15 | `SQL` | `VARCHAR(500)` | NULL |  | v916 | v916 |
| 16 | `CAMPO` | `VARCHAR(100)` | NULL |  | v916 | v916 |
| 17 | `FORMATO` | `VARCHAR(50)` | NULL |  | v916 | v916 |
| 18 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v916 | v916 |
| 19 | `QUANT_REGISTROS` | `INTEGER, ADD PERIODO VARCHAR(20)` | NULL |  | v925 | v925 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 812 | CREATE | CREATE TABLE com 6 colunas |
| 812 | ADD_COL | + ORDEM DOUBLE PRECISION |
| 812 | ADD_COL | + PODE_APARECER_PARA_TODOS VARCHAR(1) |
| 813 | ADD_COL | + SQLWHERE VARCHAR(500) |
| 814 | ADD_COL | + TEM_AUTOCHECK VARCHAR(1), ADD TEM_RADIOITEM VARCHAR(1), ADD GROUPINDEX INTEGER |
| 814 | ADD_COL | + TEM_PADRAO VARCHAR(1) |
| 916 | ADD_COL | + TEM_PERIODO VARCHAR(1) |
| 916 | ADD_COL | + TEM_QUANT_REGISTROS VARCHAR(1) |
| 916 | ADD_COL | + NAME VARCHAR(100) |
| 916 | ADD_COL | + SQL VARCHAR(500) |
| 916 | ADD_COL | + CAMPO VARCHAR(100) |
| 916 | ADD_COL | + FORMATO VARCHAR(50) |
| 916 | ADD_COL | + GRAFICO_PERIODO VARCHAR(10) |
| 925 | ADD_COL | + QUANT_REGISTROS INTEGER, ADD PERIODO VARCHAR(20) |

