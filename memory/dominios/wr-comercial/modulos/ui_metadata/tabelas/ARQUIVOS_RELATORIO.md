---
table: ARQUIVOS_RELATORIO
module: ui_metadata
created_at_version: 624
last_modified_version: 1435
target_version: 1468
columns_count: 20
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `ARQUIVOS_RELATORIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 624;
- **Última mudança:** UPDATE 1435;
- **Total colunas (versão 1468):** 20

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v624 | v624 |
| 2 | `ATIVO` | `DOM_BOOLEAN` | NULL |  | v624 | v624 |
| 3 | `LINK` | `VARCHAR(500)` | NULL |  | v624 | v624 |
| 4 | `VERSAO` | `VARCHAR(30)` | NULL |  | v624 | v624 |
| 5 | `NATIVO` | `DOM_BOOLEAN` | NULL |  | v624 | v624 |
| 6 | `NOME_ORIGINAL` | `VARCHAR(255)` | NULL |  | v624 | v624 |
| 7 | `ARQUIVO_FR3` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v755 | v755 |
| 8 | `FORM` | `VARCHAR(255)` | NULL |  | v755 | v755 |
| 9 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v755 | v755 |
| 10 | `DESCRICAO` | `VARCHAR(255)` | NULL |  | v755 | v755 |
| 11 | `OBSERVACAO` | `VARCHAR(6000)` | NULL |  | v755 | v755 |
| 12 | `MD5` | `VARCHAR(50)` | NULL |  | v755 | v755 |
| 13 | `TAG_TELA` | `INTEGER` | NULL |  | v1267 | v1267 |
| 14 | `IS_CONSULTA` | `VARCHAR(1)` | NULL |  | v1267 | v1267 |
| 15 | `ID_INTERNO` | `VARCHAR(15)` | NULL |  | v701 | v701 |
| 16 | `CNPJ` | `varchar(18)` | NULL |  | v704 | v704 |
| 17 | `TAMANHO` | `INTEGER` | NULL |  | v768 | v768 |
| 18 | `TAG_APP` | `INTEGER` | NULL |  | v1267 | v1267 |
| 19 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |
| 20 | `PATH_APP` | `VARCHAR(255)` | NULL |  | v1435 | v1435 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 605 | CREATE | CREATE TABLE com 6 colunas |
| 605 | ADD_COL | + ARQUIVO_FR3 BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 605 | ADD_COL | + FORM VARCHAR(255) |
| 605 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 605 | ADD_COL | + DESCRICAO VARCHAR(255) |
| 605 | ADD_COL | + OBSERVACAO VARCHAR(6000) |
| 605 | ADD_COL | + MD5 VARCHAR(50) |
| 605 | ADD_COL | + TAG_TELA INTEGER |
| 605 | ADD_COL | + IS_CONSULTA VARCHAR(1) |
| 624 | CREATE | CREATE TABLE com 6 colunas |
| 662 | ADD_COL | + TAG_TELA INTEGER |
| 662 | ADD_COL | + IS_CONSULTA VARCHAR(1) |
| 701 | ADD_COL | + ID_INTERNO VARCHAR(15) |
| 704 | ADD_COL | + CNPJ varchar(18) |
| 755 | ADD_COL | + ARQUIVO_FR3 BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 755 | ADD_COL | + FORM VARCHAR(255) |
| 755 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 755 | ADD_COL | + DESCRICAO VARCHAR(255) |
| 755 | ADD_COL | + OBSERVACAO VARCHAR(6000) |
| 755 | ADD_COL | + MD5 VARCHAR(50) |
| 763 | ADD_COL | + TAMANHO INTEGER |
| 768 | ADD_COL | + TAMANHO INTEGER |
| 805 | ADD_COL | + TAG_TELA INTEGER |
| 805 | ADD_COL | + IS_CONSULTA VARCHAR(1) |
| 1267 | ADD_COL | + TAG_TELA INTEGER |
| 1267 | ADD_COL | + TAG_APP INTEGER |
| 1267 | ADD_COL | + IS_CONSULTA VARCHAR(1) |
| 1430 | ADD_COL | + PATH VARCHAR(255) |
| 1435 | ADD_COL | + PATH_APP VARCHAR(255) |

