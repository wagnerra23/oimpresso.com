---
table: PRODUCAO_SITUACAO
module: producao
created_at_version: 810
last_modified_version: 1098
target_version: 1468
columns_count: 11
foreign_keys_count: 1
foreign_keys:
  CODPRODUCAO_ACAO: PRODUCAO_ACAO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_SITUACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 810;
- **Última mudança:** UPDATE 1098;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUCAO_ACAO` | [`PRODUCAO_ACAO`](../../producao/tabelas/PRODUCAO_ACAO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v810 | v810 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v810 | v810 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v810 | v810 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v810 | v810 |
| 5 | `COR` | `INTEGER` | NULL |  | v810 | v810 |
| 6 | `TEM_OBSERVACAO` | `VARCHAR(1)` | NULL |  | v921 | v921 |
| 7 | `CODPRODUCAO_ACAO` | `INTEGER` | NULL | → `PRODUCAO_ACAO` | v1039 | v1039 |
| 8 | `TEM_PRODUCAO_MOTIVO` | `VARCHAR(1)` | NULL |  | v1051 | v1051 |
| 9 | `ESTILO` | `VARCHAR(50)` | NULL |  | v1087 | v1087 |
| 10 | `FILA` | `INTEGER` | NULL |  | v1098 | v1098 |
| 11 | `ICO` | `INTEGER` | NULL |  | v1098 | v1098 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 810 | CREATE | CREATE TABLE com 6 colunas |
| 810 | CREATE | CREATE TABLE com 7 colunas |
| 810 | ADD_COL | + PODE_ARQUIVAR VARCHAR(1), ADD PODE_FINALIZAR VARCHAR(1), ADD PODE_APROVAR VARCHAR(1), ADD PODE_PLAY VARCHAR(1), ADD PODE_PAUSAR VARCHAR(1), ADD PODE_INATIVAR VARCHAR(1) |
| 810 | ADD_COL | + COR INTEGER |
| 894 | ADD_COL | + TEM_TRABALHANDO VARCHAR(1) |
| 894 | RENAME_COL | × PODE_ARQUIVAR → TEM_ARQUIVAR |
| 894 | RENAME_COL | × PODE_FINALIZAR → TEM_FINALIZAR |
| 894 | RENAME_COL | × PODE_APROVAR → TEM_APROVAR |
| 894 | RENAME_COL | × PODE_PLAY → TEM_PLAY |
| 894 | RENAME_COL | × PODE_PAUSAR → TEM_PAUSAR |
| 895 | RENAME_COL | × PODE_INATIVAR → TEM_INATIVAR |
| 897 | RENAME_COL | × TEM_ARQUIVAR → TEM_ARQUIVADO |
| 907 | ADD_COL | + CODUSUARIO INTEGER |
| 921 | ADD_COL | + TEM_OBSERVACAO VARCHAR(1) |
| 922 | ADD_COL | + MENSAGEM_HISTORICO VARCHAR(200) |
| 1039 | ADD_COL | + CODPRODUCAO_ACAO INTEGER |
| 1039 | DROP_COL | - TEM_EMAIL |
| 1039 | DROP_COL | - CODEMAIL_MODELO |
| 1039 | DROP_COL | - TEM_ARQUIVADO |
| 1039 | DROP_COL | - TEM_FINALIZAR |
| 1039 | DROP_COL | - TEM_PLAY |
| 1039 | DROP_COL | - TEM_PAUSAR |
| 1039 | DROP_COL | - TEM_INATIVAR |
| 1039 | DROP_COL | - TEM_TRABALHANDO |
| 1039 | DROP_COL | - CODUSUARIO |
| 1039 | DROP_COL | - MENSAGEM_HISTORICO |
| 1051 | ADD_COL | + TEM_PRODUCAO_MOTIVO VARCHAR(1) |
| 1087 | ADD_COL | + ESTILO VARCHAR(50) |
| 1098 | ADD_COL | + FILA INTEGER |
| 1098 | ADD_COL | + ICO INTEGER |

