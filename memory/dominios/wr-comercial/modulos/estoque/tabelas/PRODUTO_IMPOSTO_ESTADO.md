---
table: PRODUTO_IMPOSTO_ESTADO
module: estoque
created_at_version: 86
last_modified_version: 86
target_version: 1468
columns_count: 4
foreign_keys_count: 1
foreign_keys:
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_IMPOSTO_ESTADO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 86;
- **Última mudança:** UPDATE 86;
- **Total colunas (versão 1468):** 4

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v86 | v86 |
| 2 | `ESTADO` | `VARCHAR(2)` | NOT NULL |  | v86 | v86 |
| 3 | `MVA` | `DOUBLE PRECISION` | NULL |  | v86 | v86 |
| 4 | `PAUTA_PRECO` | `DOUBLE PRECISION` | NULL |  | v86 | v86 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 86 | CREATE | CREATE TABLE com 4 colunas |

