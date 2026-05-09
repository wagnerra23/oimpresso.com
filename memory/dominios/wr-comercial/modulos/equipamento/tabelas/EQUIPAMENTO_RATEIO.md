---
table: EQUIPAMENTO_RATEIO
module: equipamento
created_at_version: 1100
last_modified_version: 1119
target_version: 1468
columns_count: 15
foreign_keys_count: 4
foreign_keys:
  CODCONDICAOPAGTO: CONDICAOPAGTO
  CODCONTA: CONTAS
  CODEQUIPAMENTO: EQUIPAMENTO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_RATEIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1100;
- **Última mudança:** UPDATE 1119;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONDICAOPAGTO` | [`CONDICAOPAGTO`](../../financeiro/tabelas/CONDICAOPAGTO.md) |
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEQUIPAMENTO` | [`EQUIPAMENTO`](../../equipamento/tabelas/EQUIPAMENTO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1100 | v1100 |
| 2 | `CODEQUIPAMENTO` | `INTEGER` | NULL | → `EQUIPAMENTO` | v1100 | v1100 |
| 3 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1100 | v1100 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1100 | v1100 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1100 | v1100 |
| 6 | `TIPO` | `VARCHAR(50)` | NULL |  | v1100 | v1100 |
| 7 | `TOTAL` | `DOUBLE PRECISION` | NULL |  | v1100 | v1100 |
| 8 | `CODCONDICAOPAGTO` | `INTEGER` | NULL | → `CONDICAOPAGTO` | v1119 | v1119 |
| 9 | `CONDICAOPAGTO` | `VARCHAR(100)` | NULL |  | v1119 | v1119 |
| 10 | `INTERVALO_MENSAL` | `VARCHAR(1)` | NULL |  | v1119 | v1119 |
| 11 | `QUANTIDADE_PARCELAS` | `INTEGER` | NULL |  | v1119 | v1119 |
| 12 | `CODPLANOCONTAS` | `VARCHAR(30)` | NULL | → `PLANOCONTAS` | v1119 | v1119 |
| 13 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v1119 | v1119 |
| 14 | `DIA_INTERVALO` | `INTEGER` | NULL |  | v1119 | v1119 |
| 15 | `DATA_INICIAL` | `TIMESTAMP` | NULL |  | v1119 | v1119 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1100 | CREATE | CREATE TABLE com 5 colunas |
| 1100 | ADD_COL | + PLACA VARCHAR(10) |
| 1100 | ADD_COL | + TIPO VARCHAR(50) |
| 1100 | ADD_COL | + TOTAL DOUBLE PRECISION |
| 1119 | ADD_COL | + CODCONDICAOPAGTO INTEGER |
| 1119 | ADD_COL | + CONDICAOPAGTO VARCHAR(100) |
| 1119 | ADD_COL | + INTERVALO_MENSAL VARCHAR(1) |
| 1119 | ADD_COL | + QUANTIDADE_PARCELAS INTEGER |
| 1119 | ADD_COL | + CODPLANOCONTAS VARCHAR(30) |
| 1119 | ADD_COL | + CODCONTA INTEGER |
| 1119 | ADD_COL | + DIA_INTERVALO INTEGER |
| 1119 | DROP_COL | - PLACA |
| 1119 | ADD_COL | + DATA_INICIAL TIMESTAMP |

