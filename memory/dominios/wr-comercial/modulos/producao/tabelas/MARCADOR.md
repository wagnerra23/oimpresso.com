---
table: MARCADOR
module: producao
created_at_version: 390
last_modified_version: 390
target_version: 1468
columns_count: 13
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MARCADOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 390;
- **Última mudança:** UPDATE 390;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ARQUIVO_FR3` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v390 | v390 |
| 2 | `SPREADSHEET` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v390 | v390 |
| 3 | `META_DO_ALERTA` | `INTEGER` | NULL |  | v390 | v390 |
| 4 | `EMAIL_DO_ALERTA` | `VARCHAR(100)` | NULL |  | v390 | v390 |
| 5 | `QUANDO_ENVIAR_EMAIL_ALERTA` | `VARCHAR(20)` | NULL |  | v390 | v390 |
| 6 | `EVENTO_DO_ALERTA` | `VARCHAR(20)` | NULL |  | v390 | v390 |
| 7 | `ACAO_DO_ALERTA` | `VARCHAR(20)` | NULL |  | v390 | v390 |
| 8 | `MENSAGEM_DO_ALERTA` | `VARCHAR(32000)` | NULL |  | v390 | v390 |
| 9 | `METADOALERTA` | `INTEGER` | NULL |  | v390 | v390 |
| 10 | `EMAILDOALERTA` | `VARCHAR(100)` | NULL |  | v390 | v390 |
| 11 | `DT_ULTIMA_EXECUCAO` | `TIMESTAMP` | NULL |  | v390 | v390 |
| 12 | `ITERACAO_COM_PESSOAS` | `VARCHAR(1)` | NULL |  | v390 | v390 |
| 13 | `CELULA_ITERACAO_COM_PESSOAS` | `VARCHAR(10)` | NULL |  | v390 | v390 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 390 | DROP_COL | - TITULO |
| 390 | DROP_COL | - ICONE |
| 390 | DROP_COL | - CODTABELA |
| 390 | DROP_COL | - TABELA |
| 390 | DROP_COL | - DESCRICAO |
| 390 | DROP_COL | - CODMARCADOR_LISTA |
| 390 | ADD_COL | + ARQUIVO_FR3 BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 390 | ADD_COL | + SPREADSHEET BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 390 | ADD_COL | + META_DO_ALERTA INTEGER |
| 390 | ADD_COL | + EMAIL_DO_ALERTA VARCHAR(100) |
| 390 | ADD_COL | + QUANDO_ENVIAR_EMAIL_ALERTA VARCHAR(20) |
| 390 | ADD_COL | + EVENTO_DO_ALERTA VARCHAR(20) |
| 390 | ADD_COL | + ACAO_DO_ALERTA VARCHAR(20) |
| 390 | ADD_COL | + MENSAGEM_DO_ALERTA VARCHAR(32000) |
| 390 | ADD_COL | + METADOALERTA INTEGER |
| 390 | ADD_COL | + EMAILDOALERTA VARCHAR(100) |
| 390 | ADD_COL | + DT_ULTIMA_EXECUCAO TIMESTAMP |
| 390 | ADD_COL | + ITERACAO_COM_PESSOAS VARCHAR(1) |
| 390 | ADD_COL | + EMAILDOALERTA VARCHAR(100) |
| 390 | ADD_COL | + CELULA_ITERACAO_COM_PESSOAS VARCHAR(10) |
| 390 | DROP_COL | - CODMARCADOR_SERVIDOR |

