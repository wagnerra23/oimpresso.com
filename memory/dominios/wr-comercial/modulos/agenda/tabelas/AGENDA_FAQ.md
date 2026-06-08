---
table: AGENDA_FAQ
module: agenda
created_at_version: 12
last_modified_version: 419
target_version: 1468
columns_count: 21
foreign_keys_count: 1
foreign_keys:
  CODAGENDA_TITULO: AGENDA_TITULO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_FAQ`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 419;
- **Total colunas (versão 1468):** 21

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODAGENDA_TITULO` | [`AGENDA_TITULO`](../../agenda/tabelas/AGENDA_TITULO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(15)` | NOT NULL |  | v12 | v12 |
| 2 | `DESCRICAO` | `VARCHAR(600)` | NULL |  | v12 | v12 |
| 3 | `RESPOSTA` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v12 | v12 |
| 4 | `CODAGENDA_TITULO` | `INTEGER` | NULL | → `AGENDA_TITULO` | v12 | v12 |
| 5 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v12 | v12 |
| 6 | `TIPO` | `VARCHAR(1)` | NULL |  | v12 | v12 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v12 | v12 |
| 8 | `INDICE1` | `INTEGER` | NULL |  | v12 | v12 |
| 9 | `INDICE2` | `INTEGER` | NULL |  | v12 | v12 |
| 10 | `INDICE3` | `INTEGER` | NULL |  | v12 | v12 |
| 11 | `INDICE4` | `INTEGER` | NULL |  | v12 | v12 |
| 12 | `DATA` | `TIMESTAMP` | NULL |  | v13 | v13 |
| 13 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v409 | v409 |
| 14 | `CODIGO_ERRO_NFE` | `VARCHAR(10)` | NULL |  | v409 | v409 |
| 15 | `SEQUENCIAL` | `VARCHAR(15)` | NULL |  | v409 | v409 |
| 16 | `MODELO_NFE` | `VARCHAR(10)` | NULL |  | v409 | v409 |
| 17 | `APLIC` | `VARCHAR(10)` | NULL |  | v409 | v409 |
| 18 | `EFEITO` | `VARCHAR(10)` | NULL |  | v409 | v409 |
| 19 | `TAG` | `VARCHAR(255)` | NULL |  | v409 | v409 |
| 20 | `LINK` | `VARCHAR(255)` | NULL |  | v409 | v409 |
| 21 | `CODIGO_RETORNO_NFE` | `VARCHAR(10)` | NULL |  | v419 | v419 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | CREATE | CREATE TABLE com 11 colunas |
| 13 | ADD_COL | + DATA TIMESTAMP |
| 399 | ADD_COL | + DT_ALTERACAO timestamp |
| 409 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 409 | ADD_COL | + CODIGO_ERRO_NFE VARCHAR(10) |
| 409 | ADD_COL | + SEQUENCIAL VARCHAR(15) |
| 409 | ADD_COL | + MODELO_NFE VARCHAR(10) |
| 409 | ADD_COL | + APLIC VARCHAR(10) |
| 409 | ADD_COL | + EFEITO VARCHAR(10) |
| 409 | ADD_COL | + TAG VARCHAR(255) |
| 409 | ADD_COL | + LINK VARCHAR(255) |
| 419 | ADD_COL | + CODIGO_RETORNO_NFE VARCHAR(10) |

