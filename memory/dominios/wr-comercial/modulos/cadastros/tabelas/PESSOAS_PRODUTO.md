---
table: PESSOAS_PRODUTO
module: cadastros
created_at_version: 355
last_modified_version: 355
target_version: 1468
columns_count: 5
foreign_keys_count: 2
foreign_keys:
  CODPESSOAS: PESSOAS
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_PRODUTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 355;
- **Última mudança:** UPDATE 355;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOAS` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPESSOAS` | `VARCHAR(10)` | NOT NULL | → `PESSOAS` | v355 | v355 |
| 2 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v355 | v355 |
| 3 | `TIPO` | `VARCHAR(15)` | NOT NULL |  | v355 | v355 |
| 4 | `DT_ENTREGA` | `DATE` | NULL |  | v355 | v355 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v355 | v355 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 355 | CREATE | CREATE TABLE com 5 colunas |

