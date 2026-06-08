---
table: CONFIGURACAO_ACOES
module: configuracao
created_at_version: 945
last_modified_version: 1046
target_version: 1468
columns_count: 11
foreign_keys_count: 1
foreign_keys:
  CODCONFIGURACAO_FORM: CONFIGURACAO_FORM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_ACOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 945;
- **Última mudança:** UPDATE 1046;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONFIGURACAO_FORM` | [`CONFIGURACAO_FORM`](../../configuracao/tabelas/CONFIGURACAO_FORM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v945 | v945 |
| 2 | `OBRIGATORIO` | `VARCHAR(1)` | NULL |  | v903 | v903 |
| 3 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v945 | v945 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v945 | v945 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v945 | v945 |
| 6 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v945 | v945 |
| 7 | `CONDICAO` | `VARCHAR(500)` | NULL |  | v903 | v946 |
| 8 | `CODCONFIGURACAO_FORM` | `INTEGER, ADD DESCRICAO VARCHAR(50), ADD DT_ALTERACAO TIMESTAMP, ADD ATIVO VARCHAR(1)` | NULL | → `CONFIGURACAO_FORM` | v946 | v946 |
| 9 | `COR` | `INTEGER` | NULL |  | v950 | v950 |
| 10 | `PODE_CONFIRMAR` | `VARCHAR(1)` | NULL |  | v952 | v952 |
| 11 | `LEGENDA` | `VARCHAR(150)` | NULL |  | v1046 | v1046 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 903 | CREATE | CREATE TABLE com 9 colunas |
| 945 | CREATE | CREATE TABLE com 4 colunas |
| 945 | ADD_COL | + TEM_PADRAO VARCHAR(1) |
| 946 | RENAME_COL | × OBRIGATORIO_CONDICAO → CONDICAO |
| 946 | ADD_COL | + CODCONFIGURACAO_FORM INTEGER, ADD DESCRICAO VARCHAR(50), ADD DT_ALTERACAO TIMESTAMP, ADD ATIVO VARCHAR(1) |
| 946 | DROP_COL | - ENABLE |
| 946 | DROP_COL | - ENABLE_CONDICAO |
| 946 | DROP_COL | - VISIBLE |
| 946 | DROP_COL | - VISIBLE_CONDICAO |
| 946 | DROP_COL | - CODCONFIGURACAO_COMPONENTE |
| 946 | DROP_COL | - CODCONFIGURACAO_REGRA |
| 950 | ADD_COL | + COR INTEGER |
| 952 | ADD_COL | + PODE_CONFIRMAR VARCHAR(1) |
| 1046 | ADD_COL | + LEGENDA VARCHAR(150) |

