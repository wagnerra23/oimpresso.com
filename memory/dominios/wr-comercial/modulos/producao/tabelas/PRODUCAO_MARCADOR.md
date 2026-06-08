---
table: PRODUCAO_MARCADOR
module: producao
created_at_version: 810
last_modified_version: 810
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_MARCADOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 810;
- **Última mudança:** UPDATE 810;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v810 | v810 |
| 2 | `DESCRICAO` | `VARCHAR(50) CHARACTER SET NONE` | NULL |  | v810 | v810 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v810 | v810 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v810 | v810 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 810 | CREATE | CREATE TABLE com 4 colunas |

