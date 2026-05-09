---
table: PRODUCAO_ACAO
module: producao
created_at_version: 1030
last_modified_version: 1100
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_ACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1030;
- **Última mudança:** UPDATE 1100;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1030 | v1030 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1030 | v1030 |
| 3 | `ICONE` | `INTEGER` | NULL |  | v1030 | v1030 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1030 | v1030 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1030 | v1030 |
| 6 | `TEM_ARQUIVADO` | `VARCHAR(1), add TEM_FINALIZAR VARCHAR(1), add TEM_APROVAR VARCHAR(1), add TEM_PLAY VARCHAR(1), add TEM_PAUSAR VARCHAR(1), add TEM_INATIVAR VARCHAR(1), add TEM_TRABALHANDO VARCHAR(1), add TEM_EMAIL VARCHAR(1), add CODEMAIL_MODELO INTEGER, add CODUSUARIO INTEGER, add TEM_OBSERVACAO VARCHAR(1), add MENSAGEM_HISTORICO VARCHAR(200)` | NULL |  | v1038 | v1038 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1030 | CREATE | CREATE TABLE com 5 colunas |
| 1038 | ADD_COL | + TEM_ARQUIVADO VARCHAR(1), add TEM_FINALIZAR VARCHAR(1), add TEM_APROVAR VARCHAR(1), add TEM_PLAY VARCHAR(1), add TEM_PAUSAR VARCHAR(1), add TEM_INATIVAR VARCHAR(1), add TEM_TRABALHANDO VARCHAR(1), add TEM_EMAIL VARCHAR(1), add CODEMAIL_MODELO INTEGER, add CODUSUARIO INTEGER, add TEM_OBSERVACAO VARCHAR(1), add MENSAGEM_HISTORICO VARCHAR(200) |
| 1082 | ADD_COL | + TEM_FILA VARCHAR(1) |
| 1093 | ADD_COL | + EVENTO INTEGER |
| 1094 | RENAME_COL | × EVENTO → FILA |
| 1098 | DROP_COL | - FILA |
| 1098 | DROP_COL | - TEM_FILA |
| 1100 | RENAME_COL | × EVENTO → FILA |

