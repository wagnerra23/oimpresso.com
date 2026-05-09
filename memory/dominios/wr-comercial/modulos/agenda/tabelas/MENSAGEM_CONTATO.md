---
table: MENSAGEM_CONTATO
module: agenda
created_at_version: 1286
last_modified_version: 1286
target_version: 1468
columns_count: 7
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM_CONTATO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1286;
- **Última mudança:** UPDATE 1286;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1286 | v1286 |
| 2 | `DESCRICAO` | `VARCHAR(255)` | NULL | v1286 | v1286 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL | v1286 | v1286 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1286 | v1286 |
| 5 | `CODPESSOAS` | `VARCHAR(15)` | NULL | v1286 | v1286 |
| 6 | `CONTATO` | `VARCHAR(500)` | NULL | v1286 | v1286 |
| 7 | `TIPO_CONTATO` | `VARCHAR(100)` | NULL | v1286 | v1286 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1286 | CREATE | CREATE TABLE com 7 colunas |

