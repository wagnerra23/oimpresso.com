---
table: NF_ENTRADA_DESPESA
module: nfe
created_at_version: 976
last_modified_version: 978
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_DESPESA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 976;
- **Última mudança:** UPDATE 978;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | v976 | v976 |
| 2 | `CODFINANCEIRO` | `INTEGER` | NOT NULL | v976 | v976 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v976 | v976 |
| 4 | `OBSERVACAO` | `VARCHAR(600)` | NULL | v976 | v976 |
| 5 | `TOTAL` | `DOUBLE PRECISION` | NULL | v978 | v978 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v978 | v978 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 976 | CREATE | CREATE TABLE com 4 colunas |
| 978 | ADD_COL | + TOTAL DOUBLE PRECISION |
| 978 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

