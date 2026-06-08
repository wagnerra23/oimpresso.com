---
table: CONCILIACAO_BANCARIA
module: financeiro
created_at_version: 284
last_modified_version: 1130
target_version: 1468
columns_count: 16
foreign_keys_count: 2
foreign_keys:
  CODBANCO: BANCOS
  CODCONTA: CONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONCILIACAO_BANCARIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 284;
- **Última mudança:** UPDATE 1130;
- **Total colunas (versão 1468):** 16

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v284 | v284 |
| 2 | `CODBANCO` | `INTEGER` | NULL | → `BANCOS` | v284 | v284 |
| 3 | `CONTA` | `VARCHAR(20)` | NULL |  | v284 | v284 |
| 4 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v284 | v284 |
| 5 | `DT_FINANCEIRO` | `TIMESTAMP` | NULL |  | v284 | v284 |
| 6 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v284 | v284 |
| 7 | `DT_FIM` | `TIMESTAMP` | NULL |  | v284 | v284 |
| 8 | `DT_ARQUIVO` | `TIMESTAMP` | NULL |  | v284 | v284 |
| 9 | `BALANCO_INICIAL` | `DOUBLE PRECISION` | NULL |  | v284 | v284 |
| 10 | `BALANCO_FINAL` | `DOUBLE PRECISION` | NULL |  | v284 | v284 |
| 11 | `ACAO` | `INTEGER` | NULL |  | v1119 | v1119 |
| 12 | `TIPO` | `VARCHAR(15)` | NULL |  | v1130 | v1130 |
| 13 | `NOME_ARQUIVO` | `VARCHAR(255)` | NULL |  | v1130 | v1130 |
| 14 | `CODCONTA` | `INTEGER` | NOT NULL | → `CONTAS` | v1130 | v1130 |
| 15 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1130 | v1130 |
| 16 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1130 | v1130 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 284 | CREATE | CREATE TABLE com 10 colunas |
| 1119 | ADD_COL | + ACAO INTEGER |
| 1130 | ADD_COL | + TIPO VARCHAR(15) |
| 1130 | ADD_COL | + NOME_ARQUIVO VARCHAR(255) |
| 1130 | ADD_COL | + CODCONTA INTEGER |
| 1130 | ADD_COL | + ATIVO VARCHAR(1) |
| 1130 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

