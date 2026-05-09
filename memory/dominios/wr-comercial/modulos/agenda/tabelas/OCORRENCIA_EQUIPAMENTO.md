---
table: OCORRENCIA_EQUIPAMENTO
module: agenda
created_at_version: 576
last_modified_version: 576
target_version: 1468
columns_count: 1
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `OCORRENCIA_EQUIPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 576;
- **Última mudança:** UPDATE 576;
- **Total colunas (versão 1468):** 1

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `MONTA` | `VARCHAR(15)` | NULL |  | v576 | v576 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 576 | ADD_COL | + MONTA VARCHAR(15) |

