---
table: MENSAGEM
module: agenda
created_at_version: 1430
last_modified_version: 1431
target_version: 1468
columns_count: 8
foreign_keys_count: 2
foreign_keys:
  CODMENSAGEM_ASSUNTO: MENSAGEM_ASSUNTO
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1430;
- **Última mudança:** UPDATE 1431;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODMENSAGEM_ASSUNTO` | [`MENSAGEM_ASSUNTO`](../../agenda/tabelas/MENSAGEM_ASSUNTO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1430 | v1430 |
| 2 | `CODMENSAGEM_ASSUNTO` | `INTEGER` | NULL | → `MENSAGEM_ASSUNTO` | v1430 | v1430 |
| 3 | `MENSAGEM` | `VARCHAR(5000)` | NULL |  | v1430 | v1430 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1430 | v1430 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1430 | v1430 |
| 6 | `DT_MENSAGEM` | `TIMESTAMP` | NULL |  | v1430 | v1430 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1430 | v1430 |
| 8 | `DATATYPE` | `VARCHAR(10)` | NULL |  | v1431 | v1431 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1430 | CREATE | CREATE TABLE com 7 colunas |
| 1431 | ADD_COL | + DATATYPE VARCHAR(10) |

