---
table: WR_CONFIG
module: wr_metadata
created_at_version: 1344
last_modified_version: 1430
target_version: 1468
columns_count: 12
foreign_keys_count: 1
foreign_keys:
  CODWR_APP: WR_APP
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_CONFIG`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1344;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_APP` | [`WR_APP`](../../wr_metadata/tabelas/WR_APP.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1344 | v1344 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1344 | v1344 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1344 | v1344 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1344 | v1344 |
| 5 | `DICA` | `VARCHAR(1000)` | NULL |  | v1344 | v1344 |
| 6 | `OBSERVACAO` | `VARCHAR(1000)` | NULL |  | v1344 | v1344 |
| 7 | `TIPO` | `VARCHAR(255)` | NULL |  | v1344 | v1344 |
| 8 | `CONFIG` | `VARCHAR(255)` | NULL |  | v1344 | v1344 |
| 9 | `SUB` | `VARCHAR(255)` | NULL |  | v1344 | v1344 |
| 10 | `CODWR_APP` | `INTEGER` | NULL | → `WR_APP` | v1344 | v1344 |
| 11 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |
| 12 | `PATHWR_APP` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1344 | CREATE | CREATE TABLE com 4 colunas |
| 1344 | ADD_COL | + DICA VARCHAR(1000) |
| 1344 | ADD_COL | + OBSERVACAO VARCHAR(1000) |
| 1344 | ADD_COL | + TIPO VARCHAR(255) |
| 1344 | ADD_COL | + CONFIG VARCHAR(255) |
| 1344 | ADD_COL | + SUB VARCHAR(255) |
| 1344 | ADD_COL | + CODWR_APP INTEGER |
| 1430 | ADD_COL | + PATH VARCHAR(255) |
| 1430 | ADD_COL | + PATHWR_APP VARCHAR(255) |

