---
table: VENDA_SITUACAO
module: vendas
created_at_version: 329
last_modified_version: 1347
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_SITUACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 329;
- **Última mudança:** UPDATE 1347;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v329 | v329 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v329 | v329 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v329 | v329 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 5 | `TEM_FATURA` | `VARCHAR(1)` | NULL |  | v1347 | v1347 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 329 | CREATE | CREATE TABLE com 3 colunas |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 1347 | ADD_COL | + TEM_FATURA VARCHAR(1) |

