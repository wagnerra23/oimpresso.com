---
table: VENDA_PRODUTO_FORNECEDOR
module: vendas
created_at_version: 376
last_modified_version: 376
target_version: 1468
columns_count: 4
foreign_keys_count: 3
foreign_keys:
  CODFORNECEDOR: FORNECEDOR
  CODVENDA: VENDA
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_PRODUTO_FORNECEDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 376;
- **Última mudança:** UPDATE 376;
- **Total colunas (versão 1468):** 4

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFORNECEDOR` | [`FORNECEDOR`](../../cadastros/tabelas/FORNECEDOR.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODFORNECEDOR` | `VARCHAR(10)` | NOT NULL | → `FORNECEDOR` | v376 | v376 |
| 2 | `CODVENDA` | `VARCHAR(10)` | NOT NULL | → `VENDA` | v376 | v376 |
| 3 | `CODVENDA_PRODUTO` | `INTEGER` | NOT NULL | → `VENDA_PRODUTO` | v376 | v376 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v376 | v376 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 376 | CREATE | CREATE TABLE com 4 colunas |

