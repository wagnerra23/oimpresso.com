---
table: PRODUCAO_ROTEIRO_ORGANOGRAMA
module: producao
created_at_version: 821
last_modified_version: 822
target_version: 1468
columns_count: 15
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_ROTEIRO_ORGANOGRAMA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 821;
- **Última mudança:** UPDATE 822;
- **Total colunas (versão 1468):** 15

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v821 | v821 |
| 2 | `CODPRODUCAO_ROTEIRO` | `INTEGER` | NOT NULL | v821 | v821 |
| 3 | `PARENT` | `INTEGER` | NULL | v821 | v821 |
| 4 | `DESCRICAO` | `VARCHAR(150)` | NULL | v821 | v821 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v821 | v821 |
| 6 | `RESPONSAVEL` | `VARCHAR(150)` | NULL | v821 | v821 |
| 7 | `WIDTH` | `INTEGER` | NULL | v821 | v821 |
| 8 | `HEIGHT` | `INTEGER` | NULL | v821 | v821 |
| 9 | `TIPO` | `VARCHAR(20)` | NULL | v821 | v821 |
| 10 | `COR` | `INTEGER` | NULL | v821 | v821 |
| 11 | `IMAGEM` | `INTEGER` | NULL | v821 | v821 |
| 12 | `IMAGEM_ALINHAMENTO` | `VARCHAR(20)` | NULL | v821 | v821 |
| 13 | `ORDEM` | `INTEGER` | NULL | v821 | v821 |
| 14 | `ALINHAMENTO` | `VARCHAR(20)` | NULL | v821 | v821 |
| 15 | `CODPRODUCAO_ROTEIRO_PERGUNTA` | `INTEGER` | NULL | v821 | v822 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 821 | CREATE | CREATE TABLE com 15 colunas |
| 822 | RENAME_COL | × CODPRODUCAO_PERGUNTA → CODPRODUCAO_ROTEIRO_PERGUNTA |

