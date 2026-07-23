---
id: dominios-wr-comercial-modulos-vendas-tabelas-venda-produto-etapa
table: VENDA_PRODUTO_ETAPA
module: vendas
created_at_version: 1231
last_modified_version: 1251
target_version: 1468
columns_count: 15
foreign_keys_count: 6
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUTO: PRODUTO
  CODPRODUTO_COMPOSICAO: PRODUTO_COMPOSICAO
  CODPRODUTO_ETAPA: PRODUTO_ETAPA
  CODVENDA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_PRODUTO_ETAPA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1231;
- **Última mudança:** UPDATE 1251;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_COMPOSICAO` | [`PRODUTO_COMPOSICAO`](../../estoque/tabelas/PRODUTO_COMPOSICAO.md) |
| `CODPRODUTO_ETAPA` | [`PRODUTO_ETAPA`](../../estoque/tabelas/PRODUTO_ETAPA.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1231 | v1231 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1231 | v1231 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1231 | v1231 |
| 4 | `CODVENDA` | `VARCHAR(30)` | NULL | → `VENDA` | v1231 | v1251 |
| 5 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1231 | v1231 |
| 6 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v1231 | v1231 |
| 7 | `TEMPO_HORAS` | `DOUBLE PRECISION` | NULL |  | v1231 | v1231 |
| 8 | `ORDEM` | `DOUBLE PRECISION` | NULL |  | v1231 | v1231 |
| 9 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v1231 | v1231 |
| 10 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1233 | v1233 |
| 11 | `CODPRODUTO_COMPOSICAO` | `INTEGER` | NULL | → `PRODUTO_COMPOSICAO` | v1233 | v1233 |
| 12 | `CODETAPA_ORIGINAL` | `INTEGER` | NULL |  | v1233 | v1233 |
| 13 | `CODPRODUTO_ETAPA` | `INTEGER` | NULL | → `PRODUTO_ETAPA` | v1233 | v1233 |
| 14 | `TEMPO_STRING` | `VARCHAR(150)` | NULL |  | v1233 | v1233 |
| 15 | `TEMPO_MINUTOS` | `INTEGER` | NULL |  | v1233 | v1233 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1231 | CREATE | CREATE TABLE com 9 colunas |
| 1233 | ADD_COL | + CODPRODUTO VARCHAR(15) |
| 1233 | ADD_COL | + CODPRODUTO_COMPOSICAO INTEGER |
| 1233 | ADD_COL | + CODETAPA_ORIGINAL INTEGER |
| 1233 | ADD_COL | + CODPRODUTO_ETAPA INTEGER |
| 1233 | ADD_COL | + TEMPO_STRING VARCHAR(150) |
| 1233 | ADD_COL | + TEMPO_MINUTOS INTEGER |
| 1251 | ALTER_TYPE | ~ CODVENDA TYPE VARCHAR(30) |

