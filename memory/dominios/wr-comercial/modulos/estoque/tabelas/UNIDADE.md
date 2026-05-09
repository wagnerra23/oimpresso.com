---
table: UNIDADE
module: estoque
created_at_version: 139
last_modified_version: 1250
target_version: 1468
columns_count: 23
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `UNIDADE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 139;
- **Última mudança:** UPDATE 1250;
- **Total colunas (versão 1468):** 23

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `UNIDADE` | `VARCHAR(6)` | NOT NULL |  | v139 | v139 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v139 | v139 |
| 3 | `EXIBIR_COMPRIMENTO` | `SMALLINT` | NULL |  | v139 | v139 |
| 4 | `EXIBIR_LARGURA` | `SMALLINT` | NULL |  | v139 | v139 |
| 5 | `EXIBIR_ESPESSURA` | `SMALLINT` | NULL |  | v139 | v139 |
| 6 | `CALC_COMPRIMENTO` | `smallint` | NULL |  | v162 | v162 |
| 7 | `CALC_LARGURA` | `smallint` | NULL |  | v162 | v162 |
| 8 | `CALC_ESPESSURA` | `smallint` | NULL |  | v162 | v162 |
| 9 | `GERA_LOTE` | `smallint` | NULL |  | v232 | v232 |
| 10 | `EXIBIR_QTDMETRICAUNITARIA` | `smallint` | NULL |  | v312 | v312 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 12 | `CODIGO` | `INTEGER` | NULL |  | v728 | v728 |
| 13 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 14 | `FORMULA` | `VARCHAR(20)` | NULL |  | v889 | v889 |
| 15 | `SUBUNIDADE_QTDADE` | `DOUBLE PRECISION, ADD SUBUNIDADE_COMP DOUBLE PRECISION, ADD SUBUNIDADE_LARG DOUBLE PRECISION, ADD SUBUNIDADE_ESPESSURA DOUBLE PRECISION, ADD SUBUNIDADE_QUANT DOUBLE PRECISION` | NULL |  | v1152 | v1152 |
| 16 | `TEM_DECIMAL` | `VARCHAR(1)` | NULL |  | v1152 | v1152 |
| 17 | `TEM_SUBUNIDADE` | `VARCHAR(1)` | NULL |  | v1152 | v1152 |
| 18 | `SUBUNIDADE` | `VARCHAR(6)` | NULL |  | v1152 | v1152 |
| 19 | `IS_PESO` | `VARCHAR(1)` | NULL |  | v1166 | v1166 |
| 20 | `OIMPRESSO_ATIVO` | `VARCHAR(1)` | NULL |  | v1250 | v1250 |
| 21 | `OIMPRESSO_CODIGO` | `VARCHAR(15)` | NULL |  | v1250 | v1250 |
| 22 | `OIMPRESSO_DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1250 | v1250 |
| 23 | `OIMPRESSO_UPDATED_AT` | `TIMESTAMP` | NULL |  | v1250 | v1250 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 139 | CREATE | CREATE TABLE com 5 colunas |
| 162 | ADD_COL | + CALC_COMPRIMENTO smallint |
| 162 | ADD_COL | + CALC_LARGURA smallint |
| 162 | ADD_COL | + CALC_ESPESSURA smallint |
| 232 | ADD_COL | + GERA_LOTE smallint |
| 312 | ADD_COL | + EXIBIR_QTDMETRICAUNITARIA smallint |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + CODIGO INTEGER |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 889 | ADD_COL | + FORMULA VARCHAR(20) |
| 1152 | ADD_COL | + SUBUNIDADE_QTDADE DOUBLE PRECISION, ADD SUBUNIDADE_COMP DOUBLE PRECISION, ADD SUBUNIDADE_LARG DOUBLE PRECISION, ADD SUBUNIDADE_ESPESSURA DOUBLE PRECISION, ADD SUBUNIDADE_QUANT DOUBLE PRECISION |
| 1152 | ADD_COL | + TEM_DECIMAL VARCHAR(1) |
| 1152 | ADD_COL | + TEM_SUBUNIDADE VARCHAR(1) |
| 1152 | ADD_COL | + SUBUNIDADE VARCHAR(6) |
| 1166 | ADD_COL | + IS_PESO VARCHAR(1) |
| 1250 | ADD_COL | + OIMPRESSO_ATIVO VARCHAR(1) |
| 1250 | ADD_COL | + OIMPRESSO_CODIGO VARCHAR(15) |
| 1250 | ADD_COL | + OIMPRESSO_DT_ALTERACAO TIMESTAMP |
| 1250 | ADD_COL | + OIMPRESSO_UPDATED_AT TIMESTAMP |

