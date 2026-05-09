---
table: FINANCEIRO_CHEQUE
module: financeiro
created_at_version: 9
last_modified_version: 414
target_version: 1468
columns_count: 21
foreign_keys_count: 2
foreign_keys:
  CODBANCO: BANCOS
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_CHEQUE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 9;
- **Última mudança:** UPDATE 414;
- **Total colunas (versão 1468):** 21

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v9 | v9 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL |  | v9 | v9 |
| 3 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | → `EMPRESA` | v9 | v9 |
| 4 | `CODBANCO` | `INTEGER` | NULL | → `BANCOS` | v9 | v9 |
| 5 | `BANCO` | `VARCHAR(50)` | NULL |  | v9 | v9 |
| 6 | `NOME` | `VARCHAR(50)` | NULL |  | v9 | v9 |
| 7 | `REPASSADO` | `VARCHAR(50)` | NULL |  | v9 | v9 |
| 8 | `CNPJCPF` | `VARCHAR(18)` | NULL |  | v9 | v9 |
| 9 | `STATUS` | `VARCHAR(10)` | NULL |  | v9 | v9 |
| 10 | `COMPE` | `INTEGER` | NULL |  | v9 | v9 |
| 11 | `AGENCIA` | `INTEGER` | NULL |  | v9 | v9 |
| 12 | `C1` | `VARCHAR(1)` | NULL |  | v9 | v9 |
| 13 | `CONTA` | `VARCHAR(15)` | NULL |  | v9 | v9 |
| 14 | `C2` | `VARCHAR(1)` | NULL |  | v9 | v9 |
| 15 | `C3` | `VARCHAR(1)` | NULL |  | v9 | v9 |
| 16 | `DT_CADASTRO` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 17 | `DT_REPASSADO` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 18 | `TIPO` | `VARCHAR(1)` | NULL |  | v9 | v9 |
| 19 | `DEVOLVIDO` | `CHAR(1)` | NULL |  | v9 | v9 |
| 20 | `MOTIVO` | `VARCHAR(50)` | NULL |  | v9 | v9 |
| 21 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 9 | CREATE | CREATE TABLE com 21 colunas |
| 9 | DROP_COL | - NUMERO |
| 28 | DROP_COL | - NUMERO |
| 30 | DROP_COL | - NUMERO |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 414 | DROP_COL | - DT_BOM_PARA |

