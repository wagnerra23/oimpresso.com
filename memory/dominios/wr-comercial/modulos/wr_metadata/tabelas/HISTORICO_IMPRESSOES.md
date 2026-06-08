---
table: HISTORICO_IMPRESSOES
module: wr_metadata
created_at_version: 1162
last_modified_version: 1162
target_version: 1468
columns_count: 8
foreign_keys_count: 2
foreign_keys:
  CODEMPRESA: EMPRESA
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_IMPRESSOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1162;
- **Última mudança:** UPDATE 1162;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1162 | v1162 |
| 2 | `CODEMPRESA` | `VARCHAR(250) CHARACTER SET NONE` | NULL | → `EMPRESA` | v1162 | v1162 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1162 | v1162 |
| 4 | `DATA` | `TIMESTAMP` | NULL |  | v1162 | v1162 |
| 5 | `FORMULARIO` | `VARCHAR(250) CHARACTER SET NONE` | NULL |  | v1162 | v1162 |
| 6 | `TABELA` | `VARCHAR(50) CHARACTER SET NONE` | NULL |  | v1162 | v1162 |
| 7 | `CHAVE_PK` | `VARCHAR(250) CHARACTER SET NONE` | NULL |  | v1162 | v1162 |
| 8 | `RELATORIO` | `VARCHAR(200)` | NULL |  | v1162 | v1162 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1162 | CREATE | CREATE TABLE com 8 colunas |

