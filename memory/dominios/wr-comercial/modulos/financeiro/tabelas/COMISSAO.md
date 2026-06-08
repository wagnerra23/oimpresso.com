---
table: COMISSAO
module: financeiro
created_at_version: 12
last_modified_version: 1225
target_version: 1468
columns_count: 20
foreign_keys_count: 2
foreign_keys:
  CODCOMISSAO_MIGRACAO: COMISSAO
  CODFINANCEIRO_GERADO: FINANCEIRO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMISSAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 1225;
- **Total colunas (versão 1468):** 20

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCOMISSAO_MIGRACAO` | [`COMISSAO`](../../financeiro/tabelas/COMISSAO.md) |
| `CODFINANCEIRO_GERADO` | [`FINANCEIRO`](../../financeiro/tabelas/FINANCEIRO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v12 | v12 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v12 | v12 |
| 3 | `DATA` | `DATE` | NULL |  | v12 | v12 |
| 4 | `DT_FINANCEIRO` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 5 | `DT_COMISSAO_GERADA` | `TIMESTAMP` | NULL |  | v12 | v12 |
| 6 | `TIPO_FINANCEIRO` | `VARCHAR(30)` | NULL |  | v484 | v484 |
| 7 | `TIPO` | `VARCHAR(50)` | NULL |  | v1216 | v1216 |
| 8 | `TIPO_DATA` | `VARCHAR(30)` | NULL |  | v485 | v485 |
| 9 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v485 | v485 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1013 | v1013 |
| 11 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v1216 | v1216 |
| 12 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v1216 | v1216 |
| 13 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v1216 | v1216 |
| 14 | `VALOR_COMISSAO` | `DOUBLE PRECISION` | NULL |  | v1216 | v1216 |
| 15 | `SOMA_FINANCEIRO_VENCIDA` | `DOUBLE PRECISION` | NULL |  | v1216 | v1216 |
| 16 | `SOMA_FINANCEIRO_EMABERTO` | `DOUBLE PRECISION` | NULL |  | v1216 | v1216 |
| 17 | `SOMA_FINANCEIRO_QUITADA` | `DOUBLE PRECISION` | NULL |  | v1216 | v1216 |
| 18 | `CODCOMISSAO_MIGRACAO` | `INTEGER` | NULL | → `COMISSAO` | v1216 | v1216 |
| 19 | `CODFINANCEIRO_GERADO` | `INTEGER` | NULL | → `FINANCEIRO` | v1225 | v1225 |
| 20 | `OBSERVACAO` | `VARCHAR(1000)` | NULL |  | v1225 | v1225 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | CREATE | CREATE TABLE com 5 colunas |
| 484 | ADD_COL | + TIPO_FINANCEIRO VARCHAR(30) |
| 484 | ADD_COL | + TIPO VARCHAR(30) |
| 485 | ADD_COL | + TIPO_DATA VARCHAR(30) |
| 485 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 524 | ADD_COL | + CASCATA VARCHAR(1) |
| 758 | DROP_COL | - CASCATA |
| 1013 | ADD_COL | + ATIVO VARCHAR(1) |
| 1216 | ADD_COL | + PESSOA_RESPONSAVEL_CODIGO VARCHAR(10) |
| 1216 | ADD_COL | + PESSOA_RESPONSAVEL_TIPO VARCHAR(3) |
| 1216 | ADD_COL | + PESSOA_RESPONSAVEL_SEQUENCIA INTEGER |
| 1216 | ADD_COL | + VALOR_COMISSAO DOUBLE PRECISION |
| 1216 | ADD_COL | + SOMA_FINANCEIRO_VENCIDA DOUBLE PRECISION |
| 1216 | ADD_COL | + SOMA_FINANCEIRO_EMABERTO DOUBLE PRECISION |
| 1216 | ADD_COL | + SOMA_FINANCEIRO_QUITADA DOUBLE PRECISION |
| 1216 | ADD_COL | + CODCOMISSAO_MIGRACAO INTEGER |
| 1216 | ADD_COL | + TIPO VARCHAR(50) |
| 1225 | ADD_COL | + CODFINANCEIRO_GERADO INTEGER |
| 1225 | ADD_COL | + OBSERVACAO VARCHAR(1000) |

