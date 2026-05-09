---
table: CONFIGURACAO_AGRUPAMENTO
module: configuracao
created_at_version: 812
last_modified_version: 925
target_version: 1468
columns_count: 11
foreign_keys_count: 1
foreign_keys:
  CODCONFIGURACAO_FILTRO: CONFIGURACAO_FILTRO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_AGRUPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 812;
- **Última mudança:** UPDATE 925;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONFIGURACAO_FILTRO` | [`CONFIGURACAO_FILTRO`](../../configuracao/tabelas/CONFIGURACAO_FILTRO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v812 | v812 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v812 | v812 |
| 3 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v812 | v812 |
| 4 | `FORM` | `VARCHAR(500)` | NULL |  | v812 | v812 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v812 | v812 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v812 | v812 |
| 7 | `ORDEM` | `DOUBLE PRECISION` | NULL |  | v812 | v812 |
| 8 | `PODE_APARECER_PARA_TODOS` | `VARCHAR(1)` | NULL |  | v812 | v812 |
| 9 | `CODCONFIGURACAO_FILTRO` | `INTEGER` | NULL | → `CONFIGURACAO_FILTRO` | v814 | v814 |
| 10 | `CAMPO1` | `VARCHAR(255), ADD ORDEM1 VARCHAR(15), ADD CAMPO2 VARCHAR(255), ADD ORDEM2 VARCHAR(15), ADD CAMPO3 VARCHAR(255), ADD ORDEM3 VARCHAR(15)` | NULL |  | v814 | v814 |
| 11 | `TEM_AUTOCHECK` | `VARCHAR(1), ADD TEM_RADIOITEM VARCHAR(1), ADD GROUPINDEX INTEGER` | NULL |  | v814 | v814 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 812 | CREATE | CREATE TABLE com 6 colunas |
| 812 | ADD_COL | + ORDEM DOUBLE PRECISION |
| 812 | ADD_COL | + PODE_APARECER_PARA_TODOS VARCHAR(1) |
| 814 | ADD_COL | + CODCONFIGURACAO_FILTRO INTEGER |
| 814 | ADD_COL | + CAMPOS VARCHAR(1000) |
| 814 | ADD_COL | + CAMPO1 VARCHAR(255), ADD ORDEM1 VARCHAR(15), ADD CAMPO2 VARCHAR(255), ADD ORDEM2 VARCHAR(15), ADD CAMPO3 VARCHAR(255), ADD ORDEM3 VARCHAR(15) |
| 814 | DROP_COL | - CAMPOS |
| 814 | ADD_COL | + TEM_AUTOCHECK VARCHAR(1), ADD TEM_RADIOITEM VARCHAR(1), ADD GROUPINDEX INTEGER |
| 916 | ADD_COL | + GRAFICO_TIPO VARCHAR(20) |
| 925 | DROP_COL | - GRAFICO_TIPO |

