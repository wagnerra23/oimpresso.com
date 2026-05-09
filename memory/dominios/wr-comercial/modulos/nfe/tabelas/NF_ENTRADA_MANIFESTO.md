---
table: NF_ENTRADA_MANIFESTO
module: nfe
created_at_version: 1021
last_modified_version: 1428
target_version: 1468
columns_count: 36
foreign_keys_count: 2
foreign_keys:
  CODEMPRESA: EMPRESA
  CODNF_ENTRADA: NF_ENTRADA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_MANIFESTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1021;
- **Última mudança:** UPDATE 1428;
- **Total colunas (versão 1468):** 36

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1021 | v1021 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1021 | v1021 |
| 3 | `NF_NUMERO` | `BIGINT` | NULL |  | v1021 | v1021 |
| 4 | `NF_CHAVE` | `VARCHAR(44)` | NOT NULL |  | v1021 | v1021 |
| 5 | `NF_CNPJCPF_EMITENTE` | `VARCHAR(18)` | NULL |  | v1021 | v1021 |
| 6 | `NF_AMBIENTE` | `INTEGER` | NULL |  | v1021 | v1021 |
| 7 | `NF_RAZAOSOCIAL_EMITENTE` | `VARCHAR(60)` | NULL |  | v1021 | v1021 |
| 8 | `NF_DT_EMISSAO` | `TIMESTAMP` | NULL |  | v1021 | v1021 |
| 9 | `NF_TOTAL` | `DOUBLE PRECISION` | NULL |  | v1021 | v1021 |
| 10 | `NF_SITUACAO` | `VARCHAR(30)` | NULL |  | v1021 | v1021 |
| 11 | `NSU` | `BIGINT` | NULL |  | v1021 | v1021 |
| 12 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 13 | `CODNF_ENTRADA` | `VARCHAR(10)` | NULL | → `NF_ENTRADA` | v1021 | v1021 |
| 14 | `TEM_NOTA` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 15 | `TEM_COMPRA` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 16 | `TEM_CIENCIA` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 17 | `TEM_CONFIRMADA` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 18 | `TEM_DESCONHECIDA` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 19 | `TEM_NAO_REALIZADA` | `VARCHAR(1)` | NULL |  | v1021 | v1021 |
| 20 | `NF_REFERENCIA` | `VARCHAR(44)` | NULL |  | v1122 | v1122 |
| 21 | `TIPO` | `VARCHAR(20)` | NULL |  | v1122 | v1122 |
| 22 | `TEM_SOLICITADO_CIENCIA` | `VARCHAR(1)` | NULL |  | v1142 | v1142 |
| 23 | `TEM_FINALIZADO` | `VARCHAR(1)` | NULL |  | v1145 | v1145 |
| 24 | `MOTIVO_FINALIZADO` | `VARCHAR(100)` | NULL |  | v1145 | v1145 |
| 25 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1145 | v1145 |
| 26 | `ARQUIVO_XML` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v1205 | v1205 |
| 27 | `SITUACAO` | `VARCHAR(50)` | NULL |  | v1208 | v1208 |
| 28 | `NF_IE` | `VARCHAR(50)` | NULL |  | v1413 | v1413 |
| 29 | `NF_PROTOCOLO` | `VARCHAR(100)` | NULL |  | v1413 | v1413 |
| 30 | `NF_DT_RECEBIMENTO` | `TIMESTAMP` | NULL |  | v1428 | v1428 |
| 31 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1414 | v1414 |
| 32 | `SITUACAO_MANIFESTACAO` | `VARCHAR(50)` | NULL |  | v1414 | v1414 |
| 33 | `TEM_LIDO` | `VARCHAR(1)` | NULL |  | v1414 | v1414 |
| 34 | `CHCTE` | `VARCHAR(50)` | NULL |  | v1417 | v1417 |
| 35 | `CHMDFE` | `VARCHAR(50)` | NULL |  | v1417 | v1417 |
| 36 | `XJUST` | `VARCHAR(100)` | NULL |  | v1417 | v1417 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1021 | CREATE | CREATE TABLE com 20 colunas |
| 1122 | ADD_COL | + NF_REFERENCIA VARCHAR(44) |
| 1122 | ADD_COL | + TIPO VARCHAR(20) |
| 1142 | ADD_COL | + TEM_SOLICITADO_CIENCIA VARCHAR(1) |
| 1145 | ADD_COL | + TEM_FINALIZADO VARCHAR(1) |
| 1145 | ADD_COL | + MOTIVO_FINALIZADO VARCHAR(100) |
| 1145 | ADD_COL | + OBSERVACAO VARCHAR(500) |
| 1205 | ADD_COL | + ARQUIVO_XML BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 1208 | ADD_COL | + SITUACAO VARCHAR(50) |
| 1413 | ADD_COL | + NF_IE VARCHAR(50) |
| 1413 | ADD_COL | + NF_PROTOCOLO VARCHAR(100) |
| 1413 | RENAME_COL | × DT_RECEBIMENTO → NF_DT_RECEBIMENTO |
| 1414 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1414 | ADD_COL | + SITUACAO_MANIFESTACAO VARCHAR(50) |
| 1414 | ADD_COL | + TEM_LIDO VARCHAR(1) |
| 1417 | ADD_COL | + CHCTE VARCHAR(50) |
| 1417 | ADD_COL | + CHMDFE VARCHAR(50) |
| 1417 | ADD_COL | + XJUST VARCHAR(100) |
| 1428 | ADD_COL | + NF_DT_RECEBIMENTO TIMESTAMP |

