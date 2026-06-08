---
table: CONTRATO_TIPO
module: vendas
created_at_version: 499
last_modified_version: 1430
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONTRATO_TIPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 499;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1430 | v1430 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 1430 | ADD_COL | + ATIVO VARCHAR(1) |
| 1430 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

