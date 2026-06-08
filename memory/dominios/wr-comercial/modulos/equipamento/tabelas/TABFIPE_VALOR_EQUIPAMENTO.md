---
table: TABFIPE_VALOR_EQUIPAMENTO
module: equipamento
created_at_version: 1456
last_modified_version: 1457
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TABFIPE_VALOR_EQUIPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1456;
- **Última mudança:** UPDATE 1457;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `VALOR_CONTRIBUICAO_ASSOCIADO` | `DOUBLE PRECISION` | NULL |  | v1456 | v1456 |
| 2 | `VALOR_BASE_EQUIPAMENTO` | `DOUBLE PRECISION` | NULL |  | v1457 | v1457 |
| 3 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1457 | v1457 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1456 | ADD_COL | + VALOR_CONTRIBUICAO_ASSOCIADO DOUBLE PRECISION |
| 1457 | ADD_COL | + VALOR_BASE_EQUIPAMENTO DOUBLE PRECISION |
| 1457 | ADD_COL | + OBSERVACAO VARCHAR(500) |

