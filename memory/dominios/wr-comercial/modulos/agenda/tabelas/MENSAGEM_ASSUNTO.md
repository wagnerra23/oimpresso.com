---
table: MENSAGEM_ASSUNTO
module: agenda
created_at_version: 1286
last_modified_version: 1441
target_version: 1468
columns_count: 14
foreign_keys_count: 3
foreign_keys:
  CODMENSAGEM_CONTATO: MENSAGEM_CONTATO
  CODMENSAGEM_INTENCAO: MENSAGEM_INTENCAO
  CODWR_FORM: WR_FORM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM_ASSUNTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1286;
- **Última mudança:** UPDATE 1441;
- **Total colunas (versão 1468):** 14

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODMENSAGEM_CONTATO` | [`MENSAGEM_CONTATO`](../../agenda/tabelas/MENSAGEM_CONTATO.md) |
| `CODMENSAGEM_INTENCAO` | [`MENSAGEM_INTENCAO`](../../agenda/tabelas/MENSAGEM_INTENCAO.md) |
| `CODWR_FORM` | [`WR_FORM`](../../wr_metadata/tabelas/WR_FORM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1286 | v1286 |
| 2 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1286 | v1286 |
| 3 | `TAG_APP` | `INTEGER` | NULL |  | v1286 | v1286 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1286 | v1286 |
| 5 | `CODMENSAGEM_CONTATO` | `INTEGER` | NULL | → `MENSAGEM_CONTATO` | v1286 | v1286 |
| 6 | `CODMENSAGEM_INTENCAO` | `INTEGER` | NULL | → `MENSAGEM_INTENCAO` | v1286 | v1286 |
| 7 | `MODULO` | `VARCHAR(255)` | NULL |  | v1286 | v1286 |
| 8 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1286 | v1286 |
| 9 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1286 | v1286 |
| 10 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1286 | v1286 |
| 11 | `TABELA` | `VARCHAR(255)` | NULL |  | v1286 | v1286 |
| 12 | `CODWR_FORM` | `INTEGER` | NULL | → `WR_FORM` | v1430 | v1430 |
| 13 | `DT_ALTERACAO1` | `TIMESTAMP` | NULL |  | v1431 | v1441 |
| 14 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1441 | v1441 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1286 | CREATE | CREATE TABLE com 7 colunas |
| 1286 | ADD_COL | + MODULO VARCHAR(255) |
| 1286 | ADD_COL | + CHAVE_PK1 INTEGER |
| 1286 | ADD_COL | + CHAVE_PK2 VARCHAR(40) |
| 1286 | ADD_COL | + CHAVE_PK3 VARCHAR(15) |
| 1286 | ADD_COL | + TABELA VARCHAR(255) |
| 1430 | ADD_COL | + CODWR_FORM INTEGER |
| 1431 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1441 | RENAME_COL | × DT_ALTERACAO → DT_ALTERACAO1 |
| 1441 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

