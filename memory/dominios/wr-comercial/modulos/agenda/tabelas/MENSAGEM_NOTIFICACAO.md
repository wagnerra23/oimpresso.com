---
table: MENSAGEM_NOTIFICACAO
module: agenda
created_at_version: 1431
last_modified_version: 1431
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM_NOTIFICACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1431;
- **Última mudança:** UPDATE 1431;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1431 | v1431 |
| 2 | `CODMENSAGEM` | `INTEGER` | NULL | v1431 | v1431 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v1431 | v1431 |
| 4 | `DT_LIDO` | `TIMESTAMP` | NULL | v1431 | v1431 |
| 5 | `TEM_FAVORITO` | `VARCHAR(1)` | NULL | v1431 | v1431 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1431 | v1431 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL | v1431 | v1431 |
| 8 | `LIDO` | `VARCHAR(1)` | NULL | v1431 | v1431 |
| 9 | `CODPESSOA_NOTIFICANTE` | `VARCHAR(15)` | NULL | v1431 | v1431 |
| 10 | `CODPESSOA_NOTIFICADA` | `VARCHAR(15)` | NULL | v1431 | v1431 |
| 11 | `FOI_NOTIFICADA` | `VARCHAR(1)` | NULL | v1431 | v1431 |
| 12 | `CODHISTORICO` | `INTEGER` | NULL | v1431 | v1431 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1431 | CREATE | CREATE TABLE com 12 colunas |

