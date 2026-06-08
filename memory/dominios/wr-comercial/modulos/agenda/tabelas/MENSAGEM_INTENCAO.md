---
table: MENSAGEM_INTENCAO
module: agenda
created_at_version: 1286
last_modified_version: 1286
target_version: 1468
columns_count: 11
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM_INTENCAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1286;
- **Última mudança:** UPDATE 1286;
- **Total colunas (versão 1468):** 11

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1286 | v1286 |
| 2 | `DESCRICAO` | `VARCHAR(255)` | NULL |  | v1286 | v1286 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1286 | v1286 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1286 | v1286 |
| 5 | `CLASSIFICACAO` | `VARCHAR(18)` | NULL |  | v1286 | v1286 |
| 6 | `INDICE1` | `INTEGER` | NULL |  | v1286 | v1286 |
| 7 | `INDICE2` | `INTEGER` | NULL |  | v1286 | v1286 |
| 8 | `INDICE3` | `INTEGER` | NULL |  | v1286 | v1286 |
| 9 | `INDICE4` | `INTEGER` | NULL |  | v1286 | v1286 |
| 10 | `INDICE5` | `INTEGER` | NULL |  | v1286 | v1286 |
| 11 | `INDICE6` | `INTEGER` | NULL |  | v1286 | v1286 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1286 | CREATE | CREATE TABLE com 11 colunas |

