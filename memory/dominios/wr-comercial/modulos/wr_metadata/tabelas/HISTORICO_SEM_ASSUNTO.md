---
table: HISTORICO_SEM_ASSUNTO
module: wr_metadata
created_at_version: 1430
last_modified_version: 1430
target_version: 1468
columns_count: 19
foreign_keys_count: 4
foreign_keys:
  CODEMPRESA: EMPRESA
  CODHISTORICO_ASSUNTO: HISTORICO
  CODMENSAGEM_ASSUNTO: MENSAGEM_ASSUNTO
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_SEM_ASSUNTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1430;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 19

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODHISTORICO_ASSUNTO` | [`HISTORICO`](../../wr_metadata/tabelas/HISTORICO.md) |
| `CODMENSAGEM_ASSUNTO` | [`MENSAGEM_ASSUNTO`](../../agenda/tabelas/MENSAGEM_ASSUNTO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1430 | v1430 |
| 2 | `CODHISTORICO_ASSUNTO` | `INTEGER` | NULL | → `HISTORICO` | v1430 | v1430 |
| 3 | `CODEMPRESA` | `VARCHAR(250)` | NULL | → `EMPRESA` | v1430 | v1430 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1430 | v1430 |
| 5 | `DATA` | `DATE` | NULL |  | v1430 | v1430 |
| 6 | `HORA` | `TIME` | NULL |  | v1430 | v1430 |
| 7 | `FORMULARIO` | `VARCHAR(250)` | NULL |  | v1430 | v1430 |
| 8 | `TELA` | `VARCHAR(250)` | NULL |  | v1430 | v1430 |
| 9 | `EVENTO` | `VARCHAR(50)` | NULL |  | v1430 | v1430 |
| 10 | `OBS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v1430 | v1430 |
| 11 | `TABELA` | `VARCHAR(50)` | NULL |  | v1430 | v1430 |
| 12 | `CHAVE_PK` | `VARCHAR(250)` | NULL |  | v1430 | v1430 |
| 13 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1430 | v1430 |
| 14 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1430 | v1430 |
| 15 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1430 | v1430 |
| 16 | `MENSAGEM` | `VARCHAR(5000)` | NULL |  | v1430 | v1430 |
| 17 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1430 | v1430 |
| 18 | `DT_FECHAMENTO` | `TIMESTAMP` | NULL |  | v1430 | v1430 |
| 19 | `CODMENSAGEM_ASSUNTO` | `INTEGER` | NULL | → `MENSAGEM_ASSUNTO` | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1430 | CREATE | CREATE TABLE com 19 colunas |

