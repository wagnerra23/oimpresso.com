---
table: CUSTO_ADICIONAL
module: financeiro
created_at_version: 579
last_modified_version: 672
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CUSTO_ADICIONAL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 672;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v579 | v579 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v579 | v579 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v579 | v579 |
| 4 | `CLASSIFICACAO` | `VARCHAR(20)` | NULL |  | v593 | v593 |
| 5 | `APLICAR_ANTES_MARGEM` | `DOM_BOOLEAN` | NULL |  | v616 | v616 |
| 6 | `ATIVO` | `DOM_ATIVO` | NULL |  | v672 | v672 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 3 colunas |
| 593 | ADD_COL | + CLASSIFICACAO VARCHAR(20) |
| 616 | ADD_COL | + APLICAR_ANTES_MARGEM DOM_BOOLEAN |
| 672 | ADD_COL | + ATIVO DOM_ATIVO |

