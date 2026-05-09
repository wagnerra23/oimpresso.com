---
table: WR_OBRIGATORIO
module: wr_metadata
created_at_version: 1345
last_modified_version: 1345
target_version: 1468
columns_count: 8
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_OBRIGATORIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1345;
- **Última mudança:** UPDATE 1345;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1345 | v1345 |
| 2 | `DESCRICAO` | `VARCHAR(500)` | NULL | v1345 | v1345 |
| 3 | `HINT` | `VARCHAR(1000)` | NULL | v1345 | v1345 |
| 4 | `CODWR_CONDICAO` | `INTEGER` | NULL | v1345 | v1345 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL | v1345 | v1345 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1345 | v1345 |
| 7 | `TEM_PADRAO` | `VARCHAR(1)` | NULL | v1345 | v1345 |
| 8 | `CODWR_COMPONENTE` | `INTEGER` | NULL | v1345 | v1345 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1345 | CREATE | CREATE TABLE com 8 colunas |

