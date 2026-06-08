---
table: REPRESENTANTE
module: cadastros
created_at_version: 110
last_modified_version: 186
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `REPRESENTANTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 110;
- **Última mudança:** UPDATE 186;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CRT` | `VARCHAR(50)` | NULL |  | v110 | v110 |
| 2 | `COMISSAO_POR_VENDA` | `varchar(1)` | NULL |  | v186 | v186 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 110 | ADD_COL | + CRT VARCHAR(50) |
| 186 | ADD_COL | + COMISSAO_POR_VENDA varchar(1) |

