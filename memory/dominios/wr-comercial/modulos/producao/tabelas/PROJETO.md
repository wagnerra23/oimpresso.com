---
table: PROJETO
module: producao
created_at_version: 579
last_modified_version: 1347
target_version: 1468
columns_count: 8
foreign_keys_count: 1
foreign_keys:
  CODVENDA: VENDA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PROJETO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 1347;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v579 | v579 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v579 | v579 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v579 | v579 |
| 4 | `PESSOAS_RESPONSAVEL_CODIGO` | `VARCHAR(10), ADD PESSOAS_RESPONSAVEL_SEQUENCIA INTEGER, ADD PESSOAS_RESPONSAVEL_TIPO VARCHAR(3), ADD DT_INICIO TIMESTAMP, ADD DT_FIM TIMESTAMP, ADD TOKEN_PROJETO_MARCADOR VARCHAR(500), ADD SITUACAO VARCHAR(150), ADD OBSERVACAO VARCHAR(500)` | NULL |  | v966 | v966 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v968 | v968 |
| 6 | `VALOR` | `DOUBLE PRECISION, ADD PROGRESSO INTEGER, ADD STATUS VARCHAR(50), ADD LABEL VARCHAR(1000)` | NULL |  | v993 | v993 |
| 7 | `RAZAOSOCIAL` | `VARCHAR(150)` | NULL |  | v994 | v994 |
| 8 | `CODVENDA` | `VARCHAR(15)` | NULL | → `VENDA` | v1347 | v1347 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 3 colunas |
| 966 | ADD_COL | + PESSOAS_RESPONSAVEL_CODIGO VARCHAR(10), ADD PESSOAS_RESPONSAVEL_SEQUENCIA INTEGER, ADD PESSOAS_RESPONSAVEL_TIPO VARCHAR(3), ADD DT_INICIO TIMESTAMP, ADD DT_FIM TIMESTAMP, ADD TOKEN_PROJETO_MARCADOR VARCHAR(500), ADD SITUACAO VARCHAR(150), ADD OBSERVACAO VARCHAR(500) |
| 968 | ADD_COL | + ATIVO VARCHAR(1) |
| 993 | ADD_COL | + VALOR DOUBLE PRECISION, ADD PROGRESSO INTEGER, ADD STATUS VARCHAR(50), ADD LABEL VARCHAR(1000) |
| 993 | ALTER_TYPE | ~ OBSERVACAO TYPE VARCHAR(5000) CHARACTER SET WIN1252 |
| 994 | ADD_COL | + RAZAOSOCIAL VARCHAR(150) |
| 994 | RENAME_COL | × PROGRESSO → PCONCLUSAO |
| 1347 | ADD_COL | + CODVENDA VARCHAR(15) |

