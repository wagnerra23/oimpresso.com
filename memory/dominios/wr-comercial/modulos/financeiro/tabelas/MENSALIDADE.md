---
table: MENSALIDADE
module: financeiro
created_at_version: 489
last_modified_version: 1465
target_version: 1468
columns_count: 10
foreign_keys_count: 3
foreign_keys:
  CODCONTA: CONTAS
  CODEMAIL_MODELO: EMAIL_MODELO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSALIDADE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 489;
- **Última mudança:** UPDATE 1465;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEMAIL_MODELO` | [`EMAIL_MODELO`](../../agenda/tabelas/EMAIL_MODELO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v489 | v489 |
| 2 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v489 | v489 |
| 3 | `MES` | `DATE` | NULL |  | v489 | v489 |
| 4 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v489 | v489 |
| 5 | `DT_GERADO` | `TIMESTAMP` | NULL |  | v489 | v489 |
| 6 | `DT_FINANCEIRO` | `TIMESTAMP` | NULL |  | v489 | v489 |
| 7 | `CODPLANOCONTAS` | `VARCHAR(30) CHARACTER SET WIN1252` | NULL | → `PLANOCONTAS` | v489 | v499 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v489 | v489 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v734 | v734 |
| 10 | `CODEMAIL_MODELO` | `INTEGER` | NULL | → `EMAIL_MODELO` | v1465 | v1465 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 489 | CREATE | CREATE TABLE com 8 colunas |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 734 | ADD_COL | + ATIVO VARCHAR(1) |
| 1465 | ADD_COL | + CODEMAIL_MODELO INTEGER |

