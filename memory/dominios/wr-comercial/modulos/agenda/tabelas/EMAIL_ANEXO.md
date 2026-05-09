---
table: EMAIL_ANEXO
module: agenda
created_at_version: 369
last_modified_version: 386
target_version: 1468
columns_count: 9
foreign_keys_count: 2
foreign_keys:
  CODEMAIL: EMAIL
  CODEMAIL_CRM_DATABASE: EMAIL
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_ANEXO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 369;
- **Última mudança:** UPDATE 386;
- **Total colunas (versão 1468):** 9

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMAIL` | [`EMAIL`](../../agenda/tabelas/EMAIL.md) |
| `CODEMAIL_CRM_DATABASE` | [`EMAIL`](../../agenda/tabelas/EMAIL.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v369 | v369 |
| 2 | `CODCRM_DATABASE` | `INTEGER` | NOT NULL |  | v369 | v369 |
| 3 | `CODEMAIL` | `INTEGER` | NULL | → `EMAIL` | v369 | v369 |
| 4 | `CODEMAIL_CRM_DATABASE` | `INTEGER` | NULL | → `EMAIL` | v369 | v369 |
| 5 | `DESCRICAO` | `VARCHAR(1000)` | NULL |  | v369 | v386 |
| 6 | `CONTEUDO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v369 | v369 |
| 7 | `CAMINHO` | `VARCHAR(1000)` | NULL |  | v369 | v386 |
| 8 | `TIPO_PART` | `VARCHAR(255)` | NULL |  | v369 | v369 |
| 9 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v369 | v369 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 369 | CREATE | CREATE TABLE com 9 colunas |
| 386 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(1000) |
| 386 | ALTER_TYPE | ~ CAMINHO TYPE VARCHAR(1000) |

