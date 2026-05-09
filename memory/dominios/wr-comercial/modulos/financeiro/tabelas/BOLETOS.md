---
table: BOLETOS
module: financeiro
created_at_version: 9
last_modified_version: 1330
target_version: 1468
columns_count: 26
foreign_keys_count: 3
foreign_keys:
  CODBANCO: BANCOS
  CODEMPRESA: EMPRESA
  CODFINANCEIRO: FINANCEIRO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BOLETOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 9;
- **Última mudança:** UPDATE 1330;
- **Total colunas (versão 1468):** 26

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODFINANCEIRO` | [`FINANCEIRO`](../../financeiro/tabelas/FINANCEIRO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v9 | v9 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL |  | v9 | v9 |
| 3 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | → `EMPRESA` | v9 | v9 |
| 4 | `CARTEIRA` | `VARCHAR(15)` | NULL |  | v9 | v9 |
| 5 | `TIPO` | `VARCHAR(15)` | NULL |  | v9 | v9 |
| 6 | `ESPECIE` | `VARCHAR(15)` | NULL |  | v9 | v9 |
| 7 | `DEMONSTRATIVO` | `VARCHAR(1000)` | NULL |  | v9 | v435 |
| 8 | `ABATIMENTO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 9 | `JUROS_MORA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 10 | `MULTA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 11 | `DESCONTO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 12 | `PROTESTO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 13 | `ACEITE` | `VARCHAR(1)` | NULL |  | v9 | v9 |
| 14 | `OCORENCIA` | `VARCHAR(100)` | NULL |  | v9 | v9 |
| 15 | `REMESSA` | `INTEGER` | NULL |  | v9 | v9 |
| 16 | `RETORNO` | `INTEGER` | NULL |  | v9 | v9 |
| 17 | `TIPOOCORRENCIA` | `VARCHAR(50)` | NULL |  | v9 | v9 |
| 18 | `CODBANCO` | `integer` | NULL | → `BANCOS` | v303 | v303 |
| 19 | `BAIXA_DEVOLUCAO` | `INTEGER` | NULL |  | v428 | v428 |
| 20 | `ATIVO` | `VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP` | NULL |  | v1194 | v1194 |
| 21 | `SITUACAO` | `VARCHAR(50)` | NULL |  | v1196 | v1196 |
| 22 | `DIADESCONTO` | `INTEGER` | NULL |  | v1278 | v1278 |
| 23 | `PODE_IMPRIMIR` | `VARCHAR(1)` | NULL |  | v1312 | v1312 |
| 24 | `CODFINANCEIRO` | `INTEGER` | NULL | → `FINANCEIRO` | v1328 | v1328 |
| 25 | `PODE_ENVIAR` | `VARCHAR(1)` | NULL |  | v1329 | v1329 |
| 26 | `DT_REMESSA` | `TIMESTAMP` | NULL |  | v1330 | v1330 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 9 | CREATE | CREATE TABLE com 17 colunas |
| 303 | ADD_COL | + CODBANCO integer |
| 428 | ADD_COL | + BAIXA_DEVOLUCAO INTEGER |
| 435 | ALTER_TYPE | ~ DEMONSTRATIVO TYPE VARCHAR(1000) |
| 1194 | ADD_COL | + ATIVO VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP |
| 1196 | ADD_COL | + SITUACAO VARCHAR(50) |
| 1278 | ADD_COL | + DIADESCONTO INTEGER |
| 1312 | ADD_COL | + PODE_IMPRIMIR VARCHAR(1) |
| 1328 | ADD_COL | + CODFINANCEIRO INTEGER |
| 1329 | ADD_COL | + PODE_ENVIAR VARCHAR(1) |
| 1330 | ADD_COL | + DT_REMESSA TIMESTAMP |

