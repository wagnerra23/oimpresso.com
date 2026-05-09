---
table: AGENDA_TITULO
module: agenda
created_at_version: 12
last_modified_version: 728
target_version: 1468
columns_count: 13
foreign_keys_count: 1
foreign_keys:
  CODSETOR: SETOR
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_TITULO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 728;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODSETOR` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v12 | v12 |
| 2 | `DESCRICAO` | `VARCHAR(32)` | NULL |  | v12 | v12 |
| 3 | `MINUTOS` | `INTEGER` | NULL |  | v12 | v12 |
| 4 | `COLOR` | `INTEGER` | NULL |  | v12 | v12 |
| 5 | `IMAGEINDEX` | `SMALLINT` | NULL |  | v12 | v12 |
| 6 | `TITULO_FORMATO` | `VARCHAR(10)` | NULL |  | v12 | v12 |
| 7 | `MOSTRAR_HORARIO` | `CHAR(1)` | NULL |  | v12 | v12 |
| 8 | `DT_INICIAL` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 9 | `DT_FINAL` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 10 | `EXAME_TIPO` | `VARCHAR(20)` | NULL |  | v12 | v12 |
| 11 | `CODSETOR` | `INTEGER` | NULL | → `SETOR` | v100 | v100 |
| 12 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 13 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | CREATE | CREATE TABLE com 1 colunas |
| 12 | ADD_COL | + DESCRICAO VARCHAR(32) |
| 12 | ADD_COL | + MINUTOS INTEGER |
| 12 | ADD_COL | + COLOR INTEGER |
| 12 | ADD_COL | + IMAGEINDEX SMALLINT |
| 12 | ADD_COL | + TITULO_FORMATO VARCHAR(10) |
| 12 | ADD_COL | + MOSTRAR_HORARIO CHAR(1) |
| 12 | ADD_COL | + DT_INICIAL TIMESTAMP |
| 12 | ADD_COL | + DT_FINAL TIMESTAMP |
| 12 | ADD_COL | + EXAME_TIPO VARCHAR(20) |
| 12 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(32) |
| 12 | ALTER_TYPE | ~ MINUTOS TYPE INTEGER |
| 12 | ALTER_TYPE | ~ COLOR TYPE INTEGER |
| 12 | ALTER_TYPE | ~ IMAGEINDEX TYPE SMALLINT |
| 12 | ALTER_TYPE | ~ TITULO_FORMATO TYPE VARCHAR(10) |
| 12 | ALTER_TYPE | ~ MOSTRAR_HORARIO TYPE CHAR(1) |
| 12 | ALTER_TYPE | ~ DT_INICIAL TYPE TIMESTAMP |
| 12 | ALTER_TYPE | ~ DT_FINAL TYPE TIMESTAMP |
| 12 | ALTER_TYPE | ~ EXAME_TIPO TYPE VARCHAR(20) |
| 100 | ADD_COL | + CODSETOR INTEGER |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

