---
id: dominios-wr-comercial-modulos-producao-tabelas-producao-movimento
table: PRODUCAO_MOVIMENTO
module: producao
created_at_version: 1074
last_modified_version: 1149
target_version: 1468
columns_count: 18
foreign_keys_count: 9
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUCAO: PRODUCAO
  CODPRODUCAO_PRODUTO: PRODUCAO_PRODUTO
  CODPRODUTO: PRODUTO
  CODPRODUTO_MOVIMENTO: PRODUTO_MOVIMENTO
  CODUSUARIO: USUARIO
  CODUSUARIO_RESPONSAVEL: USUARIO
  CODVENDA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_MOVIMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1074;
- **Última mudança:** UPDATE 1149;
- **Total colunas (versão 1468):** 18

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODPRODUCAO_PRODUTO` | [`PRODUCAO_PRODUTO`](../../producao/tabelas/PRODUCAO_PRODUTO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_MOVIMENTO` | [`PRODUTO_MOVIMENTO`](../../estoque/tabelas/PRODUTO_MOVIMENTO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_RESPONSAVEL` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1074 | v1074 |
| 2 | `CODPRODUCAO` | `INTEGER` | NULL | → `PRODUCAO` | v1074 | v1074 |
| 3 | `CODPRODUCAO_PRODUTO` | `INTEGER` | NULL | → `PRODUCAO_PRODUTO` | v1074 | v1074 |
| 4 | `CODPRODUTO_MOVIMENTO` | `INTEGER` | NULL | → `PRODUTO_MOVIMENTO` | v1074 | v1074 |
| 5 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1074 | v1074 |
| 6 | `CODVENDA` | `VARCHAR(10)` | NULL | → `VENDA` | v1074 | v1074 |
| 7 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1074 | v1074 |
| 8 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1074 | v1074 |
| 9 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v1074 | v1074 |
| 10 | `SITUACAO` | `VARCHAR(100)` | NULL |  | v1074 | v1074 |
| 11 | `PRODUCAO_ESTAGIO` | `VARCHAR(100)` | NULL |  | v1074 | v1074 |
| 12 | `PRODUCAO_MOTIVO` | `VARCHAR(100)` | NULL |  | v1074 | v1074 |
| 13 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1074 | v1074 |
| 14 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1074 | v1074 |
| 15 | `CODUSUARIO_RESPONSAVEL` | `INTEGER` | NULL | → `USUARIO` | v1074 | v1074 |
| 16 | `TIPO_USO` | `VARCHAR(20)` | NULL |  | v1075 | v1075 |
| 17 | `QUANT` | `FLOAT` | NULL |  | v1075 | v1075 |
| 18 | `PESSOA_FUNCIONARIO_CODIGO` | `VARCHAR(10)` | NULL |  | v1149 | v1149 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1074 | CREATE | CREATE TABLE com 15 colunas |
| 1075 | ADD_COL | + TIPO_USO VARCHAR(20) |
| 1075 | ADD_COL | + QUANT FLOAT |
| 1149 | ADD_COL | + PESSOA_FUNCIONARIO_CODIGO VARCHAR(10) |

