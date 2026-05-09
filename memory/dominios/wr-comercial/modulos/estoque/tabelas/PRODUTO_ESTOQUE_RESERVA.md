---
table: PRODUTO_ESTOQUE_RESERVA
module: estoque
created_at_version: 973
last_modified_version: 973
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODVENDA: VENDA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_ESTOQUE_RESERVA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 973;
- **Última mudança:** UPDATE 973;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v973 | v973 |
| 2 | `CODVENDA` | `VARCHAR(10)` | NOT NULL | → `VENDA` | v973 | v973 |
| 3 | `RESERVADO` | `DOUBLE PRECISION` | NULL |  | v973 | v973 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 973 | CREATE | CREATE TABLE com 3 colunas |

