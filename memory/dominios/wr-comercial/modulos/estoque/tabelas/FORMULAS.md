---
table: FORMULAS
module: estoque
created_at_version: 825
last_modified_version: 826
target_version: 1468
columns_count: 11
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FORMULAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 825;
- **Última mudança:** UPDATE 826;
- **Total colunas (versão 1468):** 11

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v825 | v825 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v825 | v825 |
| 3 | `TIPO` | `VARCHAR(20)` | NULL |  | v825 | v825 |
| 4 | `COMP_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 5 | `LARG_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 6 | `ESPESSURA_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 7 | `QTDADEPECA_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 8 | `FORMULA` | `VARCHAR(4000)` | NULL |  | v825 | v825 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v825 | v825 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v825 | v825 |
| 11 | `OBSERVACAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80, ADD IMAGEM BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v826 | v826 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 825 | CREATE | CREATE TABLE com 10 colunas |
| 826 | ADD_COL | + OBSERVACAO BLOB SUB_TYPE 1 SEGMENT SIZE 80, ADD IMAGEM BLOB SUB_TYPE 0 SEGMENT SIZE 80 |

