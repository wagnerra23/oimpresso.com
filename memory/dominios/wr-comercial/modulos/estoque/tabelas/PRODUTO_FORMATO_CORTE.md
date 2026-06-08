---
table: PRODUTO_FORMATO_CORTE
module: estoque
created_at_version: 341
last_modified_version: 343
target_version: 1468
columns_count: 7
foreign_keys_count: 1
foreign_keys:
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_FORMATO_CORTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 341;
- **Última mudança:** UPDATE 343;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `integer` | NOT NULL |  | v341 | v341 |
| 2 | `CODPRODUTO` | `varchar (15)` | NULL | → `PRODUTO` | v341 | v341 |
| 3 | `DESCRICAO` | `varchar (15)` | NULL |  | v341 | v341 |
| 4 | `MEDIDA1` | `double precision` | NULL |  | v341 | v341 |
| 5 | `MEDIDA2` | `double precision` | NULL |  | v341 | v341 |
| 6 | `QTD_POR_PAPEL` | `double precision` | NULL |  | v341 | v341 |
| 7 | `LAYOUT` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v343 | v343 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 341 | CREATE | CREATE TABLE com 6 colunas |
| 343 | ADD_COL | + LAYOUT BLOB SUB_TYPE 0 SEGMENT SIZE 80 |

