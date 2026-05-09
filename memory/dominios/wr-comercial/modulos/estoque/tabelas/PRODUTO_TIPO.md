---
table: PRODUTO_TIPO
module: estoque
created_at_version: 14
last_modified_version: 782
target_version: 1468
columns_count: 10
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_TIPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 14;
- **Última mudança:** UPDATE 782;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TIPO_COMPOSICAO` | `INTEGER` | NULL |  | v72 | v72 |
| 2 | `NIVEL_COMPOSICAO` | `INTEGER` | NULL |  | v72 | v72 |
| 3 | `PRODUZIDO` | `SMALLINT` | NULL |  | v283 | v283 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v375 | v375 |
| 5 | `BLOQUEIA_ESTOQUE_INSUFICIENTE` | `VARCHAR(1)` | NULL |  | v466 | v466 |
| 6 | `CLASSIFICACAO` | `VARCHAR(50)` | NULL |  | v718 | v718 |
| 7 | `PODE_SER_VENDIDO` | `DOM_BOOLEAN` | NULL |  | v717 | v717 |
| 8 | `PODE_SER_COMPRADO` | `DOM_BOOLEAN` | NULL |  | v717 | v717 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 10 | `PODE_ALTERAR_ESTOQUE` | `VARCHAR(1)` | NULL |  | v249 | v782 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 14 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(15) |
| 23 | ADD_COL | + TIPO_COMPOSICAO INTEGER |
| 23 | ADD_COL | + NIVEL_COMPOSICAO INTEGER |
| 72 | ADD_COL | + NIVEL_COMPOSICAO INTEGER |
| 72 | ADD_COL | + TIPO_COMPOSICAO INTEGER |
| 249 | ADD_COL | + ALTERA_ESTOQUE VARCHAR(1) |
| 283 | ADD_COL | + PRODUZIDO SMALLINT |
| 375 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 466 | ADD_COL | + BLOQUEIA_ESTOQUE_INSUFICIENTE VARCHAR(1) |
| 717 | ADD_COL | + CLASSIFICACAO VARCHAR(50) |
| 717 | ADD_COL | + PODE_SER_VENDIDO DOM_BOOLEAN |
| 717 | ADD_COL | + PODE_SER_COMPRADO DOM_BOOLEAN |
| 718 | ADD_COL | + CLASSIFICACAO VARCHAR(50) |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 782 | RENAME_COL | × ALTERA_ESTOQUE → PODE_ALTERAR_ESTOQUE |

