---
table: EMAIL_LOG
module: agenda
created_at_version: 390
last_modified_version: 390
target_version: 1468
columns_count: 8
foreign_keys_count: 2
foreign_keys:
  CODEMAIL: EMAIL
  CODEMAIL_CRM_DATABASE: EMAIL
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_LOG`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 390;
- **Última mudança:** UPDATE 390;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMAIL` | [`EMAIL`](../../agenda/tabelas/EMAIL.md) |
| `CODEMAIL_CRM_DATABASE` | [`EMAIL`](../../agenda/tabelas/EMAIL.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v390 | v390 |
| 2 | `CODCRM_DATABASE` | `INTEGER` | NOT NULL |  | v390 | v390 |
| 3 | `CODEMAIL` | `INTEGER` | NULL | → `EMAIL` | v390 | v390 |
| 4 | `CODEMAIL_CRM_DATABASE` | `INTEGER` | NULL | → `EMAIL` | v390 | v390 |
| 5 | `DT_LOG` | `TIMESTAMP` | NULL |  | v390 | v390 |
| 6 | `EVENTO` | `VARCHAR(10)` | NULL |  | v390 | v390 |
| 7 | `DESCRICAO` | `VARCHAR(2000)` | NULL |  | v390 | v390 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v390 | v390 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 390 | CREATE | CREATE TABLE com 8 colunas |

