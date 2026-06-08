---
table: WR_COMPONENTE
module: wr_metadata
created_at_version: 1324
last_modified_version: 1346
target_version: 1468
columns_count: 11
foreign_keys_count: 1
foreign_keys:
  CODWR_FORM: WR_FORM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_COMPONENTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1324;
- **Última mudança:** UPDATE 1346;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_FORM` | [`WR_FORM`](../../wr_metadata/tabelas/WR_FORM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NULL |  | v1324 | v1324 |
| 2 | `FORM` | `VARCHAR(50)` | NULL |  | v1324 | v1324 |
| 3 | `COMPONENTE` | `VARCHAR(150)` | NULL |  | v1324 | v1324 |
| 4 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1324 | v1324 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 6 | `CAPTION` | `VARCHAR(255)` | NULL |  | v1345 | v1345 |
| 7 | `ADD` | `HINT VARCHAR(1000)` | NULL |  | v1345 | v1345 |
| 8 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 9 | `CODWR_FORM` | `INTEGER` | NULL | → `WR_FORM` | v1345 | v1345 |
| 10 | `TABELA` | `VARCHAR(255)` | NULL |  | v1346 | v1346 |
| 11 | `CAMPO` | `VARCHAR(255)` | NULL |  | v1346 | v1346 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1324 | CREATE | CREATE TABLE com 5 colunas |
| 1345 | ADD_COL | + CAPTION VARCHAR(255) |
| 1345 | ADD_COL | + ADD HINT VARCHAR(1000) |
| 1345 | ADD_COL | + TEM_PADRAO VARCHAR(1) |
| 1345 | ADD_COL | + CODWR_FORM INTEGER |
| 1346 | ADD_COL | + TABELA VARCHAR(255) |
| 1346 | ADD_COL | + CAMPO VARCHAR(255) |

