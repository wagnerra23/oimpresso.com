---
table: CONFIGURACAO_REGRA
module: configuracao
created_at_version: 797
last_modified_version: 797
target_version: 1468
columns_count: 8
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_REGRA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 797;
- **Última mudança:** UPDATE 797;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v797 | v797 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v797 | v797 |
| 3 | `regra` | `VARCHAR(500)` | NULL |  | v797 | v797 |
| 4 | `ESTILO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v797 | v797 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v797 | v797 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v797 | v797 |
| 7 | `FORM` | `VARCHAR(255)` | NULL |  | v797 | v797 |
| 8 | `GRID` | `VARCHAR(255)` | NULL |  | v797 | v797 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 797 | CREATE | CREATE TABLE com 8 colunas |

