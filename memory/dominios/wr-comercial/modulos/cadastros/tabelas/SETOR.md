---
table: SETOR
module: cadastros
created_at_version: 56
last_modified_version: 730
target_version: 1468
columns_count: 10
foreign_keys_count: 1
foreign_keys:
  CODFUNCIONARIO_RESPONSAVEL: FUNCIONARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SETOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 56;
- **Última mudança:** UPDATE 730;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFUNCIONARIO_RESPONSAVEL` | [`FUNCIONARIO`](../../cadastros/tabelas/FUNCIONARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v56 | v56 |
| 2 | `CODIGO` | `INTEGER` | NOT NULL |  | v56 | v56 |
| 3 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v56 | v56 |
| 4 | `CODFUNCIONARIO_RESPONSAVEL` | `VARCHAR(10)` | NULL | → `FUNCIONARIO` | v56 | v56 |
| 5 | `SETOR_TIPO` | `VARCHAR(15)` | NULL |  | v316 | v316 |
| 6 | `IMAGEM` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v316 | v316 |
| 7 | `PARENT` | `INTEGER` | NULL |  | v346 | v346 |
| 8 | `TIPO` | `VARCHAR(10)` | NULL |  | v346 | v346 |
| 9 | `KANBAN` | `varchar(1)` | NULL |  | v415 | v415 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL |  | v730 | v730 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 52 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 55 | CREATE | CREATE TABLE com 4 colunas |
| 56 | CREATE | CREATE TABLE com 4 colunas |
| 316 | ADD_COL | + SETOR_TIPO VARCHAR(15) |
| 316 | ADD_COL | + IMAGEM BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 346 | ADD_COL | + PARENT INTEGER |
| 346 | ADD_COL | + TIPO VARCHAR(10) |
| 415 | ADD_COL | + KANBAN varchar(1) |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |

