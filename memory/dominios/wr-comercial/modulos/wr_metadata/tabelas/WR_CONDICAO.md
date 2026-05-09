---
table: WR_CONDICAO
module: wr_metadata
created_at_version: 1345
last_modified_version: 1345
target_version: 1468
columns_count: 10
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_CONDICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1345;
- **Última mudança:** UPDATE 1345;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1345 | v1345 |
| 2 | `CONDICAO` | `VARCHAR(500)` | NULL |  | v1345 | v1345 |
| 3 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 4 | `funcao` | `VARCHAR(255)` | NULL |  | v1345 | v1345 |
| 5 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1345 | v1345 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1345 | v1345 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 8 | `COR` | `INTEGER` | NULL |  | v1345 | v1345 |
| 9 | `PODE_CONFIRMAR` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 10 | `LEGENDA` | `VARCHAR(150)` | NULL |  | v1345 | v1345 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1345 | CREATE | CREATE TABLE com 10 colunas |

