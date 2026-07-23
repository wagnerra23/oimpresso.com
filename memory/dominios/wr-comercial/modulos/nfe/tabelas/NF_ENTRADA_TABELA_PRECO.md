---
id: dominios-wr-comercial-modulos-nfe-tabelas-nf-entrada-tabela-preco
table: NF_ENTRADA_TABELA_PRECO
module: nfe
created_at_version: 827
last_modified_version: 1371
target_version: 1468
columns_count: 11
foreign_keys_count: 5
foreign_keys:
  CODCLIENTE: PESSOAS
  CODNF_ENTRADA: NF_ENTRADA
  CODNF_ENTRADA_PRODUTOS: NF_ENTRADA_PRODUTOS
  CODPRODUTO: PRODUTO
  CODPRODUTO_TABELA: PRODUTO_TABELA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_TABELA_PRECO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 827;
- **Última mudança:** UPDATE 1371;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCLIENTE` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODNF_ENTRADA_PRODUTOS` | [`NF_ENTRADA_PRODUTOS`](../../nfe/tabelas/NF_ENTRADA_PRODUTOS.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_TABELA` | [`PRODUTO_TABELA`](../../estoque/tabelas/PRODUTO_TABELA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO_TABELA` | `INTEGER` | NOT NULL | → `PRODUTO_TABELA` | v827 | v827 |
| 2 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | → `NF_ENTRADA` | v827 | v827 |
| 3 | `CODNF_ENTRADA_PRODUTOS` | `INTEGER` | NOT NULL | → `NF_ENTRADA_PRODUTOS` | v827 | v827 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v827 | v827 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v827 | v827 |
| 6 | `PERC_DESCONTO` | `DOUBLE PRECISION` | NULL |  | v827 | v827 |
| 7 | `TEM_MARGEM_FIXA_CONTIBUICAO` | `VARCHAR(1)` | NULL |  | v827 | v827 |
| 8 | `PERC_ACRESCIMO` | `DOUBLE PRECISION` | NULL |  | v827 | v827 |
| 9 | `CODCLIENTE` | `VARCHAR(30)` | NULL | → `PESSOAS` | v827 | v827 |
| 10 | `PARENT` | `INTEGER` | NULL |  | v863 | v863 |
| 11 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1371 | v1371 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 827 | CREATE | CREATE TABLE com 9 colunas |
| 863 | ADD_COL | + PARENT INTEGER |
| 1371 | ADD_COL | + CODPRODUTO VARCHAR(15) |

