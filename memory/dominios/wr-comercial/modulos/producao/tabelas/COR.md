---
table: COR
module: producao
created_at_version: 732
last_modified_version: 736
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 732;
- **Última mudança:** UPDATE 736;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `timestamp` | NULL |  | v736 | v736 |
| 2 | `ATIVO` | `varchar(1)` | NULL |  | v736 | v736 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 732 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 732 | ADD_COL | + ATIVO VARCHAR(1) |
| 736 | ADD_COL | + DT_ALTERACAO timestamp |
| 736 | ADD_COL | + ATIVO varchar(1) |

