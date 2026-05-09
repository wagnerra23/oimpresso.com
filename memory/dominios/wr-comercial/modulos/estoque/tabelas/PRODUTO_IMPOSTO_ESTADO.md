---
table: PRODUTO_IMPOSTO_ESTADO
module: estoque
created_at_version: 86
last_modified_version: 86
target_version: 1468
columns_count: 4
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_IMPOSTO_ESTADO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 86;
- **Última mudança:** UPDATE 86;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | v86 | v86 |
| 2 | `ESTADO` | `VARCHAR(2)` | NOT NULL | v86 | v86 |
| 3 | `MVA` | `DOUBLE PRECISION` | NULL | v86 | v86 |
| 4 | `PAUTA_PRECO` | `DOUBLE PRECISION` | NULL | v86 | v86 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 86 | CREATE | CREATE TABLE com 4 colunas |

