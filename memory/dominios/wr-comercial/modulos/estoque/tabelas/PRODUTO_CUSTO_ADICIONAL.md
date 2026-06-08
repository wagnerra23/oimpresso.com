---
table: PRODUTO_CUSTO_ADICIONAL
module: estoque
created_at_version: 579
last_modified_version: 867
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

# `PRODUTO_CUSTO_ADICIONAL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 867;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v579 | v579 |
| 2 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v579 | v579 |
| 3 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v579 | v579 |
| 4 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v709 | v709 |
| 5 | `LANCADO_MANUALMENTE` | `DOM_BOOLEAN` | NULL |  | v709 | v709 |
| 6 | `DESCRICAO` | `varchar(50)` | NULL |  | v736 | v736 |
| 7 | `PERCVALOR` | `VARCHAR(10)` | NULL |  | v579 | v736 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 5 colunas |
| 616 | ADD_COL | + COBRAR_DO_CLIENTE DOM_BOOLEAN |
| 617 | ADD_COL | + APLICAR_ANTES_MARGEM DOM_BOOLEAN |
| 633 | ADD_COL | + CONTEXTO_CUSTO varchar(20) |
| 637 | ADD_COL | + APLICAR_NA varchar(20) |
| 649 | ADD_COL | + MARGEM DOUBLE PRECISION |
| 649 | ADD_COL | + VALOR_CUSTO DOUBLE PRECISION |
| 688 | ADD_COL | + CODPESSOA varchar(10) |
| 709 | ADD_COL | + OBSERVACAO VARCHAR(500) |
| 709 | ADD_COL | + LANCADO_MANUALMENTE DOM_BOOLEAN |
| 728 | ADD_COL | + VALOR_ANTERIOR DOUBLE PRECISION |
| 732 | ADD_COL | + DESCRICAO VARCHAR(50) |
| 732 | ADD_COL | + PERCVALOR VARCHAR(10) |
| 736 | ADD_COL | + DESCRICAO varchar(50) |
| 736 | RENAME_COL | × CONTEXTO_CUSTO → CLASSIFICACAO |
| 736 | RENAME_COL | × TIPO_CUSTO → PERCVALOR |
| 854 | DROP_COL | - APLICAR_ANTES_MARGEM |
| 854 | DROP_COL | - COBRAR_DO_CLIENTE |
| 854 | DROP_COL | - VALOR_CUSTO |
| 854 | DROP_COL | - VALOR_ANTERIOR |
| 854 | DROP_COL | - MARGEM |
| 854 | DROP_COL | - CODCUSTO_ADICIONAL |
| 854 | DROP_COL | - CODPESSOA |
| 854 | DROP_COL | - TIPO_CUSTO |
| 854 | DROP_COL | - CONTEXTO_CUSTO |
| 854 | DROP_COL | - APLICAR_NA |
| 867 | DROP_COL | - CLASSIFICACAO |

