---
table: HISTORICO_SEGUIDOR
module: wr_metadata
created_at_version: 975
last_modified_version: 1244
target_version: 1468
columns_count: 15
foreign_keys_count: 4
foreign_keys:
  CODHISTORICO_SLA: HISTORICO_SLA
  CODPESSOA: PESSOAS
  CODSLA: SLA
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_SEGUIDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 975;
- **Última mudança:** UPDATE 1244;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODHISTORICO_SLA` | [`HISTORICO_SLA`](../../wr_metadata/tabelas/HISTORICO_SLA.md) |
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODSLA` | [`SLA`](../../agenda/tabelas/SLA.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `codigo` | `integer` | NOT NULL |  | v975 | v975 |
| 2 | `TABELA` | `VARCHAR(255)` | NULL |  | v975 | v975 |
| 3 | `CODTABELA` | `VARCHAR(40)` | NULL |  | v975 | v975 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v975 | v975 |
| 5 | `CODPESSOA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v982 | v982 |
| 6 | `CODHISTORICO_SLA` | `INTEGER` | NULL | → `HISTORICO_SLA` | v1240 | v1240 |
| 7 | `CODSLA` | `INTEGER` | NULL | → `SLA` | v1240 | v1240 |
| 8 | `TEM_NOVO` | `INTEGER` | NULL |  | v1240 | v1240 |
| 9 | `TEM_EDITAR` | `INTEGER` | NULL |  | v1240 | v1240 |
| 10 | `TEM_EXCLUIR` | `INTEGER` | NULL |  | v1240 | v1240 |
| 11 | `TEM_NOTIFICACAO` | `INTEGER` | NULL |  | v1240 | v1240 |
| 12 | `TEM_EMAIL` | `INTEGER` | NULL |  | v1240 | v1240 |
| 13 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1244 | v1244 |
| 14 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1244 | v1244 |
| 15 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1244 | v1244 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 975 | CREATE | CREATE TABLE com 9 colunas |
| 982 | ADD_COL | + CODPESSOA VARCHAR(15) |
| 984 | ADD_COL | + TEM_NOTIFICACAO VARCHAR(1) |
| 1240 | ADD_COL | + CODHISTORICO_SLA INTEGER |
| 1240 | ADD_COL | + TEM_EMAIL VARCHAR(1) |
| 1240 | ADD_COL | + CODSLA INTEGER |
| 1240 | DROP_COL | - TEM_NOVO |
| 1240 | DROP_COL | - TEM_EDITAR |
| 1240 | DROP_COL | - TEM_EXCLUIR |
| 1240 | DROP_COL | - TEM_NOTIFICACAO |
| 1240 | DROP_COL | - TEM_TODOS |
| 1240 | DROP_COL | - TEM_EMAIL |
| 1240 | ADD_COL | + TEM_NOVO INTEGER |
| 1240 | ADD_COL | + TEM_EDITAR INTEGER |
| 1240 | ADD_COL | + TEM_EXCLUIR INTEGER |
| 1240 | ADD_COL | + TEM_NOTIFICACAO INTEGER |
| 1240 | ADD_COL | + TEM_EMAIL INTEGER |
| 1244 | ADD_COL | + CHAVE_PK1 INTEGER |
| 1244 | ADD_COL | + CHAVE_PK2 VARCHAR(40) |
| 1244 | ADD_COL | + CHAVE_PK3 VARCHAR(15) |

