---
table: AGENDA_TITULO_WORKFLOW
module: agenda
created_at_version: 100
last_modified_version: 105
target_version: 1468
columns_count: 17
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_TITULO_WORKFLOW`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 100;
- **Última mudança:** UPDATE 105;
- **Total colunas (versão 1468):** 17

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v100 | v100 |
| 2 | `CODAGENDA_TITULO` | `INTEGER` | NOT NULL | v100 | v100 |
| 3 | `PARENT` | `INTEGER` | NULL | v100 | v100 |
| 4 | `LARGURA` | `INTEGER` | NULL | v100 | v100 |
| 5 | `ALTURA` | `INTEGER` | NULL | v100 | v100 |
| 6 | `TIPO` | `INTEGER` | NULL | v100 | v100 |
| 7 | `COR` | `INTEGER` | NULL | v100 | v100 |
| 8 | `ORDEM` | `INTEGER` | NULL | v100 | v100 |
| 9 | `ALINHAMENTO` | `INTEGER` | NULL | v100 | v100 |
| 10 | `CAMPO_NOME` | `VARCHAR(20)` | NULL | v100 | v100 |
| 11 | `CAMPO_TIPO` | `INTEGER` | NULL | v100 | v100 |
| 12 | `CAMPO_TAMANHO` | `INTEGER` | NULL | v100 | v100 |
| 13 | `CAMPO_DESCRICAO` | `VARCHAR(20)` | NULL | v100 | v100 |
| 14 | `HINT` | `VARCHAR(100)` | NULL | v100 | v100 |
| 15 | `PESQUISA_TABELA` | `VARCHAR(20)` | NULL | v100 | v100 |
| 16 | `PERGUNTA` | `VARCHAR(150)` | NULL | v100 | v100 |
| 17 | `SOMENTE_LEITURA` | `INTEGER` | NULL | v105 | v105 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 100 | CREATE | CREATE TABLE com 16 colunas |
| 105 | ADD_COL | + SOMENTE_LEITURA INTEGER |

