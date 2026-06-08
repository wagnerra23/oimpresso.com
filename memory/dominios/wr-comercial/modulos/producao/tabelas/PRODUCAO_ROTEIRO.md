---
table: PRODUCAO_ROTEIRO
module: producao
created_at_version: 821
last_modified_version: 821
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_ROTEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 821;
- **Última mudança:** UPDATE 821;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v821 | v821 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v821 | v821 |
| 3 | `OBSERVACAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v821 | v821 |
| 4 | `IMAGEM` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v821 | v821 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v821 | v821 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v821 | v821 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 821 | CREATE | CREATE TABLE com 6 colunas |

