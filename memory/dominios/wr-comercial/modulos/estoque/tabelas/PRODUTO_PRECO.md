---
table: PRODUTO_PRECO
module: estoque
created_at_version: 125
last_modified_version: 1184
target_version: 1468
columns_count: 10
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODPRODUTO_VINCULADO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_PRECO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 125;
- **Última mudança:** UPDATE 1184;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_VINCULADO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v125 | v125 |
| 2 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v125 | v125 |
| 3 | `QUANT` | `DOUBLE PRECISION` | NULL |  | v125 | v125 |
| 4 | `TIPO` | `VARCHAR(11)` | NULL |  | v125 | v513 |
| 5 | `PORCENTAGEM` | `DOUBLE PRECISION` | NULL |  | v125 | v125 |
| 6 | `DE` | `DOUBLE PRECISION` | NULL |  | v513 | v513 |
| 7 | `CODPRODUTO_VINCULADO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1184 | v1184 |
| 8 | `DESCRICAO` | `VARCHAR(200)` | NULL |  | v1184 | v1184 |
| 9 | `REFERENCIA` | `VARCHAR(100)` | NULL |  | v1184 | v1184 |
| 10 | `SKU` | `VARCHAR(50)` | NULL |  | v1184 | v1184 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 125 | CREATE | CREATE TABLE com 6 colunas |
| 291 | ADD_COL | + CUSTO_LOJA DOUBLE PRECISION |
| 291 | ADD_COL | + CUSTO_FABR DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_VARIAVEL DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_FINANCEIRO DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_FIXO DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_LUCRO_DESEJADO DOUBLE PRECISION |
| 504 | ADD_COL | + MARKUP DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_VARIAVEL DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_FINANCEIRO DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_FIXO DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_LUCRO_DESEJADO DOUBLE PRECISION |
| 504 | ADD_COL | + MARKUP DOUBLE PRECISION |
| 504 | ADD_COL | + PERC_CUSTO_COMISSAO DOUBLE PRECISION |
| 513 | ALTER_TYPE | ~ TIPO TYPE VARCHAR(11) |
| 513 | ADD_COL | + DE DOUBLE PRECISION |
| 587 | ADD_COL | + FLUXO_CT_PERSONALIZADO DOM_BOOLEAN |
| 591 | DROP_COL | - PERC_CUSTO_FIXO |
| 591 | DROP_COL | - PERC_CUSTO_FINANCEIRO |
| 591 | DROP_COL | - PERC_CUSTO_VARIAVEL |
| 610 | ADD_COL | + CUSTO_COMPOSICAO double precision |
| 613 | ADD_COL | + CUSTO_DETALHADO DOM_BOOLEAN |
| 631 | ADD_COL | + VALOR_COMPOSICAO double precision |
| 631 | ADD_COL | + CUSTO_CENTRO_TRABALHO double precision |
| 721 | RENAME_COL | × CUSTO_LOJA → CUSTO_VENDA_TOTAL |
| 758 | DROP_COL | - VALOR |
| 758 | DROP_COL | - CUSTO_VENDA_TOTAL |
| 758 | DROP_COL | - CUSTO_FABR |
| 758 | DROP_COL | - PERC_LUCRO_DESEJADO |
| 758 | DROP_COL | - MARKUP |
| 758 | DROP_COL | - PERC_CUSTO_COMISSAO |
| 758 | DROP_COL | - FLUXO_CT_PERSONALIZADO |
| 758 | DROP_COL | - CUSTO_COMPOSICAO |
| 758 | DROP_COL | - CUSTO_DETALHADO |
| 758 | DROP_COL | - VALOR_COMPOSICAO |
| 758 | DROP_COL | - CUSTO_CENTRO_TRABALHO |
| 1184 | ADD_COL | + CODPRODUTO_VINCULADO VARCHAR(15) |
| 1184 | ADD_COL | + DESCRICAO VARCHAR(200) |
| 1184 | ADD_COL | + REFERENCIA VARCHAR(100) |
| 1184 | ADD_COL | + SKU VARCHAR(50) |

