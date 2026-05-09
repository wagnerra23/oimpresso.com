---
table: NOTA_FISCAL_ENTRADA
module: nfe
created_at_version: 611
last_modified_version: 1211
target_version: 1468
columns_count: 23
foreign_keys_count: 2
foreign_keys:
  CODEMPRESA: EMPRESA
  CODNF_ENTRADA: NF_ENTRADA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NOTA_FISCAL_ENTRADA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 611;
- **Última mudança:** UPDATE 1211;
- **Total colunas (versão 1468):** 23

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v611 | v611 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v611 | v611 |
| 3 | `ARQUIVO_XML` | `BLOB SUB_TYPE 0 SEGMENT SIZE 80` | NULL |  | v611 | v611 |
| 4 | `NF_NUMERO` | `BIGINT` | NULL |  | v611 | v611 |
| 5 | `NF_CHAVE` | `VARCHAR(44)` | NULL |  | v611 | v611 |
| 6 | `NF_CNPJCPF_EMITENTE` | `VARCHAR(18)` | NULL |  | v611 | v611 |
| 7 | `NF_AMBIENTE` | `INTEGER` | NULL |  | v611 | v611 |
| 8 | `NF_RAZAOSOCIAL_EMITENTE` | `VARCHAR(60)` | NULL |  | v611 | v611 |
| 9 | `NF_MANIFESTO` | `VARCHAR(30)` | NULL |  | v611 | v611 |
| 10 | `NF_DT_EMISSAO` | `TIMESTAMP` | NULL |  | v611 | v611 |
| 11 | `DT_RECEBIMENTO` | `TIMESTAMP` | NULL |  | v611 | v611 |
| 12 | `NF_TOTAL` | `DOUBLE PRECISION` | NULL |  | v611 | v611 |
| 13 | `NF_SITUACAO` | `VARCHAR(30)` | NULL |  | v611 | v611 |
| 14 | `NSU` | `BIGINT` | NULL |  | v611 | v611 |
| 15 | `"SCHEMA"` | `VARCHAR(15)` | NULL |  | v611 | v611 |
| 16 | `ATIVO` | `VARCHAR(1)` | NULL |  | v783 | v783 |
| 17 | `TEM_COMPRA` | `VARCHAR(1)` | NULL |  | v1016 | v1016 |
| 18 | `CODNF_ENTRADA` | `VARCHAR(10)` | NULL | → `NF_ENTRADA` | v1016 | v1016 |
| 19 | `TEM_CIENCIA` | `VARCHAR(1)` | NULL |  | v1016 | v1016 |
| 20 | `TIPO_OPERACAO` | `VARCHAR(50)` | NULL |  | v1019 | v1019 |
| 21 | `CODNOTA_COMPLETA` | `INTEGER` | NULL |  | v1019 | v1019 |
| 22 | `TIPO` | `VARCHAR(10)` | NULL |  | v1199 | v1199 |
| 23 | `SITUACAO` | `VARCHAR(50)` | NULL |  | v1211 | v1211 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 611 | CREATE | CREATE TABLE com 15 colunas |
| 783 | ADD_COL | + ATIVO VARCHAR(1) |
| 1016 | ADD_COL | + TEM_COMPRA VARCHAR(1) |
| 1016 | ADD_COL | + CODNF_ENTRADA VARCHAR(10) |
| 1016 | ADD_COL | + TEM_CIENCIA VARCHAR(1) |
| 1019 | ADD_COL | + TIPO_OPERACAO VARCHAR(50) |
| 1019 | ADD_COL | + CODNOTA_COMPLETA INTEGER |
| 1199 | ADD_COL | + TIPO VARCHAR(10) |
| 1211 | ADD_COL | + SITUACAO VARCHAR(50) |

