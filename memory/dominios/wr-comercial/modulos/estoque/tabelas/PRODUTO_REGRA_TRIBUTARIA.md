---
table: PRODUTO_REGRA_TRIBUTARIA
module: estoque
created_at_version: 1451
last_modified_version: 1451
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODREGRA_TRIBUTARIA: REGRA_TRIBUTARIA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_REGRA_TRIBUTARIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1451;
- **Última mudança:** UPDATE 1451;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODREGRA_TRIBUTARIA` | [`REGRA_TRIBUTARIA`](../../tributario/tabelas/REGRA_TRIBUTARIA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1451 | v1451 |
| 2 | `CODPRODUTO` | `INTEGER` | NOT NULL | → `PRODUTO` | v1451 | v1451 |
| 3 | `CODREGRA_TRIBUTARIA` | `INTEGER` | NOT NULL | → `REGRA_TRIBUTARIA` | v1451 | v1451 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1451 | CREATE | CREATE TABLE com 3 colunas |

