---
table: WR_CONTROLE_PARENT
module: wr_metadata
created_at_version: 1339
last_modified_version: 1430
target_version: 1468
columns_count: 5
foreign_keys_count: 1
foreign_keys:
  CODWR_CONTROLE: WR_CONTROLE
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_CONTROLE_PARENT`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1339;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_CONTROLE` | [`WR_CONTROLE`](../../wr_metadata/tabelas/WR_CONTROLE.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODORIGEM` | `INTEGER` | NULL |  | v1339 | v1339 |
| 2 | `CODWR_CONTROLE` | `INTEGER` | NULL | → `WR_CONTROLE` | v1339 | v1339 |
| 3 | `TIPO` | `VARCHAR(50)` | NULL |  | v1340 | v1340 |
| 4 | `TEM_NA_CONSULTA` | `VARCHAR(1)` | NULL |  | v1348 | v1348 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1339 | CREATE | CREATE TABLE com 3 colunas |
| 1340 | ADD_COL | + TIPO VARCHAR(50) |
| 1343 | DROP_COL | - TABELA |
| 1348 | ADD_COL | + TEM_NA_CONSULTA VARCHAR(1) |
| 1430 | ADD_COL | + ATIVO VARCHAR(1) |

