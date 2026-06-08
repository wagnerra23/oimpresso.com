---
table: AGENDA_MENSAGEM
module: agenda
created_at_version: 343
last_modified_version: 417
target_version: 1468
columns_count: 10
foreign_keys_count: 4
foreign_keys:
  CODAGENDA: AGENDA
  CODAGENDA_FAQ: AGENDA_FAQ
  CODSETOR: SETOR
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `AGENDA_MENSAGEM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 343;
- **Última mudança:** UPDATE 417;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODAGENDA` | [`AGENDA`](../../agenda/tabelas/AGENDA.md) |
| `CODAGENDA_FAQ` | [`AGENDA_FAQ`](../../agenda/tabelas/AGENDA_FAQ.md) |
| `CODSETOR` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v343 | v343 |
| 2 | `CODAGENDA` | `VARCHAR(40)` | NOT NULL | → `AGENDA` | v343 | v343 |
| 3 | `MENSAGEM` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v343 | v343 |
| 4 | `DT_MENSAGEM` | `TIMESTAMP` | NULL |  | v343 | v343 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v343 | v343 |
| 6 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v343 | v343 |
| 7 | `CODSETOR` | `INTEGER` | NULL | → `SETOR` | v395 | v395 |
| 8 | `PERMISSAO` | `VARCHAR(10)` | NULL |  | v395 | v395 |
| 9 | `CODAGENDA_FAQ` | `VARCHAR(15)` | NULL | → `AGENDA_FAQ` | v399 | v399 |
| 10 | `TIPO` | `varchar(15)` | NULL |  | v417 | v417 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 343 | CREATE | CREATE TABLE com 6 colunas |
| 395 | ADD_COL | + CODSETOR INTEGER |
| 395 | ADD_COL | + PERMISSAO VARCHAR(10) |
| 399 | ADD_COL | + CODAGENDA_FAQ VARCHAR(15) |
| 417 | ADD_COL | + TIPO varchar(15) |

