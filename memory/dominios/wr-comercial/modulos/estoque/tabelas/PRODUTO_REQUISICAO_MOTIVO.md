---
table: PRODUTO_REQUISICAO_MOTIVO
module: estoque
created_at_version: 1263
last_modified_version: 1263
target_version: 1468
columns_count: 11
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_REQUISICAO_MOTIVO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1263;
- **Última mudança:** UPDATE 1263;
- **Total colunas (versão 1468):** 11

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1263 | v1263 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1263 | v1263 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1263 | v1263 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1263 | v1263 |
| 5 | `COR` | `INTEGER` | NULL |  | v1263 | v1263 |
| 6 | `TEM_OBSERVACAO` | `VARCHAR(1)` | NULL |  | v1263 | v1263 |
| 7 | `ESTILO` | `VARCHAR(50)` | NULL |  | v1263 | v1263 |
| 8 | `FILA` | `INTEGER` | NULL |  | v1263 | v1263 |
| 9 | `ICO` | `INTEGER` | NULL |  | v1263 | v1263 |
| 10 | `TIPO_MOVIMENTACAO` | `VARCHAR(1)` | NULL |  | v1263 | v1263 |
| 11 | `FORM` | `VARCHAR(100)` | NULL |  | v1263 | v1263 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1263 | CREATE | CREATE TABLE com 11 colunas |

