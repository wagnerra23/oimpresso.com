---
table: CONFIGURACAO_COMPONENTE
module: configuracao
created_at_version: 797
last_modified_version: 1044
target_version: 1468
columns_count: 29
foreign_keys_count: 1
foreign_keys:
  CODCONFIGURACAO_FORM: CONFIGURACAO_FORM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_COMPONENTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 797;
- **Última mudança:** UPDATE 1044;
- **Total colunas (versão 1468):** 29

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONFIGURACAO_FORM` | [`CONFIGURACAO_FORM`](../../configuracao/tabelas/CONFIGURACAO_FORM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v797 | v797 |
| 2 | `COMPONENTE` | `VARCHAR(500)` | NULL |  | v797 | v797 |
| 3 | `TABELA` | `VARCHAR(255)` | NULL |  | v797 | v797 |
| 4 | `CAMPO` | `VARCHAR(100)` | NULL |  | v797 | v797 |
| 5 | `FORMATACAO` | `VARCHAR(40)` | NULL |  | v797 | v797 |
| 6 | `CAPTION` | `VARCHAR(255)` | NULL |  | v797 | v797 |
| 7 | `HINT` | `VARCHAR(5000)` | NULL |  | v797 | v797 |
| 8 | `OBSERVACAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v797 | v797 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v797 | v797 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v797 | v797 |
| 11 | `TAB` | `VARCHAR(500)` | NULL |  | v802 | v802 |
| 12 | `CSS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v810 | v810 |
| 13 | `TEM_NA_CONSULTA` | `VARCHAR(1)` | NULL |  | v810 | v810 |
| 14 | `POSSUI_PK` | `VARCHAR(1)` | NULL |  | v885 | v885 |
| 15 | `OBRIGATORIO` | `VARCHAR(1)` | NULL |  | v1044 | v1044 |
| 16 | `VALOR_INICIAL` | `VARCHAR(500)` | NULL |  | v885 | v885 |
| 17 | `POSSUI_UNIQUE` | `VARCHAR(1)` | NULL |  | v885 | v885 |
| 18 | `TIPO_COMPONENTE` | `VARCHAR(500)` | NULL |  | v885 | v885 |
| 19 | `ACAO` | `VARCHAR(500)` | NULL |  | v885 | v885 |
| 20 | `POSSUI_FK` | `VARCHAR(1)` | NULL |  | v885 | v885 |
| 21 | `GEN` | `VARCHAR(1)` | NULL |  | v885 | v885 |
| 22 | `MULTEMPRESA` | `VARCHAR(1), ADD VALOR_INICIAL VARCHAR(500), ADD ACAO VARCHAR(100)` | NULL |  | v1044 | v1044 |
| 23 | `OBRIGATORIO_EXPRESSAO` | `VARCHAR(500)` | NULL |  | v886 | v886 |
| 24 | `IMPEDIR_DUPLICIDADE` | `VARCHAR(1)` | NULL |  | v886 | v886 |
| 25 | `SQLCAMPOSADICIONAIS` | `VARCHAR(500)` | NULL |  | v893 | v893 |
| 26 | `CODCONFIGURACAO_FORM` | `INTEGER` | NULL | → `CONFIGURACAO_FORM` | v902 | v902 |
| 27 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v1041 | v1041 |
| 28 | `IMPEDIR_DUPLICIDADE_SQL` | `VARCHAR(1000)` | NULL |  | v964 | v964 |
| 29 | `TEM_CAPTIONOUHINT` | `VARCHAR(1)` | NULL |  | v965 | v965 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 797 | CREATE | CREATE TABLE com 12 colunas |
| 802 | ADD_COL | + TAB VARCHAR(500) |
| 810 | ADD_COL | + CSS BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 810 | ADD_COL | + TEM_NA_CONSULTA VARCHAR(1) |
| 885 | ADD_COL | + POSSUI_PK VARCHAR(1) |
| 885 | ADD_COL | + OBRIGATORIO VARCHAR(1) |
| 885 | ADD_COL | + VALOR_INICIAL VARCHAR(500) |
| 885 | ADD_COL | + POSSUI_UNIQUE VARCHAR(1) |
| 885 | ADD_COL | + TIPO_COMPONENTE VARCHAR(500) |
| 885 | ADD_COL | + ACAO VARCHAR(500) |
| 885 | ADD_COL | + POSSUI_FK VARCHAR(1) |
| 885 | ADD_COL | + GEN VARCHAR(1) |
| 885 | ADD_COL | + MULTEMPRESA VARCHAR(1) |
| 885 | ADD_COL | + MUDOU_CAPTION_HINT VARCHAR(1) |
| 886 | ADD_COL | + OBRIGATORIO_EXPRESSAO VARCHAR(500) |
| 886 | ADD_COL | + IMPEDIR_DUPLICIDADE VARCHAR(1) |
| 893 | ADD_COL | + SQLCAMPOSADICIONAIS VARCHAR(500) |
| 902 | ADD_COL | + CODCONFIGURACAO_FORM INTEGER |
| 902 | DROP_COL | - FORM |
| 902 | RENAME_COL | × PADRAO → TEM_PADRAO |
| 964 | ADD_COL | + IMPEDIR_DUPLICIDADE_SQL VARCHAR(1000) |
| 965 | ADD_COL | + TEM_CAPTIONOUHINT VARCHAR(1) |
| 965 | DROP_COL | - MUDOU_CAPTION_HINT |
| 1041 | ADD_COL | + TEM_PADRAO VARCHAR(1) |
| 1044 | ADD_COL | + MULTEMPRESA VARCHAR(1), ADD VALOR_INICIAL VARCHAR(500), ADD ACAO VARCHAR(100) |
| 1044 | ADD_COL | + OBRIGATORIO VARCHAR(1) |

