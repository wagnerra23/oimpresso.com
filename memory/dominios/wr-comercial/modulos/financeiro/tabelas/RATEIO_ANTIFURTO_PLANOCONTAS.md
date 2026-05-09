---
table: RATEIO_ANTIFURTO_PLANOCONTAS
module: financeiro
created_at_version: 727
last_modified_version: 727
target_version: 1468
columns_count: 2
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `RATEIO_ANTIFURTO_PLANOCONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 727;
- **Última mudança:** UPDATE 727;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODRATEIO` | `INTEGER` | NOT NULL | v727 | v727 |
| 2 | `CODPLANOCONTAS` | `VARCHAR(15)` | NOT NULL | v727 | v727 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 727 | CREATE | CREATE TABLE com 2 colunas |

