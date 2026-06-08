---
table: CONFIGURACOES_GRID
module: ui_metadata
created_at_version: 728
last_modified_version: 791
target_version: 1468
columns_count: 8
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACOES_GRID`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 728;
- **Última mudança:** UPDATE 791;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v728 | v728 |
| 2 | `FORM` | `VARCHAR(100)` | NULL |  | v728 | v728 |
| 3 | `DESCRICAO` | `VARCHAR(255)` | NULL |  | v728 | v728 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v728 | v728 |
| 5 | `GRID` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v728 | v728 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 7 | `ARQUIVO_INI` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v791 | v791 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v791 | v791 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 728 | CREATE | CREATE TABLE com 6 colunas |
| 791 | ADD_COL | + ARQUIVO_INI BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 791 | ADD_COL | + ATIVO VARCHAR(1) |

