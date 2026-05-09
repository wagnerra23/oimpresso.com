---
table: DICA
module: ui_metadata
created_at_version: 728
last_modified_version: 728
target_version: 1468
columns_count: 20
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DICA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 728;
- **Última mudança:** UPDATE 728;
- **Total colunas (versão 1468):** 20

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v728 | v728 |
| 2 | `FORM` | `VARCHAR(100)` | NULL |  | v728 | v728 |
| 3 | `COMPONENTE` | `VARCHAR(200)` | NULL |  | v616 | v616 |
| 4 | `MSG_CABECALHO` | `VARCHAR(1000)` | NULL |  | v616 | v616 |
| 5 | `MSG_CORPO` | `VARCHAR(1000)` | NULL |  | v616 | v616 |
| 6 | `MSG_RODAPE` | `VARCHAR(1000)` | NULL |  | v616 | v616 |
| 7 | `USAR_HINT_CABECALHO` | `VARCHAR(1)` | NULL |  | v616 | v616 |
| 8 | `LARGURA` | `INTEGER` | NULL |  | v728 | v728 |
| 9 | `IMG_CABECALHO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v616 | v616 |
| 10 | `IMG_CORPO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v616 | v616 |
| 11 | `IMG_RODAPE` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v616 | v616 |
| 12 | `IMG_CABECALHO_FW` | `VARCHAR(1)` | NULL |  | v616 | v616 |
| 13 | `IMG_CORPO_FW` | `VARCHAR(1)` | NULL |  | v616 | v616 |
| 14 | `IMG_RODAPE_FW` | `VARCHAR(1)` | NULL |  | v616 | v616 |
| 15 | `SEQUENCIA` | `INTEGER` | NULL |  | v616 | v616 |
| 16 | `ATIVO` | `DOM_ATIVO` | NULL |  | v728 | v728 |
| 17 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v728 | v728 |
| 18 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 19 | `TIPO` | `VARCHAR(15)` | NULL |  | v728 | v728 |
| 20 | `LINK` | `VARCHAR(2000)` | NULL |  | v728 | v728 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 616 | CREATE | CREATE TABLE com 17 colunas |
| 728 | DROP_TABLE | DROP TABLE |
| 728 | CREATE | CREATE TABLE com 8 colunas |

