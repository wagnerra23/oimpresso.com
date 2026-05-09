---
table: COMISSAO_PESSOA
module: financeiro
created_at_version: 484
last_modified_version: 1216
target_version: 1468
columns_count: 13
foreign_keys_count: 1
foreign_keys:
  CODCOMISSAO: COMISSAO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMISSAO_PESSOA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 484;
- **Última mudança:** UPDATE 1216;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCOMISSAO` | [`COMISSAO`](../../financeiro/tabelas/COMISSAO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v484 | v484 |
| 2 | `CODCOMISSAO` | `INTEGER` | NOT NULL | → `COMISSAO` | v484 | v484 |
| 3 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v484 | v484 |
| 4 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v484 | v484 |
| 5 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v484 | v484 |
| 6 | `VALOR_COMISSAO` | `DOUBLE PRECISION` | NULL |  | v484 | v484 |
| 7 | `GERA_COMISSAO` | `VARCHAR(1)` | NULL |  | v484 | v484 |
| 8 | `ACAO` | `VARCHAR(30)` | NULL |  | v1117 | v1117 |
| 9 | `REFERENCIA` | `VARCHAR(50), ADD DT_ALTERACAO TIMESTAMP, ADD ATIVO VARCHAR(1), ADD DT_FINANCEIRO TIMESTAMP, ADD TIPO_DATA VARCHAR(30), ADD TIPO_FINANCEIRO VARCHAR(30)` | NULL |  | v1209 | v1209 |
| 10 | `SOMA_FINANCEIRO_EMABERTO` | `DOUBLE PRECISION` | NULL |  | v484 | v1216 |
| 11 | `SOMA_FINANCEIRO_VENCIDA` | `DOUBLE PRECISION` | NULL |  | v484 | v1216 |
| 12 | `SOMA_FINANCEIRO_QUITADA` | `DOUBLE PRECISION` | NULL |  | v1117 | v1216 |
| 13 | `MIGROU` | `VARCHAR(1)` | NULL |  | v1216 | v1216 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 484 | CREATE | CREATE TABLE com 11 colunas |
| 1117 | ADD_COL | + VALOR_QUITADA DOUBLE PRECISION |
| 1117 | ADD_COL | + ACAO VARCHAR(30) |
| 1209 | ADD_COL | + REFERENCIA VARCHAR(50), ADD DT_ALTERACAO TIMESTAMP, ADD ATIVO VARCHAR(1), ADD DT_FINANCEIRO TIMESTAMP, ADD TIPO_DATA VARCHAR(30), ADD TIPO_FINANCEIRO VARCHAR(30) |
| 1216 | DROP_COL | - VALOR |
| 1216 | RENAME_COL | × VALOR_EMABERTO → SOMA_FINANCEIRO_EMABERTO |
| 1216 | RENAME_COL | × VALOR_VENCIDA → SOMA_FINANCEIRO_VENCIDA |
| 1216 | RENAME_COL | × VALOR_QUITADA → SOMA_FINANCEIRO_QUITADA |
| 1216 | DROP_COL | - VALOR_COMISSAO_APAGAR |
| 1216 | ADD_COL | + MIGROU VARCHAR(1) |

