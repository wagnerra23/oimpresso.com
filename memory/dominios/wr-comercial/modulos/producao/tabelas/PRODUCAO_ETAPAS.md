---
id: dominios-wr-comercial-modulos-producao-tabelas-producao-etapas
table: PRODUCAO_ETAPAS
module: producao
created_at_version: 1053
last_modified_version: 1236
target_version: 1468
columns_count: 16
foreign_keys_count: 7
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUCAO: PRODUCAO
  CODPRODUTO: PRODUTO
  CODUSUARIO: USUARIO
  CODUSUARIO_RESPONSAVEL: USUARIO
  CODVENDA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_ETAPAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1053;
- **Última mudança:** UPDATE 1236;
- **Total colunas (versão 1468):** 16

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_RESPONSAVEL` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1053 | v1053 |
| 2 | `CODPRODUCAO` | `INTEGER` | NULL | → `PRODUCAO` | v1053 | v1053 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1053 | v1053 |
| 4 | `CODVENDA` | `VARCHAR(10)` | NULL | → `VENDA` | v1053 | v1053 |
| 5 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1053 | v1053 |
| 6 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1053 | v1053 |
| 7 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v1053 | v1053 |
| 8 | `SITUACAO` | `VARCHAR(100)` | NULL |  | v1053 | v1053 |
| 9 | `ESTAGIO` | `VARCHAR(100)` | NULL |  | v1053 | v1053 |
| 10 | `MOTIVO` | `VARCHAR(100)` | NULL |  | v1053 | v1053 |
| 11 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1053 | v1053 |
| 12 | `DATA` | `TIMESTAMP` | NULL |  | v1054 | v1054 |
| 13 | `CODUSUARIO_RESPONSAVEL` | `INTEGER` | NULL | → `USUARIO` | v1056 | v1056 |
| 14 | `PROTOCOLO` | `VARCHAR(50)` | NULL |  | v1092 | v1092 |
| 15 | `CODETAPA` | `INTEGER` | NULL |  | v1236 | v1236 |
| 16 | `DESCRICAO_ETAPA` | `VARCHAR(150)` | NULL |  | v1236 | v1236 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1053 | CREATE | CREATE TABLE com 13 colunas |
| 1054 | ADD_COL | + DATA TIMESTAMP |
| 1056 | DROP_COL | - CENTRO_TRABALHO |
| 1056 | DROP_COL | - USUARIO |
| 1056 | ADD_COL | + CODUSUARIO_RESPONSAVEL INTEGER |
| 1092 | ADD_COL | + PROTOCOLO VARCHAR(50) |
| 1236 | ADD_COL | + CODETAPA INTEGER |
| 1236 | ADD_COL | + DESCRICAO_ETAPA VARCHAR(150) |

