---
table: NF_ENTRADA_MANIFESTO_NSU
module: nfe
created_at_version: 1409
last_modified_version: 1416
target_version: 1468
columns_count: 24
foreign_keys_count: 2
foreign_keys:
  CODEMPRESA: EMPRESA
  CODNF_ENTRADA_MANIFESTO_REQUISI: NF_ENTRADA_MANIFESTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_MANIFESTO_NSU`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1409;
- **Última mudança:** UPDATE 1416;
- **Total colunas (versão 1468):** 24

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODNF_ENTRADA_MANIFESTO_REQUISI` | [`NF_ENTRADA_MANIFESTO`](../../nfe/tabelas/NF_ENTRADA_MANIFESTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1409 | v1409 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1409 | v1409 |
| 3 | `NSU` | `BIGINT` | NOT NULL |  | v1409 | v1409 |
| 4 | `TIPO_DOCUMENTO` | `VARCHAR(10)` | NOT NULL |  | v1409 | v1409 |
| 5 | `DT_PROCESSADO` | `TIMESTAMP` | NOT NULL |  | v1409 | v1409 |
| 6 | `STATUS_PROCESSAMENTO` | `VARCHAR(50)` | NULL |  | v1409 | v1409 |
| 7 | `ATIVO` | `VARCHAR(1) DEFAULT 'S'` | NULL |  | v1409 | v1409 |
| 8 | `CODNF_ENTRADA_MANIFESTO_REQUISI` | `INTEGER` | NULL | → `NF_ENTRADA_MANIFESTO` | v1411 | v1411 |
| 9 | `ARQUIVO_XML` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v1411 | v1411 |
| 10 | `NF_CHAVE` | `VARCHAR(44)` | NULL |  | v1409 | v1412 |
| 11 | `NF_NUMERO` | `DOUBLE PRECISION` | NULL |  | v1412 | v1412 |
| 12 | `NF_CNPJCPF_EMITENTE` | `VARCHAR(18)` | NULL |  | v1412 | v1412 |
| 13 | `NF_AMBIENTE` | `INTEGER` | NULL |  | v1412 | v1412 |
| 14 | `NF_RAZAOSOCIAL_EMITENTE` | `VARCHAR(255)` | NULL |  | v1412 | v1412 |
| 15 | `NF_MANIFESTO` | `VARCHAR(30)` | NULL |  | v1412 | v1412 |
| 16 | `NF_DT_EMISSAO` | `TIMESTAMP` | NULL |  | v1412 | v1412 |
| 17 | `NF_DT_RECEBIMENTO` | `TIMESTAMP` | NULL |  | v1412 | v1412 |
| 18 | `NF_TOTAL` | `DOUBLE PRECISION` | NULL |  | v1412 | v1412 |
| 19 | `NF_SITUACAO` | `VARCHAR(50)` | NULL |  | v1412 | v1412 |
| 20 | `NF_IE` | `VARCHAR(50)` | NULL |  | v1412 | v1412 |
| 21 | `NF_PROTOCOLO` | `VARCHAR(100)` | NULL |  | v1412 | v1412 |
| 22 | `CHCTE` | `VARCHAR(50)` | NULL |  | v1416 | v1416 |
| 23 | `CHMDFE` | `VARCHAR(50)` | NULL |  | v1416 | v1416 |
| 24 | `XJUST` | `VARCHAR(100)` | NULL |  | v1416 | v1416 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1409 | CREATE | CREATE TABLE com 8 colunas |
| 1411 | ADD_COL | + CODNF_ENTRADA_MANIFESTO_REQUISI INTEGER |
| 1411 | ADD_COL | + ARQUIVO_XML BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 1412 | RENAME_COL | × CHAVE_NFE → NF_CHAVE |
| 1412 | ADD_COL | + NF_NUMERO DOUBLE PRECISION |
| 1412 | ADD_COL | + NF_CNPJCPF_EMITENTE VARCHAR(18) |
| 1412 | ADD_COL | + NF_AMBIENTE INTEGER |
| 1412 | ADD_COL | + NF_RAZAOSOCIAL_EMITENTE VARCHAR(255) |
| 1412 | ADD_COL | + NF_MANIFESTO VARCHAR(30) |
| 1412 | ADD_COL | + NF_DT_EMISSAO TIMESTAMP |
| 1412 | ADD_COL | + NF_DT_RECEBIMENTO TIMESTAMP |
| 1412 | ADD_COL | + NF_TOTAL DOUBLE PRECISION |
| 1412 | ADD_COL | + NF_SITUACAO VARCHAR(50) |
| 1412 | ADD_COL | + NF_IE VARCHAR(50) |
| 1412 | ADD_COL | + NF_PROTOCOLO VARCHAR(100) |
| 1416 | ADD_COL | + CHCTE VARCHAR(50) |
| 1416 | ADD_COL | + CHMDFE VARCHAR(50) |
| 1416 | ADD_COL | + XJUST VARCHAR(100) |

