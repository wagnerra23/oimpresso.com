---
table: MENSAGEM_LIDO
module: agenda
created_at_version: 1286
last_modified_version: 1430
target_version: 1468
columns_count: 13
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM_LIDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1286;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1286 | v1286 |
| 2 | `CODHISTORICO` | `INTEGER` | NULL | v1286 | v1286 |
| 3 | `CODMENSAGEM_ASSUNTO` | `INTEGER` | NULL | v1286 | v1286 |
| 4 | `LIDO` | `VARCHAR(1)` | NULL | v1286 | v1286 |
| 5 | `DT_LIDO` | `TIMESTAMP` | NULL | v1286 | v1286 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1286 | v1286 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL | v1286 | v1286 |
| 8 | `CODMENSAGEM` | `INTEGER` | NULL | v1430 | v1430 |
| 9 | `CODPESSOA_NOTIFICANTE` | `VARCHAR(15)` | NULL | v1430 | v1430 |
| 10 | `CODPESSOA_NOTIFICADA` | `VARCHAR(15)` | NULL | v1430 | v1430 |
| 11 | `FOI_NOTIFICADA` | `VARCHAR(1)` | NULL | v1430 | v1430 |
| 12 | `CODUSUARIO` | `INTEGER` | NULL | v1430 | v1430 |
| 13 | `PRIORIDADE` | `INTEGER` | NULL | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1286 | CREATE | CREATE TABLE com 7 colunas |
| 1430 | ADD_COL | + CODMENSAGEM INTEGER |
| 1430 | ADD_COL | + CODPESSOA_NOTIFICANTE VARCHAR(15) |
| 1430 | ADD_COL | + CODPESSOA_NOTIFICADA VARCHAR(15) |
| 1430 | ADD_COL | + FOI_NOTIFICADA VARCHAR(1) |
| 1430 | ADD_COL | + CODUSUARIO INTEGER |
| 1430 | ADD_COL | + PRIORIDADE INTEGER |

