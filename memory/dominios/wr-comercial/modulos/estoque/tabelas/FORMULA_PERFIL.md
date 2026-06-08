---
table: FORMULA_PERFIL
module: estoque
created_at_version: 653
last_modified_version: 723
target_version: 1468
columns_count: 10
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FORMULA_PERFIL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 653;
- **Última mudança:** UPDATE 723;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v653 | v653 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v653 | v653 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v653 | v653 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v653 | v653 |
| 5 | `FORMULA` | `VARCHAR(500)` | NULL |  | v721 | v721 |
| 6 | `PADRAO` | `DOM_BOOLEAN` | NULL |  | v721 | v723 |
| 7 | `COMP_FORMULA` | `VARCHAR(500)` | NULL |  | v721 | v721 |
| 8 | `LARG_FORMULA` | `VARCHAR(500)` | NULL |  | v721 | v721 |
| 9 | `ESPESSURA_FORMULA` | `VARCHAR(500)` | NULL |  | v721 | v721 |
| 10 | `QTDADEPECA_FORMULA` | `VARCHAR(500)` | NULL |  | v721 | v721 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 653 | CREATE | CREATE TABLE com 4 colunas |
| 721 | ADD_COL | + FORMULA VARCHAR(500) |
| 721 | ADD_COL | + PADRAO VARCHAR(1) |
| 721 | ADD_COL | + COMP_FORMULA VARCHAR(500) |
| 721 | ADD_COL | + LARG_FORMULA VARCHAR(500) |
| 721 | ADD_COL | + ESPESSURA_FORMULA VARCHAR(500) |
| 721 | ADD_COL | + QTDADEPECA_FORMULA VARCHAR(500) |
| 723 | ALTER_TYPE | ~ PADRAO TYPE DOM_BOOLEAN |

