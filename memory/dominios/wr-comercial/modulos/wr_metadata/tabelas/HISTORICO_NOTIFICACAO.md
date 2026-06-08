---
table: HISTORICO_NOTIFICACAO
module: wr_metadata
created_at_version: 975
last_modified_version: 1258
target_version: 1468
columns_count: 15
foreign_keys_count: 4
foreign_keys:
  CODHISTORICO: HISTORICO
  CODHISTORICO_SLA: HISTORICO_SLA
  CODPESSOA: PESSOAS
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_NOTIFICACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 975;
- **Última mudança:** UPDATE 1258;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODHISTORICO` | [`HISTORICO`](../../wr_metadata/tabelas/HISTORICO.md) |
| `CODHISTORICO_SLA` | [`HISTORICO_SLA`](../../wr_metadata/tabelas/HISTORICO_SLA.md) |
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v975 | v975 |
| 2 | `CODHISTORICO` | `INTEGER` | NULL | → `HISTORICO` | v975 | v975 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v975 | v975 |
| 4 | `DT_LIDO` | `TIMESTAMP` | NULL |  | v975 | v975 |
| 5 | `TEM_FAVORITO` | `VARCHAR(1)` | NULL |  | v981 | v981 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v985 | v985 |
| 7 | `CODPESSOA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v993 | v993 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v999 | v999 |
| 9 | `FORM` | `VARCHAR(50)` | NULL |  | v999 | v999 |
| 10 | `CODIGOPK1` | `INTEGER` | NULL |  | v1000 | v1000 |
| 11 | `LIDO` | `VARCHAR(1)` | NULL |  | v1005 | v1005 |
| 12 | `MODULO` | `VARCHAR(255)` | NULL |  | v1136 | v1136 |
| 13 | `CHAVE_PK1` | `INTEGER, ADD CHAVE_PK2 VARCHAR(40), ADD CHAVE_PK3 VARCHAR(15)` | NULL |  | v1011 | v1011 |
| 14 | `TABELA` | `VARCHAR(255)` | NULL |  | v1121 | v1121 |
| 15 | `CODHISTORICO_SLA` | `INTEGER` | NULL | → `HISTORICO_SLA` | v1258 | v1258 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 975 | CREATE | CREATE TABLE com 4 colunas |
| 981 | ADD_COL | + TEM_FAVORITO VARCHAR(1) |
| 985 | ADD_COL | + ATIVO INTEGER |
| 985 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 993 | ADD_COL | + CODPESSOA VARCHAR(15) |
| 999 | DROP_COL | - ATIVO |
| 999 | ADD_COL | + ATIVO VARCHAR(1) |
| 999 | ADD_COL | + FORM VARCHAR(50) |
| 1000 | ADD_COL | + CODUSUARIO_NOTIFICADO INTEGER |
| 1000 | ADD_COL | + CODIGOPK1 INTEGER |
| 1004 | DROP_COL | - CODUSUARIO_NOTIFICADO |
| 1004 | ADD_COL | + LIDO VARCHAR(1) |
| 1005 | ADD_COL | + LIDO VARCHAR(1) |
| 1011 | ADD_COL | + MODULO VARCHAR(255) |
| 1011 | ADD_COL | + CHAVE_PK1 INTEGER, ADD CHAVE_PK2 VARCHAR(40), ADD CHAVE_PK3 VARCHAR(15) |
| 1011 | ADD_COL | + TABELA VARCHAR(255) |
| 1121 | ADD_COL | + TABELA VARCHAR(255) |
| 1136 | ADD_COL | + MODULO VARCHAR(255) |
| 1258 | ADD_COL | + CODHISTORICO_SLA INTEGER |

