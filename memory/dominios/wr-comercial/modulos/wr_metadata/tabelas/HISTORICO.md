---
table: HISTORICO
module: wr_metadata
created_at_version: 217
last_modified_version: 1430
target_version: 1468
columns_count: 8
foreign_keys_count: 1
foreign_keys:
  CODMENSAGEM_ASSUNTO: MENSAGEM_ASSUNTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 217;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODMENSAGEM_ASSUNTO` | [`MENSAGEM_ASSUNTO`](../../agenda/tabelas/MENSAGEM_ASSUNTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CHAVE_PK` | `varchar(250)` | NULL |  | v217 | v217 |
| 2 | `CHAVE_PK1` | `INTEGER` | NULL |  | v728 | v728 |
| 3 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v728 | v728 |
| 4 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v728 | v728 |
| 5 | `MENSAGEM` | `VARCHAR(5000)` | NULL |  | v728 | v728 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 7 | `DT_FECHAMENTO` | `TIMESTAMP` | NULL |  | v1026 | v1026 |
| 8 | `CODMENSAGEM_ASSUNTO` | `INTEGER` | NULL | → `MENSAGEM_ASSUNTO` | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 217 | ADD_COL | + CHAVE_PK varchar(250) |
| 728 | ADD_COL | + TIPO VARCHAR(10) |
| 728 | ADD_COL | + ORIGEM_FORM VARCHAR(255) |
| 728 | ADD_COL | + ORIGEM_CODIGO VARCHAR(255) |
| 728 | ADD_COL | + CHAVE_PK1 INTEGER |
| 728 | ADD_COL | + CHAVE_PK2 VARCHAR(40) |
| 728 | ADD_COL | + CHAVE_PK3 VARCHAR(15) |
| 728 | ADD_COL | + CHAVE_PK_PARENT INTEGER |
| 728 | ADD_COL | + MENSAGEM VARCHAR(5000) |
| 728 | ADD_COL | + CHAVE_PRIMARIA INTEGER |
| 728 | ADD_COL | + CODIGO_ORIGEM VARCHAR(255) |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | DROP_COL | - CODIGO_ORIGEM |
| 728 | DROP_COL | - CHAVE_PRIMARIA |
| 728 | DROP_COL | - ORIGEM_CODIGO |
| 728 | DROP_COL | - ORIGEM_FORM |
| 728 | DROP_COL | - TIPO |
| 728 | DROP_COL | - CHAVE_PK_PARENT |
| 1026 | ADD_COL | + DT_FECHAMENTO TIMESTAMP |
| 1430 | ADD_COL | + CODMENSAGEM_ASSUNTO INTEGER |

