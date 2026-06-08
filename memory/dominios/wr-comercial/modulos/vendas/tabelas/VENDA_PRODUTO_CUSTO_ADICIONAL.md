---
table: VENDA_PRODUTO_CUSTO_ADICIONAL
module: vendas
created_at_version: 579
last_modified_version: 846
target_version: 1468
columns_count: 8
foreign_keys_count: 2
foreign_keys:
  CODVENDA_PRODUTO: VENDA_PRODUTO
  CODVENDA_PRODUTO_VENDA: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_PRODUTO_CUSTO_ADICIONAL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 846;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |
| `CODVENDA_PRODUTO_VENDA` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v579 | v579 |
| 2 | `CODVENDA_PRODUTO` | `INTEGER` | NOT NULL | → `VENDA_PRODUTO` | v579 | v579 |
| 3 | `CODVENDA_PRODUTO_VENDA` | `VARCHAR(10)` | NOT NULL | → `VENDA_PRODUTO` | v579 | v579 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v579 | v579 |
| 5 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v709 | v709 |
| 6 | `LANCADO_MANUALMENTE` | `DOM_BOOLEAN` | NULL |  | v709 | v709 |
| 7 | `PERCVALOR` | `varchar(10)` | NULL |  | v736 | v736 |
| 8 | `DESCRICAO` | `varchar(150)` | NULL |  | v736 | v736 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 6 colunas |
| 616 | ADD_COL | + COBRAR_DO_CLIENTE DOM_BOOLEAN |
| 617 | ADD_COL | + APLICAR_ANTES_MARGEM DOM_BOOLEAN |
| 636 | ADD_COL | + APLICAR_NA varchar(20) |
| 688 | ADD_COL | + CODPESSOA varchar(10) |
| 709 | ADD_COL | + OBSERVACAO VARCHAR(500) |
| 709 | ADD_COL | + LANCADO_MANUALMENTE DOM_BOOLEAN |
| 732 | ADD_COL | + PERCVALOR VARCHAR(10) |
| 732 | ADD_COL | + DESCRICAO VARCHAR(150) |
| 736 | RENAME_COL | × TIPO_CUSTO → PERCVALOR |
| 736 | ADD_COL | + PERCVALOR varchar(10) |
| 736 | ADD_COL | + DESCRICAO varchar(150) |
| 846 | DROP_COL | - CODCUSTO_ADICIONAL |
| 846 | DROP_COL | - TIPO_CUSTO |
| 846 | DROP_COL | - COBRAR_DO_CLIENTE |
| 846 | DROP_COL | - APLICAR_ANTES_MARGEM |
| 846 | DROP_COL | - APLICAR_NA |
| 846 | DROP_COL | - CODPESSOA |

