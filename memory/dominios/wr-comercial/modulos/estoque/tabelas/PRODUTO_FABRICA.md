---
table: PRODUTO_FABRICA
module: estoque
created_at_version: 10
last_modified_version: 239
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODFORNECEDOR: FORNECEDOR
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_FABRICA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 10;
- **Última mudança:** UPDATE 239;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFORNECEDOR` | [`FORNECEDOR`](../../cadastros/tabelas/FORNECEDOR.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODFABRICA` | `varchar(60)` | NOT NULL |  | v10 | v239 |
| 2 | `CODFORNECEDOR` | `VARCHAR(10)` | NOT NULL | → `FORNECEDOR` | v10 | v10 |
| 3 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v10 | v18 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 10 | CREATE | CREATE TABLE com 3 colunas |
| 18 | ALTER_TYPE | ~ CODPRODUTO TYPE VARCHAR(15) |
| 18 | ALTER_TYPE | ~ CODFABRICA TYPE VARCHAR(15) |
| 239 | ALTER_TYPE | ~ CODFABRICA TYPE varchar(60) |

