---
table: FINANCEIRO_SETOR
module: financeiro
created_at_version: 304
last_modified_version: 304
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_SETOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 304;
- **Última mudança:** UPDATE 304;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODFINANCEIRO` | `INTEGER` | NOT NULL | v304 | v304 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL | v304 | v304 |
| 3 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | v304 | v304 |
| 4 | `CODSETOR` | `INTEGER` | NOT NULL | v304 | v304 |
| 5 | `RATEIO` | `DOUBLE PRECISION` | NULL | v304 | v304 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 304 | CREATE | CREATE TABLE com 5 colunas |

