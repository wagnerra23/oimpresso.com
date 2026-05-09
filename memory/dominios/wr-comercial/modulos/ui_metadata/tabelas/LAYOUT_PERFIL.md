---
table: LAYOUT_PERFIL
module: ui_metadata
created_at_version: 51
last_modified_version: 51
target_version: 1468
columns_count: 4
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `LAYOUT_PERFIL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 51;
- **Última mudança:** UPDATE 51;
- **Total colunas (versão 1468):** 4

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v51 | v51 |
| 2 | `CODUSUARIO` | `INTEGER` | NOT NULL | → `USUARIO` | v51 | v51 |
| 3 | `DESCRICAO` | `VARCHAR(50)` | NOT NULL |  | v51 | v51 |
| 4 | `"HASH"` | `VARCHAR(50)` | NOT NULL |  | v51 | v51 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 51 | CREATE | CREATE TABLE com 4 colunas |

