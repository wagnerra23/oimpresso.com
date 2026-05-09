---
table: NF_ENTRADA_MANIFESTO_REQUISICAO
module: nfe
created_at_version: 1409
last_modified_version: 1412
target_version: 1468
columns_count: 13
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_MANIFESTO_REQUISICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1409;
- **Última mudança:** UPDATE 1412;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1409 | v1409 |
| 2 | `ATIVO` | `VARCHAR(1) DEFAULT 'S'` | NULL |  | v1409 | v1409 |
| 3 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1409 | v1409 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1409 | v1409 |
| 5 | `LOG` | `VARCHAR(2000)` | NULL |  | v1409 | v1409 |
| 6 | `QUANT_REGISTROS_ERRO` | `INTEGER DEFAULT 0` | NULL |  | v1409 | v1409 |
| 7 | `QUANT_REGISTROS_SUCESSO` | `INTEGER DEFAULT 0` | NULL |  | v1409 | v1409 |
| 8 | `TIPO_REQUISICAO` | `VARCHAR(50)` | NULL |  | v1409 | v1409 |
| 9 | `CSTAT` | `INTEGER` | NULL |  | v1409 | v1409 |
| 10 | `XMOTIVO` | `VARCHAR(500)` | NULL |  | v1409 | v1409 |
| 11 | `ULTIMO_NSU` | `BIGINT` | NULL |  | v1409 | v1409 |
| 12 | `MAX_NSU` | `BIGINT` | NULL |  | v1409 | v1409 |
| 13 | `RESUMO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v1412 | v1412 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1409 | CREATE | CREATE TABLE com 16 colunas |
| 1411 | ADD_COL | + DADOS_ZIP BLOB SUB_TYPE 0 SEGMENT SIZE 80 |
| 1412 | DROP_COL | - QUANT_REGISTROS_IGNORADOS |
| 1412 | ADD_COL | + RESUMO BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 1412 | DROP_COL | - DADOS_ZIP |
| 1412 | DROP_COL | - DT_CONSULTA |
| 1412 | DROP_COL | - SUCESSO_REQUISICAO |
| 1412 | DROP_COL | - COD_NF_ENTRADA_MANIFESTO |

