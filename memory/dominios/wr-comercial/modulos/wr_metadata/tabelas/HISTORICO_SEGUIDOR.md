---
table: HISTORICO_SEGUIDOR
module: wr_metadata
created_at_version: 975
last_modified_version: 1244
target_version: 1468
columns_count: 15
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

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `codigo` | `integer` | NOT NULL | v975 | v975 |
| 2 | `TABELA` | `VARCHAR(255)` | NULL | v975 | v975 |
| 3 | `CODTABELA` | `VARCHAR(40)` | NULL | v975 | v975 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | v975 | v975 |
| 5 | `CODPESSOA` | `VARCHAR(15)` | NULL | v982 | v982 |
| 6 | `CODHISTORICO_SLA` | `INTEGER` | NULL | v1240 | v1240 |
| 7 | `CODSLA` | `INTEGER` | NULL | v1240 | v1240 |
| 8 | `TEM_NOVO` | `INTEGER` | NULL | v1240 | v1240 |
| 9 | `TEM_EDITAR` | `INTEGER` | NULL | v1240 | v1240 |
| 10 | `TEM_EXCLUIR` | `INTEGER` | NULL | v1240 | v1240 |
| 11 | `TEM_NOTIFICACAO` | `INTEGER` | NULL | v1240 | v1240 |
| 12 | `TEM_EMAIL` | `INTEGER` | NULL | v1240 | v1240 |
| 13 | `CHAVE_PK1` | `INTEGER` | NULL | v1244 | v1244 |
| 14 | `CHAVE_PK2` | `VARCHAR(40)` | NULL | v1244 | v1244 |
| 15 | `CHAVE_PK3` | `VARCHAR(15)` | NULL | v1244 | v1244 |

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

