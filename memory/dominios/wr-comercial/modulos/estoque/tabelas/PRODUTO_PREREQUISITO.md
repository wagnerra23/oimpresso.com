---
table: PRODUTO_PREREQUISITO
module: estoque
created_at_version: 1230
last_modified_version: 1230
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_PREREQUISITO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1230;
- **Última mudança:** UPDATE 1230;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1230 | v1230 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1230 | v1230 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL | v1230 | v1230 |
| 4 | `CODPRODUTO` | `VARCHAR(15)` | NULL | v1230 | v1230 |
| 5 | `CODPRODUTO_ETAPA` | `INTEGER` | NULL | v1230 | v1230 |
| 6 | `CODPRODUTO_ETAPA_PREREQUISITO` | `INTEGER` | NULL | v1230 | v1230 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1230 | CREATE | CREATE TABLE com 6 colunas |

