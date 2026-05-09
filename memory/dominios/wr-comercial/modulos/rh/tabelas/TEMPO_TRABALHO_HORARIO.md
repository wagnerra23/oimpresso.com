---
table: TEMPO_TRABALHO_HORARIO
module: rh
created_at_version: 579
last_modified_version: 579
target_version: 1468
columns_count: 10
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TEMPO_TRABALHO_HORARIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 579;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v579 | v579 |
| 2 | `CODTEMPO_TRABALHO` | `INTEGER` | NOT NULL | v579 | v579 |
| 3 | `DESCRICAO` | `VARCHAR(150)` | NULL | v579 | v579 |
| 4 | `DIA_SEMANA` | `VARCHAR(10)` | NULL | v579 | v579 |
| 5 | `HORA_INICIO` | `TIME` | NULL | v579 | v579 |
| 6 | `HORA_FIM` | `TIME` | NULL | v579 | v579 |
| 7 | `DATA_INICIO` | `DATE` | NULL | v579 | v579 |
| 8 | `DATA_FIM` | `DATE` | NULL | v579 | v579 |
| 9 | `FATOR_EFICIENCIA` | `DOUBLE PRECISION` | NULL | v579 | v579 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v579 | v579 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 10 colunas |

