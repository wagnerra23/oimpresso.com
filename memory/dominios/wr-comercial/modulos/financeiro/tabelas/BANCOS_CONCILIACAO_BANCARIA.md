---
table: BANCOS_CONCILIACAO_BANCARIA
module: financeiro
created_at_version: 284
last_modified_version: 499
target_version: 1468
columns_count: 12
foreign_keys_count: 2
foreign_keys:
  CODBANCO: BANCOS
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BANCOS_CONCILIACAO_BANCARIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 284;
- **Última mudança:** UPDATE 499;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v284 | v284 |
| 2 | `CODBANCO` | `INTEGER` | NOT NULL | → `BANCOS` | v284 | v284 |
| 3 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v284 | v284 |
| 4 | `CODPLANOCONTAS` | `VARCHAR(30) CHARACTER SET WIN1252` | NULL | → `PLANOCONTAS` | v284 | v499 |
| 5 | `TIPO` | `VARCHAR(10)` | NULL |  | v284 | v284 |
| 6 | `TIPOPAGTO` | `VARCHAR(50)` | NULL |  | v284 | v427 |
| 7 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v284 | v284 |
| 8 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v284 | v284 |
| 9 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v284 | v284 |
| 10 | `HISTORICO` | `VARCHAR(100)` | NULL |  | v284 | v284 |
| 11 | `TIPO_MOVIMENTO` | `varchar (20)` | NULL |  | v286 | v286 |
| 12 | `ACAO` | `INTEGER` | NULL |  | v299 | v299 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 284 | CREATE | CREATE TABLE com 10 colunas |
| 286 | ADD_COL | + TIPO_MOVIMENTO varchar (20) |
| 299 | ADD_COL | + ACAO INTEGER |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |

