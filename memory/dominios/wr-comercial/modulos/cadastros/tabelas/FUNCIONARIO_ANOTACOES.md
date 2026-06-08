---
table: FUNCIONARIO_ANOTACOES
module: cadastros
created_at_version: 174
last_modified_version: 174
target_version: 1468
columns_count: 1
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO_ANOTACOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 174;
- **Última mudança:** UPDATE 174;
- **Total colunas (versão 1468):** 1

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `timestamp` | NULL |  | v174 | v174 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 174 | ADD_COL | + DT_ALTERACAO timestamp |

