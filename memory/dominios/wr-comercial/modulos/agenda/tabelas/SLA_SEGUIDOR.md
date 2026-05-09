---
table: SLA_SEGUIDOR
module: agenda
created_at_version: 1237
last_modified_version: 1237
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SLA_SEGUIDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1237;
- **Última mudança:** UPDATE 1237;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1237 | v1237 |
| 2 | `CODSLA` | `INTEGER` | NULL | v1237 | v1237 |
| 3 | `CODPESSOA` | `VARCHAR(100)` | NULL | v1237 | v1237 |
| 4 | `ENVIA_EMAIL` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 5 | `ENVIA_NOTIFICACAO` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 6 | `INSERIR` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 7 | `FINALIZAR` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 8 | `EXCLUIR` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 9 | `MODIFICAR` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 10 | `REATIVAR` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL | v1237 | v1237 |
| 12 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1237 | v1237 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1237 | CREATE | CREATE TABLE com 12 colunas |

