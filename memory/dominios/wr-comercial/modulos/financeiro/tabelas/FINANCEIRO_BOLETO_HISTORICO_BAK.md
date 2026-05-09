---
table: FINANCEIRO_BOLETO_HISTORICO_BAK
module: financeiro
created_at_version: 1328
last_modified_version: 1328
target_version: 1468
columns_count: 22
foreign_keys_count: 3
foreign_keys:
  CODCONTA: CONTAS
  CODEMPRESA: EMPRESA
  CODFINANCEIRO_BOLETO: FINANCEIRO_BOLETO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_BOLETO_HISTORICO_BAK`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1328;
- **Última mudança:** UPDATE 1328;
- **Total colunas (versão 1468):** 22

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODFINANCEIRO_BOLETO` | [`FINANCEIRO_BOLETO`](../../financeiro/tabelas/FINANCEIRO_BOLETO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODFINANCEIRO_BOLETO` | `INTEGER` | NOT NULL | → `FINANCEIRO_BOLETO` | v1328 | v1328 |
| 2 | `CODCONTA` | `INTEGER` | NOT NULL | → `CONTAS` | v1328 | v1328 |
| 3 | `BOLETO_NOSSO_NR` | `VARCHAR(20)` | NOT NULL |  | v1328 | v1328 |
| 4 | `DESCRICAO` | `VARCHAR(50)` | NOT NULL |  | v1328 | v1328 |
| 5 | `DATA` | `TIMESTAMP` | NULL |  | v1328 | v1328 |
| 6 | `DT_OCORRENCIA` | `TIMESTAMP` | NULL |  | v1328 | v1328 |
| 7 | `DT_CREDITO` | `TIMESTAMP` | NULL |  | v1328 | v1328 |
| 8 | `VALOR_CREDITO` | `DOUBLE PRECISION` | NULL |  | v1328 | v1328 |
| 9 | `DESPESA_COBRANCA` | `DOUBLE PRECISION` | NULL |  | v1328 | v1328 |
| 10 | `CODIGO` | `INTEGER` | NOT NULL |  | v1328 | v1328 |
| 11 | `CODPEDIDO` | `VARCHAR(10)` | NULL |  | v1328 | v1328 |
| 12 | `CODEMPRESA` | `VARCHAR(10)` | NULL | → `EMPRESA` | v1328 | v1328 |
| 13 | `OCORRENCIA` | `VARCHAR(200)` | NULL |  | v1328 | v1328 |
| 14 | `TIPOOCORRENCIA` | `VARCHAR(50)` | NULL |  | v1328 | v1328 |
| 15 | `DIFERENCA` | `DOUBLE PRECISION` | NULL |  | v1328 | v1328 |
| 16 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1328 | v1328 |
| 17 | `RETORNOS_ANTERIORES` | `VARCHAR(250)` | NULL |  | v1328 | v1328 |
| 18 | `DATA_ARQUIVO` | `TIMESTAMP` | NULL |  | v1328 | v1328 |
| 19 | `VALOR_RECEBIDO` | `DOUBLE PRECISION` | NULL |  | v1328 | v1328 |
| 20 | `MOTIVO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v1328 | v1328 |
| 21 | `CODIGO_MIGRADO` | `INTEGER` | NULL |  | v1328 | v1328 |
| 22 | `TEM_MIGRADO` | `VARCHAR(1)` | NULL |  | v1328 | v1328 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1328 | CREATE | CREATE TABLE com 22 colunas |

