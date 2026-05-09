---
table: CLIENTES_FINANCEIRO
module: cadastros
created_at_version: 536
last_modified_version: 536
target_version: 1468
columns_count: 11
foreign_keys_count: 4
foreign_keys:
  CODCLIENTE: PESSOAS
  CODCONDICAOPAGTO: CONDICAOPAGTO
  CODCONTA: CONTAS
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CLIENTES_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 536;
- **Última mudança:** UPDATE 536;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCLIENTE` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODCONDICAOPAGTO` | [`CONDICAOPAGTO`](../../financeiro/tabelas/CONDICAOPAGTO.md) |
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v536 | v536 |
| 2 | `CODCLIENTE` | `VARCHAR(10)` | NOT NULL | → `PESSOAS` | v536 | v536 |
| 3 | `NPARCELAS` | `INTEGER` | NULL |  | v536 | v536 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v536 | v536 |
| 5 | `DTVENCIMENTO` | `TIMESTAMP` | NULL |  | v536 | v536 |
| 6 | `CODCONDICAOPAGTO` | `INTEGER` | NULL | → `CONDICAOPAGTO` | v536 | v536 |
| 7 | `TIPO` | `VARCHAR(30)` | NULL |  | v536 | v536 |
| 8 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | → `PLANOCONTAS` | v536 | v536 |
| 9 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v536 | v536 |
| 10 | `DT_ENVIO_FINANCEIRO` | `TIMESTAMP` | NULL |  | v536 | v536 |
| 11 | `STATUS` | `VARCHAR(20)` | NULL |  | v536 | v536 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 528 | CREATE | CREATE TABLE com 11 colunas |
| 536 | CREATE | CREATE TABLE com 11 colunas |

