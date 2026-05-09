---
table: PLANOCONTAS_DEPENDENTE
module: financeiro
created_at_version: 390
last_modified_version: 390
target_version: 1468
columns_count: 1
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PLANOCONTAS_DEPENDENTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 390;
- **Última mudança:** UPDATE 390;
- **Total colunas (versão 1468):** 1

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v390 | v390 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 390 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

