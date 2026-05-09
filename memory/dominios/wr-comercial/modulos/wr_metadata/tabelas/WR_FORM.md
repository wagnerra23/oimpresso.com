---
table: WR_FORM
module: wr_metadata
created_at_version: 1324
last_modified_version: 1430
target_version: 1468
columns_count: 7
foreign_keys_count: 1
foreign_keys:
  CODWR_APP: WR_APP
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_FORM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1324;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_APP` | [`WR_APP`](../../wr_metadata/tabelas/WR_APP.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1324 | v1324 |
| 2 | `CODWR_APP` | `INTEGER` | NULL | → `WR_APP` | v1324 | v1324 |
| 3 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1324 | v1324 |
| 4 | `CAPTION` | `VARCHAR(500)` | NULL |  | v1324 | v1324 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1343 | v1343 |
| 7 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1324 | CREATE | CREATE TABLE com 8 colunas |
| 1343 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1343 | DROP_COL | - WIDTH |
| 1343 | DROP_COL | - HEIGHT |
| 1343 | DROP_COL | - TEXT1 |
| 1430 | ADD_COL | + PATH VARCHAR(255) |

