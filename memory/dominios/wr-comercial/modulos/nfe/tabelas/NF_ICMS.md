---
table: NF_ICMS
module: nfe
created_at_version: 1463
last_modified_version: 1463
target_version: 1468
columns_count: 1
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ICMS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1463;
- **Última mudança:** UPDATE 1463;
- **Total colunas (versão 1468):** 1

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `PFCP` | `DOUBLE PRECISION` | NULL |  | v1463 | v1463 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1463 | ADD_COL | + PFCP DOUBLE PRECISION |

