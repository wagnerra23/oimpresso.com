---
table: FINANCEIRO_HIST_AGRUPAMENTO
module: financeiro
created_at_version: 424
last_modified_version: 424
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_HIST_AGRUPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 424;
- **Última mudança:** UPDATE 424;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v424 | v424 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL | v424 | v424 |
| 3 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | v424 | v424 |
| 4 | `SEQUENCIA` | `INTEGER` | NOT NULL | v424 | v424 |
| 5 | `CODFINANCEIRO_HISTORICO` | `INTEGER` | NULL | v424 | v424 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 424 | CREATE | CREATE TABLE com 5 colunas |

