---
table: CONFIGURACAO_COMPONENTE_CSS
module: configuracao
created_at_version: 810
last_modified_version: 812
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODCONFIGURACAO_COMPONENTE: CONFIGURACAO_COMPONENTE
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_COMPONENTE_CSS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 810;
- **Última mudança:** UPDATE 812;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONFIGURACAO_COMPONENTE` | [`CONFIGURACAO_COMPONENTE`](../../configuracao/tabelas/CONFIGURACAO_COMPONENTE.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v810 | v810 |
| 2 | `CODCONFIGURACAO_COMPONENTE` | `INTEGER` | NULL | → `CONFIGURACAO_COMPONENTE` | v810 | v810 |
| 3 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v810 | v810 |
| 4 | `CSS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v810 | v810 |
| 5 | `COR` | `INTEGER` | NULL |  | v810 | v810 |
| 6 | `ORDEM` | `DOUBLE PRECISION` | NULL |  | v810 | v812 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 810 | CREATE | CREATE TABLE com 5 colunas |
| 810 | ADD_COL | + COR INTEGER |
| 812 | ALTER_TYPE | ~ ORDERBY TYPE DOUBLE PRECISION |
| 812 | RENAME_COL | × ORDERBY → ORDEM |

