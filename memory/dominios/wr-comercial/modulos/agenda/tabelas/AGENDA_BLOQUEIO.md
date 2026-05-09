---
table: AGENDA_BLOQUEIO
module: agenda
created_at_version: 12
last_modified_version: 12
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_BLOQUEIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 12;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v12 | v12 |
| 2 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v12 | v12 |
| 3 | `DATABLOQUEIO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 4 | `STATUS` | `VARCHAR(15)` | NULL |  | v12 | v12 |
| 5 | `DATA_ALTERACAO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 6 | `MOTIVO` | `VARCHAR(255)` | NULL |  | v12 | v12 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | CREATE | CREATE TABLE com 1 colunas |
| 12 | ADD_COL | + CODUSUARIO INTEGER |
| 12 | ADD_COL | + DATABLOQUEIO TIMESTAMP |
| 12 | ADD_COL | + STATUS VARCHAR(15) |
| 12 | ADD_COL | + DATA_ALTERACAO TIMESTAMP |
| 12 | ADD_COL | + MOTIVO VARCHAR(255) |
| 12 | ALTER_TYPE | ~ CODUSUARIO TYPE INTEGER |
| 12 | ALTER_TYPE | ~ DATABLOQUEIO TYPE TIMESTAMP |
| 12 | ALTER_TYPE | ~ STATUS TYPE VARCHAR(15) |
| 12 | ALTER_TYPE | ~ DATA_ALTERACAO TYPE TIMESTAMP |
| 12 | ALTER_TYPE | ~ MOTIVO TYPE VARCHAR(255) |

