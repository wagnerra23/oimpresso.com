---
table: LAYOUT_FORM
module: ui_metadata
created_at_version: 51
last_modified_version: 1095
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODLAYOUT_PERFIL: LAYOUT_PERFIL
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `LAYOUT_FORM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 51;
- **Última mudança:** UPDATE 1095;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODLAYOUT_PERFIL` | [`LAYOUT_PERFIL`](../../ui_metadata/tabelas/LAYOUT_PERFIL.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v51 | v51 |
| 2 | `CODLAYOUT_PERFIL` | `INTEGER` | NOT NULL | → `LAYOUT_PERFIL` | v51 | v51 |
| 3 | `LAYOUT` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NOT NULL |  | v51 | v51 |
| 4 | `FORM` | `VARCHAR(150)` | NOT NULL |  | v51 | v539 |
| 5 | `"HASH"` | `VARCHAR(50)` | NOT NULL |  | v51 | v51 |
| 6 | `MIGRADO` | `VARCHAR(1) CHARACTER SET WIN1252` | NULL |  | v1095 | v1095 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 51 | CREATE | CREATE TABLE com 5 colunas |
| 463 | ALTER_TYPE | ~ FORM TYPE VARCHAR(150) |
| 539 | ALTER_TYPE | ~ FORM TYPE VARCHAR(150) |
| 1095 | ADD_COL | + MIGRADO VARCHAR(1) CHARACTER SET WIN1252 |

