---
table: PRODUTO_EQUIPAMENTO
module: estoque
created_at_version: 198
last_modified_version: 198
target_version: 1468
columns_count: 3
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_EQUIPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 198;
- **Última mudança:** UPDATE 198;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | v198 | v198 |
| 2 | `CODEQUIPAMENTO` | `INTEGER` | NOT NULL | v198 | v198 |
| 3 | `MINUTOS` | `INTEGER` | NULL | v198 | v198 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 198 | CREATE | CREATE TABLE com 3 colunas |

