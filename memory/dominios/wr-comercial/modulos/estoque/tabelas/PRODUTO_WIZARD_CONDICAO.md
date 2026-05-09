---
table: PRODUTO_WIZARD_CONDICAO
module: estoque
created_at_version: 381
last_modified_version: 381
target_version: 1468
columns_count: 3
foreign_keys_count: 3
foreign_keys:
  CODPRODUTO_MATERIA_PRIMA: PRODUTO
  CODPRODUTO_WIZARD: PRODUTO_WIZARD
  CODPRODUTO_WIZARD_PARENT: PRODUTO_WIZARD
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_WIZARD_CONDICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 381;
- **Última mudança:** UPDATE 381;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO_MATERIA_PRIMA` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_WIZARD` | [`PRODUTO_WIZARD`](../../estoque/tabelas/PRODUTO_WIZARD.md) |
| `CODPRODUTO_WIZARD_PARENT` | [`PRODUTO_WIZARD`](../../estoque/tabelas/PRODUTO_WIZARD.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO_WIZARD` | `INTEGER` | NOT NULL | → `PRODUTO_WIZARD` | v381 | v381 |
| 2 | `CODPRODUTO_WIZARD_PARENT` | `INTEGER` | NOT NULL | → `PRODUTO_WIZARD` | v381 | v381 |
| 3 | `CODPRODUTO_MATERIA_PRIMA` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v381 | v381 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 381 | CREATE | CREATE TABLE com 3 colunas |

