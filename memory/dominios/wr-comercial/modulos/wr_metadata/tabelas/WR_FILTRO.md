---
table: WR_FILTRO
module: wr_metadata
created_at_version: 1324
last_modified_version: 1430
target_version: 1468
columns_count: 24
foreign_keys_count: 2
foreign_keys:
  CODWR_APP: WR_APP
  CODWR_FORM: WR_FORM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_FILTRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1324;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 24

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_APP` | [`WR_APP`](../../wr_metadata/tabelas/WR_APP.md) |
| `CODWR_FORM` | [`WR_FORM`](../../wr_metadata/tabelas/WR_FORM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1324 | v1324 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1324 | v1324 |
| 3 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1324 | v1324 |
| 4 | `CODWR_FORM` | `INTEGER` | NULL | → `WR_FORM` | v1324 | v1324 |
| 5 | `CODWR_APP` | `INTEGER` | NULL | → `WR_APP` | v1324 | v1324 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1324 | v1324 |
| 8 | `ORDEM` | `DOUBLE PRECISION` | NULL |  | v1324 | v1324 |
| 9 | `PODE_APARECER_PARA_TODOS` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 10 | `SQLWHERE` | `VARCHAR(500)` | NULL |  | v1324 | v1324 |
| 11 | `TEM_AUTOCHECK` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 12 | `TEM_RADIOITEM` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 13 | `GROUPINDEX` | `INTEGER` | NULL |  | v1324 | v1324 |
| 14 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 15 | `TEM_PERIODO` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 16 | `TEM_QUANT_REGISTROS` | `VARCHAR(1)` | NULL |  | v1324 | v1324 |
| 17 | `NOME` | `VARCHAR(100)` | NULL |  | v1324 | v1324 |
| 18 | `SQL` | `VARCHAR(500)` | NULL |  | v1324 | v1324 |
| 19 | `CAMPO` | `VARCHAR(100)` | NULL |  | v1324 | v1324 |
| 20 | `FORMATO` | `VARCHAR(50)` | NULL |  | v1324 | v1324 |
| 21 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v1324 | v1324 |
| 22 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v1324 | v1324 |
| 23 | `PERIODO` | `VARCHAR(20)` | NULL |  | v1324 | v1324 |
| 24 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1324 | CREATE | CREATE TABLE com 23 colunas |
| 1430 | ADD_COL | + PATH VARCHAR(255) |

