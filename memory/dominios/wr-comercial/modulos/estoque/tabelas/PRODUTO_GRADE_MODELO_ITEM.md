---
table: PRODUTO_GRADE_MODELO_ITEM
module: estoque
created_at_version: 1314
last_modified_version: 1314
target_version: 1468
columns_count: 5
foreign_keys_count: 1
foreign_keys:
  CODPRODUTO_GRADE_MODELO: PRODUTO_GRADE_MODELO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_GRADE_MODELO_ITEM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1314;
- **Última mudança:** UPDATE 1314;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO_GRADE_MODELO` | [`PRODUTO_GRADE_MODELO`](../../estoque/tabelas/PRODUTO_GRADE_MODELO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1314 | v1314 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1314 | v1314 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1314 | v1314 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1314 | v1314 |
| 5 | `CODPRODUTO_GRADE_MODELO` | `INTEGER` | NULL | → `PRODUTO_GRADE_MODELO` | v1314 | v1314 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1314 | CREATE | CREATE TABLE com 5 colunas |

