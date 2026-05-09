---
table: CONTRATO
module: vendas
created_at_version: 1260
last_modified_version: 1280
target_version: 1468
columns_count: 19
foreign_keys_count: 3
foreign_keys:
  CODCONTA: CONTAS
  CODCONTRATO_TIPO: CONTRATO_TIPO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONTRATO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1260;
- **Última mudança:** UPDATE 1280;
- **Total colunas (versão 1468):** 19

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODCONTRATO_TIPO` | [`CONTRATO_TIPO`](../../vendas/tabelas/CONTRATO_TIPO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1260 | v1260 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1260 | v1260 |
| 3 | `PESSOA_CLIENTE_CODIGO` | `VARCHAR(10)` | NULL |  | v1260 | v1260 |
| 4 | `PESSOA_CLIENTE_TIPO` | `VARCHAR(10)` | NULL |  | v1260 | v1260 |
| 5 | `PESSOA_CLIENTE_SEQUENCIA` | `INTEGER` | NULL |  | v1260 | v1260 |
| 6 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v1260 | v1260 |
| 7 | `DT_FIM` | `TIMESTAMP` | NULL |  | v1260 | v1260 |
| 8 | `DT_PROXIMA_FATURA` | `TIMESTAMP` | NULL |  | v1260 | v1260 |
| 9 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v1260 | v1260 |
| 10 | `DOCUMENTO` | `VARCHAR(30)` | NULL |  | v1260 | v1260 |
| 11 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | → `PLANOCONTAS` | v1260 | v1260 |
| 12 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v1260 | v1260 |
| 13 | `CODCONTRATO_TIPO` | `INTEGER` | NULL | → `CONTRATO_TIPO` | v1260 | v1260 |
| 14 | `OBSERVACAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v1260 | v1260 |
| 15 | `ATIVO` | `Varchar(1)` | NULL |  | v1260 | v1260 |
| 16 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1280 | v1280 |
| 17 | `TIPO` | `VARCHAR(50)` | NULL |  | v1260 | v1260 |
| 18 | `STATUS` | `VARCHAR(50)` | NULL |  | v1260 | v1260 |
| 19 | `DT_ULTIMA_FATURA_GERADA` | `TIMESTAMP` | NULL |  | v1260 | v1260 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 1260 | CREATE | CREATE TABLE com 16 colunas |
| 1260 | ADD_COL | + TIPO VARCHAR(50) |
| 1260 | ADD_COL | + STATUS VARCHAR(50) |
| 1260 | ADD_COL | + DT_ULTIMA_FATURA_GERADA TIMESTAMP |
| 1280 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

