---
table: OIMPRESSO_LOG
module: api
created_at_version: 1219
last_modified_version: 1248
target_version: 1468
columns_count: 15
foreign_keys_count: 2
foreign_keys:
  CODOIMPRESSO: OIMPRESSO
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `OIMPRESSO_LOG`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `api` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1219;
- **Última mudança:** UPDATE 1248;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODOIMPRESSO` | [`OIMPRESSO`](../../api/tabelas/OIMPRESSO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1219 | v1219 |
| 2 | `CODoimpresso` | `INTEGER` | NULL | → `OIMPRESSO` | v1219 | v1219 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1219 | v1219 |
| 4 | `DATA` | `TIMESTAMP` | NULL |  | v1219 | v1226 |
| 5 | `EVENTO` | `VARCHAR(50)` | NULL |  | v1219 | v1219 |
| 6 | `OBS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v1219 | v1219 |
| 7 | `TABELA` | `VARCHAR(50)` | NULL |  | v1219 | v1219 |
| 8 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1219 | v1219 |
| 9 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1219 | v1219 |
| 10 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1219 | v1219 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1219 | v1219 |
| 12 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1219 | v1219 |
| 13 | `RETORNO` | `VARCHAR(10)` | NULL |  | v1226 | v1226 |
| 14 | `BAIXADO` | `VARCHAR(1)` | NULL |  | v1248 | v1248 |
| 15 | `ENVIADO` | `VARCHAR(1)` | NULL |  | v1248 | v1248 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1219 | CREATE | CREATE TABLE com 15 colunas |
| 1226 | DROP_COL | - HORA |
| 1226 | DROP_COL | - CODEMPRESA |
| 1226 | ALTER_TYPE | ~ DATA TYPE TIMESTAMP |
| 1226 | DROP_COL | - DT_FECHAMENTO |
| 1226 | ADD_COL | + RETORNO VARCHAR(10) |
| 1248 | ADD_COL | + BAIXADO VARCHAR(1) |
| 1248 | ADD_COL | + ENVIADO VARCHAR(1) |

