---
table: KPI_MENU
module: bi
created_at_version: 1243
last_modified_version: 1285
target_version: 1468
columns_count: 25
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `KPI_MENU`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1243;
- **Última mudança:** UPDATE 1285;
- **Total colunas (versão 1468):** 25

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1243 | v1243 |
| 2 | `TABELA` | `VARCHAR(50)` | NULL |  | v1243 | v1243 |
| 3 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1243 | v1243 |
| 4 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1243 | v1243 |
| 5 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1243 | v1243 |
| 6 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1243 | v1243 |
| 7 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v1243 | v1243 |
| 8 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v1243 | v1243 |
| 9 | `GRAFICO_TIPO` | `VARCHAR(20)` | NULL |  | v1243 | v1243 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1243 | v1243 |
| 11 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1243 | v1243 |
| 12 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1243 | v1243 |
| 13 | `TEM_PRINCIPAL` | `VARCHAR(1)` | NULL |  | v1243 | v1243 |
| 14 | `COR` | `INTEGER` | NULL |  | v1243 | v1243 |
| 15 | `COMPETENCIA` | `VARCHAR(7)` | NULL |  | v1243 | v1243 |
| 16 | `TEXT1` | `VARCHAR(255)` | NULL |  | v1243 | v1243 |
| 17 | `TEXT2` | `VARCHAR(255)` | NULL |  | v1243 | v1243 |
| 18 | `TEXT3` | `VARCHAR(255)` | NULL |  | v1243 | v1243 |
| 19 | `TEXT4` | `VARCHAR(255)` | NULL |  | v1243 | v1243 |
| 20 | `TAG_ESTILO` | `INTEGER` | NULL |  | v1243 | v1243 |
| 21 | `GROUPINDEX` | `INTEGER` | NULL |  | v1243 | v1243 |
| 22 | `INDEXINGROUP` | `INTEGER` | NULL |  | v1243 | v1243 |
| 23 | `TAG_KPI` | `INTEGER` | NULL |  | v1253 | v1253 |
| 24 | `NIVEL` | `INTEGER` | NULL |  | v1285 | v1285 |
| 25 | `PARENT` | `VARCHAR(40)` | NULL |  | v1285 | v1285 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1243 | CREATE | CREATE TABLE com 22 colunas |
| 1253 | ADD_COL | + TAG_KPI INTEGER |
| 1285 | ADD_COL | + NIVEL INTEGER |
| 1285 | ADD_COL | + PARENT VARCHAR(40) |

