---
id: dominios-wr-comercial-modulos-financeiro-tabelas-financeiro-boleto-historico
table: FINANCEIRO_BOLETO_HISTORICO
module: financeiro
created_at_version: 9
last_modified_version: 1335
target_version: 1468
columns_count: 32
foreign_keys_count: 6
foreign_keys:
  CODBOLETOS: BOLETOS
  CODCONTA: CONTAS
  CODEMPRESA: EMPRESA
  CODFINANCEIRO: FINANCEIRO
  CODFINANCEIRO_BOLETO: FINANCEIRO_BOLETO
  CODFINANCEIRO_BOLETO_BKP: FINANCEIRO_BOLETO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_BOLETO_HISTORICO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 9;
- **Última mudança:** UPDATE 1335;
- **Total colunas (versão 1468):** 32

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBOLETOS` | [`BOLETOS`](../../financeiro/tabelas/BOLETOS.md) |
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODFINANCEIRO` | [`FINANCEIRO`](../../financeiro/tabelas/FINANCEIRO.md) |
| `CODFINANCEIRO_BOLETO` | [`FINANCEIRO_BOLETO`](../../financeiro/tabelas/FINANCEIRO_BOLETO.md) |
| `CODFINANCEIRO_BOLETO_BKP` | [`FINANCEIRO_BOLETO`](../../financeiro/tabelas/FINANCEIRO_BOLETO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODFINANCEIRO_BOLETO` | `INTEGER` | NOT NULL | → `FINANCEIRO_BOLETO` | v9 | v9 |
| 2 | `CODCONTA` | `INTEGER` | NOT NULL | → `CONTAS` | v9 | v9 |
| 3 | `BOLETO_NOSSO_NR` | `VARCHAR(20)` | NOT NULL |  | v9 | v9 |
| 4 | `DESCRICAO` | `VARCHAR(50)` | NOT NULL |  | v9 | v9 |
| 5 | `DATA` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 6 | `DT_OCORRENCIA` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 7 | `DT_CREDITO` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 8 | `VALOR_CREDITO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 9 | `DESPESA_COBRANCA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 10 | `CODIGO` | `INTEGER` | NOT NULL |  | v12 | v12 |
| 11 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL |  | v12 | v12 |
| 12 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | → `EMPRESA` | v12 | v12 |
| 13 | `OCORRENCIA` | `VARCHAR(200)` | NULL |  | v12 | v12 |
| 14 | `TIPOOCORRENCIA` | `VARCHAR(50)` | NULL |  | v12 | v12 |
| 15 | `DIFERENCA` | `DOUBLE PRECISION` | NULL |  | v12 | v12 |
| 16 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 17 | `RETORNOS_ANTERIORES` | `varchar (250)` | NULL |  | v229 | v229 |
| 18 | `DATA_ARQUIVO` | `timestamp` | NULL |  | v311 | v311 |
| 19 | `VALOR_RECEBIDO` | `double precision` | NULL |  | v319 | v319 |
| 20 | `MOTIVO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v473 | v473 |
| 21 | `AGRUPADOR` | `INTEGER` | NULL |  | v1321 | v1321 |
| 22 | `CODIGO_MIGRADO` | `INTEGER` | NULL |  | v1328 | v1328 |
| 23 | `TEM_MIGRADO` | `VARCHAR(1)` | NULL |  | v1328 | v1328 |
| 24 | `CODFINANCEIRO_BOLETO_BKP` | `INTEGER` | NULL | → `FINANCEIRO_BOLETO` | v1328 | v1328 |
| 25 | `CODFINANCEIRO` | `INTEGER` | NULL | → `FINANCEIRO` | v1328 | v1328 |
| 26 | `REMESSA` | `INTEGER` | NULL |  | v1330 | v1330 |
| 27 | `RETORNO` | `INTEGER` | NULL |  | v1330 | v1330 |
| 28 | `DOCUMENTO` | `VARCHAR(20)` | NULL |  | v1332 | v1332 |
| 29 | `VALOR_DESCONTO` | `DOUBLE PRECISION` | NULL |  | v1332 | v1332 |
| 30 | `VALOR_MORA_JUROS` | `DOUBLE PRECISION` | NULL |  | v1332 | v1332 |
| 31 | `VALOR_OUTROS_CREDITOS` | `DOUBLE PRECISION` | NULL |  | v1332 | v1332 |
| 32 | `CODBOLETOS` | `INTEGER` | NULL | → `BOLETOS` | v1335 | v1335 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 9 | CREATE | CREATE TABLE com 9 colunas |
| 10 | DROP_COL | - BOLETO_DESCONTO |
| 10 | RENAME_COL | × BOLETO_JUROS → DIFERENCA |
| 12 | ADD_COL | + CODIGO INTEGER |
| 12 | ADD_COL | + CODPEDIDO VARCHAR(10) |
| 12 | ADD_COL | + CODEMPRESA VARCHAR(10) |
| 12 | ADD_COL | + OCORRENCIA VARCHAR(200) |
| 12 | ADD_COL | + TIPOOCORRENCIA VARCHAR(50) |
| 12 | ADD_COL | + DIFERENCA DOUBLE PRECISION |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 229 | ADD_COL | + RETORNOS_ANTERIORES varchar (250) |
| 311 | ADD_COL | + DATA_ARQUIVO timestamp |
| 319 | ADD_COL | + VALOR_RECEBIDO double precision |
| 473 | ADD_COL | + MOTIVO BLOB SUB_TYPE 1 SEGMENT SIZE 1024 |
| 1321 | ADD_COL | + AGRUPADOR INTEGER |
| 1328 | ADD_COL | + CODIGO_MIGRADO INTEGER |
| 1328 | ADD_COL | + TEM_MIGRADO VARCHAR(1) |
| 1328 | ADD_COL | + CODFINANCEIRO_BOLETO_BKP INTEGER |
| 1328 | ADD_COL | + CODFINANCEIRO INTEGER |
| 1330 | ADD_COL | + REMESSA INTEGER |
| 1330 | ADD_COL | + RETORNO INTEGER |
| 1332 | ADD_COL | + DOCUMENTO VARCHAR(20) |
| 1332 | ADD_COL | + VALOR_DESCONTO DOUBLE PRECISION |
| 1332 | ADD_COL | + VALOR_MORA_JUROS DOUBLE PRECISION |
| 1332 | ADD_COL | + VALOR_OUTROS_CREDITOS DOUBLE PRECISION |
| 1335 | ADD_COL | + CODBOLETOS INTEGER |

