---
table: CENTRO_TRABALHO_PLANOCONTAS
module: producao
created_at_version: 962
last_modified_version: 962
target_version: 1468
columns_count: 3
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CENTRO_TRABALHO_PLANOCONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 962;
- **Última mudança:** UPDATE 962;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v962 | v962 |
| 2 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | v962 | v962 |
| 3 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | v962 | v962 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 962 | CREATE | CREATE TABLE com 3 colunas |

