---
table: MENSAGEM
module: agenda
created_at_version: 1430
last_modified_version: 1431
target_version: 1468
columns_count: 8
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1430;
- **Última mudança:** UPDATE 1431;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1430 | v1430 |
| 2 | `CODMENSAGEM_ASSUNTO` | `INTEGER` | NULL | v1430 | v1430 |
| 3 | `MENSAGEM` | `VARCHAR(5000)` | NULL | v1430 | v1430 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | v1430 | v1430 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1430 | v1430 |
| 6 | `DT_MENSAGEM` | `TIMESTAMP` | NULL | v1430 | v1430 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL | v1430 | v1430 |
| 8 | `DATATYPE` | `VARCHAR(10)` | NULL | v1431 | v1431 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1430 | CREATE | CREATE TABLE com 7 colunas |
| 1431 | ADD_COL | + DATATYPE VARCHAR(10) |

