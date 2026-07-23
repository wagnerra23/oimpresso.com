---
id: dominios-wr-comercial-modulos-vendas-tabelas-venda-produto-baixa-automatica
table: VENDA_PRODUTO_BAIXA_AUTOMATICA
module: vendas
created_at_version: 1231
last_modified_version: 1251
target_version: 1468
columns_count: 7
foreign_keys_count: 4
foreign_keys:
  CODVENDA: VENDA
  CODVENDA_ETAPA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
  CODVENDA_PRODUTO_COMPOSICAO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_PRODUTO_BAIXA_AUTOMATICA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1231;
- **Última mudança:** UPDATE 1251;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_ETAPA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |
| `CODVENDA_PRODUTO_COMPOSICAO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1231 | v1231 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1231 | v1231 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1231 | v1231 |
| 4 | `CODVENDA` | `VARCHAR(30)` | NULL | → `VENDA` | v1231 | v1251 |
| 5 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1231 | v1231 |
| 6 | `CODVENDA_ETAPA` | `INTEGER` | NULL | → `VENDA` | v1231 | v1231 |
| 7 | `CODVENDA_PRODUTO_COMPOSICAO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1231 | v1231 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1231 | CREATE | CREATE TABLE com 7 colunas |
| 1251 | ALTER_TYPE | ~ CODVENDA TYPE VARCHAR(30) |

