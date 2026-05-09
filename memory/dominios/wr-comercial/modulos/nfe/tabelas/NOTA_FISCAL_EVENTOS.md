---
table: NOTA_FISCAL_EVENTOS
module: nfe
created_at_version: 1440
last_modified_version: 1452
target_version: 1468
columns_count: 32
foreign_keys_count: 3
foreign_keys:
  CODEMPRESA: EMPRESA
  CODNOTA_FISCAL: NOTA_FISCAL
  CODVENDA: VENDA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NOTA_FISCAL_EVENTOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1440;
- **Última mudança:** UPDATE 1452;
- **Total colunas (versão 1468):** 32

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODNOTA_FISCAL` | [`NOTA_FISCAL`](../../nfe/tabelas/NOTA_FISCAL.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1440 | v1440 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1440 | v1440 |
| 3 | `TIPO` | `VARCHAR(10)` | NULL |  | v1440 | v1440 |
| 4 | `ARQUIVO_XML_ENVIO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v1440 | v1440 |
| 5 | `ARQUIVO_XML_RETORNO` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v1440 | v1440 |
| 6 | `NF_CHAVE` | `VARCHAR(44)` | NULL |  | v1440 | v1440 |
| 7 | `NF_NUMERO` | `VARCHAR(20)` | NULL |  | v1440 | v1440 |
| 8 | `NF_SERIE` | `VARCHAR(20)` | NULL |  | v1440 | v1440 |
| 9 | `NF_LOTE` | `VARCHAR(20)` | NULL |  | v1440 | v1440 |
| 10 | `NF_ID_NOTA` | `VARCHAR(20)` | NULL |  | v1440 | v1440 |
| 11 | `NF_PROTOCOLO` | `VARCHAR(50)` | NULL |  | v1440 | v1440 |
| 12 | `NF_TIPO` | `INTEGER` | NULL |  | v1440 | v1440 |
| 13 | `NF_LINK` | `VARCHAR(100)` | NULL |  | v1440 | v1440 |
| 14 | `NF_CNPJCPF_EMITENTE` | `VARCHAR(18)` | NULL |  | v1440 | v1440 |
| 15 | `NF_AMBIENTE` | `INTEGER` | NULL |  | v1440 | v1440 |
| 16 | `NF_RAZAOSOCIAL_EMITENTE` | `VARCHAR(60)` | NULL |  | v1440 | v1440 |
| 17 | `NF_MANIFESTO` | `VARCHAR(30)` | NULL |  | v1440 | v1440 |
| 18 | `NF_DT_EMISSAO` | `TIMESTAMP` | NULL |  | v1440 | v1440 |
| 19 | `NF_TOTAL` | `DOUBLE PRECISION` | NULL |  | v1440 | v1440 |
| 20 | `NF_SITUACAO` | `VARCHAR(30)` | NULL |  | v1440 | v1440 |
| 21 | `NF_DESC_SITUACAO` | `VARCHAR(150)` | NULL |  | v1440 | v1440 |
| 22 | `DT_RECEBIMENTO` | `TIMESTAMP` | NULL |  | v1440 | v1440 |
| 23 | `SUCESSO` | `BOOLEAN` | NULL |  | v1440 | v1440 |
| 24 | `NSU` | `BIGINT` | NULL |  | v1440 | v1440 |
| 25 | `"SCHEMA"` | `VARCHAR(15)` | NULL |  | v1440 | v1440 |
| 26 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1440 | v1440 |
| 27 | `CODVENDA` | `VARCHAR(10)` | NULL | → `VENDA` | v1440 | v1440 |
| 28 | `TIPO_OPERACAO` | `VARCHAR(50)` | NULL |  | v1440 | v1440 |
| 29 | `NF_ERRO` | `VARCHAR(300)` | NULL |  | v1440 | v1440 |
| 30 | `NF_CODERRO` | `VARCHAR(15)` | NULL |  | v1440 | v1440 |
| 31 | `NF_CORRECAO` | `VARCHAR(300)` | NULL |  | v1440 | v1440 |
| 32 | `CODNOTA_FISCAL` | `INTEGER` | NULL | → `NOTA_FISCAL` | v1452 | v1452 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1052 | CREATE | CREATE TABLE com 28 colunas |
| 1081 | ADD_COL | + NF_ERRO VARCHAR(300) |
| 1081 | ADD_COL | + NF_CODERRO VARCHAR(15) |
| 1081 | ADD_COL | + NF_CORRECAO VARCHAR(300) |
| 1440 | CREATE | CREATE TABLE com 28 colunas |
| 1440 | ADD_COL | + NF_ERRO VARCHAR(300) |
| 1440 | ADD_COL | + NF_CODERRO VARCHAR(15) |
| 1440 | ADD_COL | + NF_CORRECAO VARCHAR(300) |
| 1452 | ADD_COL | + CODNOTA_FISCAL INTEGER |

