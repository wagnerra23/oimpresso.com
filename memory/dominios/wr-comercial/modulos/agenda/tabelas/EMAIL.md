---
table: EMAIL
module: agenda
created_at_version: 369
last_modified_version: 386
target_version: 1468
columns_count: 19
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 369;
- **Última mudança:** UPDATE 386;
- **Total colunas (versão 1468):** 19

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v369 | v369 |
| 2 | `CODCRM_DATABASE` | `INTEGER` | NOT NULL | v369 | v369 |
| 3 | `CODEMAIL_CONTA` | `INTEGER` | NULL | v369 | v369 |
| 4 | `CODEMAIL_CONTA_CRM_DATABASE` | `INTEGER` | NULL | v369 | v369 |
| 5 | `CODEMAIL_CAIXA` | `INTEGER` | NULL | v369 | v369 |
| 6 | `CODEMAIL_CAIXA_CRM_DATABASE` | `INTEGER` | NULL | v369 | v369 |
| 7 | `DE` | `VARCHAR(1000)` | NULL | v369 | v386 |
| 8 | `PARA` | `VARCHAR(2000)` | NULL | v369 | v386 |
| 9 | `PRIORIDADE` | `INTEGER` | NULL | v369 | v369 |
| 10 | `LIDO` | `INTEGER` | NULL | v369 | v369 |
| 11 | `CODANEXO` | `INTEGER` | NULL | v369 | v369 |
| 12 | `ASSUNTO` | `VARCHAR(1000)` | NULL | v369 | v386 |
| 13 | `DATA` | `TIMESTAMP` | NULL | v369 | v369 |
| 14 | `CONTEUDO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL | v369 | v369 |
| 15 | `CODWEB` | `VARCHAR(30)` | NULL | v369 | v380 |
| 16 | `ATIVO` | `VARCHAR(1)` | NULL | v369 | v369 |
| 17 | `PARACCO` | `VARCHAR(2000)` | NULL | v369 | v386 |
| 18 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v369 | v369 |
| 19 | `CODUSUARIO_ENVIO` | `INTEGER` | NULL | v379 | v379 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 369 | CREATE | CREATE TABLE com 18 colunas |
| 379 | ADD_COL | + CODUSUARIO_ENVIO INTEGER |
| 380 | ALTER_TYPE | ~ CODWEB TYPE VARCHAR(30) |
| 386 | ALTER_TYPE | ~ DE TYPE VARCHAR(1000) |
| 386 | ALTER_TYPE | ~ PARA TYPE VARCHAR(2000) |
| 386 | ALTER_TYPE | ~ ASSUNTO TYPE VARCHAR(1000) |
| 386 | ALTER_TYPE | ~ PARACCO TYPE VARCHAR(2000) |

