---
table: USUARIO
module: cadastros
created_at_version: 50
last_modified_version: 1405
target_version: 1468
columns_count: 13
foreign_keys_count: 3
foreign_keys:
  CODEMAIL_CONTA_CRM_DB_PADRAO: EMAIL_CONTA
  CODEMAIL_CONTA_PADRAO: EMAIL_CONTA
  CODFUNCIONARIO: FUNCIONARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `USUARIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 50;
- **Última mudança:** UPDATE 1405;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMAIL_CONTA_CRM_DB_PADRAO` | [`EMAIL_CONTA`](../../agenda/tabelas/EMAIL_CONTA.md) |
| `CODEMAIL_CONTA_PADRAO` | [`EMAIL_CONTA`](../../agenda/tabelas/EMAIL_CONTA.md) |
| `CODFUNCIONARIO` | [`FUNCIONARIO`](../../cadastros/tabelas/FUNCIONARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `SKIN` | `VARCHAR(30)` | NULL |  | v50 | v476 |
| 2 | `LAYOUT_PERFIL` | `INTEGER` | NULL |  | v51 | v51 |
| 3 | `CODFUNCIONARIO` | `VARCHAR(10)` | NULL | → `FUNCIONARIO` | v54 | v54 |
| 4 | `COLOR` | `INTEGER` | NULL |  | v116 | v116 |
| 5 | `IMAGEINDEX` | `SMALLINT` | NULL |  | v116 | v116 |
| 6 | `DT_INICIAL` | `TIMESTAMP` | NULL |  | v116 | v116 |
| 7 | `DT_FINAL` | `TIMESTAMP` | NULL |  | v116 | v116 |
| 8 | `MINUTOS` | `INTEGER` | NULL |  | v116 | v116 |
| 9 | `CODEMAIL_CONTA_PADRAO` | `INTEGER` | NULL | → `EMAIL_CONTA` | v379 | v379 |
| 10 | `CODEMAIL_CONTA_CRM_DB_PADRAO` | `INTEGER` | NULL | → `EMAIL_CONTA` | v379 | v379 |
| 11 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1405 | v1405 |
| 12 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1405 | v1405 |
| 13 | `OIMPRESSO_SINCRONIZADO` | `VARCHAR(1)` | NULL |  | v1405 | v1405 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 50 | ADD_COL | + SKIN INTEGER |
| 51 | ADD_COL | + LAYOUT_PERFIL INTEGER |
| 54 | ADD_COL | + CODFUNCIONARIO VARCHAR(10) |
| 116 | ADD_COL | + COLOR INTEGER |
| 116 | ADD_COL | + IMAGEINDEX SMALLINT |
| 116 | ADD_COL | + DT_INICIAL TIMESTAMP |
| 116 | ADD_COL | + DT_FINAL TIMESTAMP |
| 116 | ADD_COL | + MINUTOS INTEGER |
| 379 | ADD_COL | + CODEMAIL_CONTA_PADRAO INTEGER |
| 379 | ADD_COL | + CODEMAIL_CONTA_CRM_DB_PADRAO INTEGER |
| 476 | ALTER_TYPE | ~ SKIN TYPE VARCHAR(30) |
| 1405 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1405 | ADD_COL | + ATIVO VARCHAR(1) |
| 1405 | ADD_COL | + OIMPRESSO_SINCRONIZADO VARCHAR(1) |

