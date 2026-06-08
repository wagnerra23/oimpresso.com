---
table: PRODUTO_TABELA_PRECO
module: estoque
created_at_version: 809
last_modified_version: 811
target_version: 1468
columns_count: 7
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODPRODUTO_TABELA: PRODUTO_TABELA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_TABELA_PRECO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 809;
- **Última mudança:** UPDATE 811;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_TABELA` | [`PRODUTO_TABELA`](../../estoque/tabelas/PRODUTO_TABELA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TEM_MARGEM_FIXA_CONTIBUICAO` | `VARCHAR(1)` | NULL |  | v809 | v809 |
| 2 | `CODPRODUTO_TABELA` | `INTEGER` | NOT NULL | → `PRODUTO_TABELA` | v809 | v809 |
| 3 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v809 | v809 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v809 | v809 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v809 | v809 |
| 6 | `PERC_DESCONTO` | `DOUBLE PRECISION` | NULL |  | v809 | v809 |
| 7 | `PERC_ACRESCIMO` | `DOUBLE PRECISION` | NULL |  | v811 | v811 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 777 | ADD_COL | + TEM_MARGEM_FIXA_CONTIBUICAO VARCHAR(1) |
| 809 | CREATE | CREATE TABLE com 6 colunas |
| 811 | ADD_COL | + PERC_ACRESCIMO DOUBLE PRECISION |

