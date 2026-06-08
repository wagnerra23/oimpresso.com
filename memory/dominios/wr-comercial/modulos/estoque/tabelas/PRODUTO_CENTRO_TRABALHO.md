---
table: PRODUTO_CENTRO_TRABALHO
module: estoque
created_at_version: 493
last_modified_version: 1000
target_version: 1468
columns_count: 7
foreign_keys_count: 3
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUTO: PRODUTO
  CODPRODUTO_CT_PRE_REQUISITO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_CENTRO_TRABALHO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 493;
- **Última mudança:** UPDATE 1000;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_CT_PRE_REQUISITO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v493 | v493 |
| 2 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | → `CENTRO_TRABALHO` | v493 | v493 |
| 3 | `TEMPO` | `DOUBLE PRECISION` | NULL |  | v493 | v493 |
| 4 | `CODIGO` | `INTEGER` | NULL |  | v1000 | v1000 |
| 5 | `DESCRICAO` | `varchar (150)` | NULL |  | v579 | v579 |
| 6 | `CODPRODUTO_CT_PRE_REQUISITO` | `integer` | NULL | → `PRODUTO` | v579 | v579 |
| 7 | `SEQUENCIA` | `INTEGER` | NULL |  | v580 | v580 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 493 | CREATE | CREATE TABLE com 4 colunas |
| 531 | ADD_COL | + PRE_REQUISITO_CENTRO_TRABALHO INTEGER |
| 579 | ADD_COL | + CODIGO integer |
| 579 | ADD_COL | + DESCRICAO varchar (150) |
| 579 | ADD_COL | + CODPRODUTO_CT_PRE_REQUISITO integer |
| 580 | ADD_COL | + SEQUENCIA INTEGER |
| 591 | DROP_COL | - PRE_REQUISITO_CENTRO_TRABALHO |
| 637 | ADD_COL | + CUSTO DOUBLE PRECISION |
| 639 | ADD_COL | + MARGEM DOUBLE PRECISION |
| 646 | ADD_COL | + CUSTO_EXTRA DOUBLE PRECISION |
| 646 | ADD_COL | + CUSTO_EXTRA_TOTAL DOUBLE PRECISION |
| 675 | RENAME_COL | × CUSTO → CUSTO_VENDA |
| 758 | DROP_COL | - VALOR |
| 758 | DROP_COL | - CUSTO_VENDA |
| 758 | DROP_COL | - MARGEM |
| 758 | DROP_COL | - CUSTO_EXTRA |
| 758 | DROP_COL | - CUSTO_EXTRA_TOTAL |
| 1000 | ADD_COL | + CODIGO INTEGER |

