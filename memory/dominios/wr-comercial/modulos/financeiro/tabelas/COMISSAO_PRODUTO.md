---
table: COMISSAO_PRODUTO
module: financeiro
created_at_version: 484
last_modified_version: 484
target_version: 1468
columns_count: 12
foreign_keys_count: 4
foreign_keys:
  CODCOMISSAO: COMISSAO
  CODPRODUTO: PRODUTO
  CODVENDA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMISSAO_PRODUTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 484;
- **Última mudança:** UPDATE 484;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCOMISSAO` | [`COMISSAO`](../../financeiro/tabelas/COMISSAO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODCOMISSAO` | `INTEGER` | NOT NULL | → `COMISSAO` | v484 | v484 |
| 2 | `CODIGO` | `INTEGER` | NOT NULL |  | v484 | v484 |
| 3 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v484 | v484 |
| 4 | `CODVENDA` | `VARCHAR(10)` | NULL | → `VENDA` | v484 | v484 |
| 5 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v484 | v484 |
| 6 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v484 | v484 |
| 7 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v484 | v484 |
| 8 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v484 | v484 |
| 9 | `GERA_COMISSAO` | `VARCHAR(1)` | NULL |  | v484 | v484 |
| 10 | `PERC` | `DOUBLE PRECISION` | NULL |  | v484 | v484 |
| 11 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v484 | v484 |
| 12 | `VALOR_COMISSAO` | `DOUBLE PRECISION` | NULL |  | v484 | v484 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 484 | CREATE | CREATE TABLE com 12 colunas |

