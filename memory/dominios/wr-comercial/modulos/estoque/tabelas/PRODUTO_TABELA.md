---
table: PRODUTO_TABELA
module: estoque
created_at_version: 809
last_modified_version: 888
target_version: 1468
columns_count: 5
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_TABELA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 809;
- **Última mudança:** UPDATE 888;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v809 | v809 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v809 | v809 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v809 | v809 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v809 | v809 |
| 5 | `CODEMPRESA` | `INTEGER` | NULL | → `EMPRESA` | v888 | v888 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 809 | CREATE | CREATE TABLE com 4 colunas |
| 888 | ADD_COL | + CODEMPRESA INTEGER |

