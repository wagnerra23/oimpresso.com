---
table: REQUISICAO
module: estoque
created_at_version: 1387
last_modified_version: 1388
target_version: 1468
columns_count: 12
foreign_keys_count: 4
foreign_keys:
  CODNF_ENTRADA: NF_ENTRADA
  CODPRODUCAO: PRODUCAO
  CODVENDA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `REQUISICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1387;
- **Última mudança:** UPDATE 1388;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1387 | v1387 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1387 | v1387 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1387 | v1387 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1387 | v1387 |
| 5 | `DT_EMISSAO` | `TIMESTAMP` | NULL |  | v1387 | v1387 |
| 6 | `TOTAL_CUSTO` | `DOUBLE PRECISION` | NULL |  | v1387 | v1387 |
| 7 | `DT_FINALIZADO` | `TIMESTAMP` | NULL |  | v1387 | v1387 |
| 8 | `CODVENDA` | `VARCHAR(50)` | NULL | → `VENDA` | v1387 | v1387 |
| 9 | `CODPRODUCAO` | `INTEGER` | NULL | → `PRODUCAO` | v1387 | v1387 |
| 10 | `CODNF_ENTRADA` | `VARCHAR(50)` | NULL | → `NF_ENTRADA` | v1387 | v1387 |
| 11 | `SITUACAO` | `VARCHAR(50)` | NULL |  | v1387 | v1387 |
| 12 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1388 | v1388 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1387 | CREATE | CREATE TABLE com 11 colunas |
| 1388 | ADD_COL | + CODVENDA_PRODUTO INTEGER |

