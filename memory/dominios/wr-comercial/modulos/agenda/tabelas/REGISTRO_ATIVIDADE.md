---
table: REGISTRO_ATIVIDADE
module: agenda
created_at_version: 491
last_modified_version: 1190
target_version: 1468
columns_count: 12
foreign_keys_count: 2
foreign_keys:
  CODPESSOA: PESSOAS
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `REGISTRO_ATIVIDADE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 491;
- **Última mudança:** UPDATE 1190;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `SEQUENCIA` | `INTEGER` | NOT NULL |  | v491 | v491 |
| 2 | `CHAVE` | `varchar(50)` | NOT NULL |  | v491 | v491 |
| 3 | `TABELA` | `VARCHAR(255)` | NOT NULL |  | v491 | v491 |
| 4 | `MENSAGEM` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v491 | v491 |
| 5 | `DATA` | `TIMESTAMP` | NULL |  | v491 | v491 |
| 6 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v491 | v491 |
| 7 | `CODCENTROTRABALHO` | `INTEGER` | NULL |  | v491 | v491 |
| 8 | `CODPESSOA` | `VARCHAR(10)` | NULL | → `PESSOAS` | v491 | v491 |
| 9 | `tipo` | `VARCHAR(10)` | NULL |  | v492 | v492 |
| 10 | `CODDESTINO` | `VARCHAR(50)` | NULL |  | v520 | v520 |
| 11 | `FORMDESTINO` | `VARCHAR(1000)` | NULL |  | v519 | v520 |
| 12 | `MIGRADO` | `VARCHAR(1)` | NULL |  | v1190 | v1190 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 491 | CREATE | CREATE TABLE com 8 colunas |
| 492 | ADD_COL | + tipo VARCHAR(10) |
| 519 | ADD_COL | + LINK VARCHAR(1000) |
| 520 | ADD_COL | + CODDESTINO VARCHAR(50) |
| 520 | RENAME_COL | × LINK → FORMDESTINO |
| 1190 | ADD_COL | + MIGRADO VARCHAR(1) |

