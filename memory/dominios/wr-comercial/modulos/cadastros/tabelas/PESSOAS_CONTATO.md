---
table: PESSOAS_CONTATO
module: cadastros
created_at_version: 214
last_modified_version: 451
target_version: 1468
columns_count: 8
foreign_keys_count: 1
foreign_keys:
  CODPESSOA: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_CONTATO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 214;
- **Última mudança:** UPDATE 451;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v214 | v214 |
| 2 | `CODPESSOA` | `VARCHAR(10)` | NOT NULL | → `PESSOAS` | v214 | v214 |
| 3 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v214 | v214 |
| 4 | `CONTATO` | `VARCHAR(250)` | NULL |  | v214 | v214 |
| 5 | `FONE` | `varchar(30)` | NULL |  | v219 | v219 |
| 6 | `EMAIL` | `varchar(100)` | NULL |  | v219 | v219 |
| 7 | `ENDERECO` | `VARCHAR(500)` | NULL |  | v293 | v293 |
| 8 | `CELULAR` | `VARCHAR(30)` | NULL |  | v451 | v451 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 214 | CREATE | CREATE TABLE com 4 colunas |
| 219 | ADD_COL | + FONE varchar(30) |
| 219 | ADD_COL | + EMAIL varchar(100) |
| 293 | ADD_COL | + ENDERECO VARCHAR(500) |
| 451 | ADD_COL | + CELULAR VARCHAR(30) |

