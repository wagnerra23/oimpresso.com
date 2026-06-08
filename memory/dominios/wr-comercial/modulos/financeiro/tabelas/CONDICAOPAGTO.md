---
table: CONDICAOPAGTO
module: financeiro
created_at_version: 320
last_modified_version: 1289
target_version: 1468
columns_count: 10
foreign_keys_count: 2
foreign_keys:
  CODPLANOCONTAS: PLANOCONTAS
  CODPLANOCONTAS_PAGTO: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONDICAOPAGTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 320;
- **Última mudança:** UPDATE 1289;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |
| `CODPLANOCONTAS_PAGTO` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TIPO_UTILIZACAO` | `varchar (15)` | NULL |  | v320 | v320 |
| 2 | `PERC_ENTRADA` | `DOUBLE PRECISION` | NULL |  | v420 | v420 |
| 3 | `CODPLANOCONTAS` | `VARCHAR(30) CHARACTER SET WIN1252` | NULL | → `PLANOCONTAS` | v449 | v499 |
| 4 | `CODPLANOCONTAS_PAGTO` | `VARCHAR(30) CHARACTER SET WIN1252` | NULL | → `PLANOCONTAS` | v449 | v499 |
| 5 | `FATOR_COMERCIAL` | `DOUBLE PRECISION` | NULL |  | v500 | v500 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 8 | `INTERVALO_MENSAL` | `DOM_BOOLEAN` | NULL |  | v742 | v742 |
| 9 | `IS_CARTAO` | `VARCHAR(1)` | NULL |  | v1141 | v1141 |
| 10 | `PODE_SUBSTITUIR_DESCONTO_VENDA` | `VARCHAR(1)` | NULL |  | v1289 | v1289 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 320 | ADD_COL | + TIPO_UTILIZACAO varchar (15) |
| 420 | ADD_COL | + PERC_ENTRADA DOUBLE PRECISION |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 449 | ADD_COL | + CODPLANOCONTAS VARCHAR(15) |
| 449 | ADD_COL | + CODPLANOCONTAS_PAGTO VARCHAR(15) |
| 498 | ADD_COL | + FATOR_COMERCIAL DOUBLE PRECISION |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS_PAGTO TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 500 | ADD_COL | + FATOR_COMERCIAL DOUBLE PRECISION |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 740 | ADD_COL | + INTERVALO_MENSAL DOM_BOOLEAN |
| 742 | ADD_COL | + INTERVALO_MENSAL DOM_BOOLEAN |
| 1141 | ADD_COL | + IS_CARTAO VARCHAR(1) |
| 1289 | ADD_COL | + PODE_SUBSTITUIR_DESCONTO_VENDA VARCHAR(1) |

