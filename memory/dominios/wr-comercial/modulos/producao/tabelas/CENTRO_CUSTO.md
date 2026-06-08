---
table: CENTRO_CUSTO
module: producao
created_at_version: 579
last_modified_version: 1348
target_version: 1468
columns_count: 13
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CENTRO_CUSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 1348;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v579 | v579 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v579 | v579 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v579 | v579 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1348 | v1348 |
| 5 | `CODTABELA` | `VARCHAR(50)` | NULL |  | v1322 | v1322 |
| 6 | `ADD` | `DT_ALTERACAO TIMESTAMP` | NULL |  | v1348 | v1348 |
| 7 | `NIVEL1` | `INTEGER` | NULL |  | v1348 | v1348 |
| 8 | `NIVEL2` | `INTEGER` | NULL |  | v1348 | v1348 |
| 9 | `NIVEL3` | `INTEGER` | NULL |  | v1348 | v1348 |
| 10 | `NIVEL4` | `INTEGER` | NULL |  | v1348 | v1348 |
| 11 | `NIVEL5` | `INTEGER` | NULL |  | v1348 | v1348 |
| 12 | `NIVEL6` | `INTEGER` | NULL |  | v1348 | v1348 |
| 13 | `TABELA` | `INTEGER` | NULL |  | v1348 | v1348 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 4 colunas |
| 1322 | ADD_COL | + CODTABELA_PAI VARCHAR(50) |
| 1322 | ADD_COL | + CODTABELA VARCHAR(50) |
| 1322 | ADD_COL | + DESCRICAO_PAI VARCHAR(150) |
| 1348 | ADD_COL | + CODCENTRO_CUSTO_PAI INTEGER |
| 1348 | ADD_COL | + ATIVO VARCHAR(1) |
| 1348 | ADD_COL | + ADD DT_ALTERACAO TIMESTAMP |
| 1348 | ADD_COL | + NIVEL1 INTEGER |
| 1348 | ADD_COL | + NIVEL2 INTEGER |
| 1348 | ADD_COL | + NIVEL3 INTEGER |
| 1348 | ADD_COL | + NIVEL4 INTEGER |
| 1348 | ADD_COL | + NIVEL5 INTEGER |
| 1348 | ADD_COL | + NIVEL6 INTEGER |
| 1348 | DROP_COL | - CODTABELA_PAI |
| 1348 | DROP_COL | - DESCRICAO_PAI |
| 1348 | DROP_COL | - CODCENTRO_CUSTO_PAI |
| 1348 | DROP_COL | - PARENT |
| 1348 | DROP_COL | - CODSETOR |
| 1348 | DROP_COL | - CODPESSOA |
| 1348 | DROP_COL | - CODPRODUTO |
| 1348 | ADD_COL | + TABELA INTEGER |

