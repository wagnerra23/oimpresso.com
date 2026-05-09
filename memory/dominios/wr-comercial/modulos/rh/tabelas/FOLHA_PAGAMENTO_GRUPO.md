---
table: FOLHA_PAGAMENTO_GRUPO
module: rh
created_at_version: 310
last_modified_version: 827
target_version: 1468
columns_count: 9
foreign_keys_count: 2
foreign_keys:
  CODCONTA: CONTAS
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FOLHA_PAGAMENTO_GRUPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 310;
- **Última mudança:** UPDATE 827;
- **Total colunas (versão 1468):** 9

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v310 | v310 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v310 | v310 |
| 3 | `CODPLANOCONTAS` | `VARCHAR(30) CHARACTER SET WIN1252` | NULL | → `PLANOCONTAS` | v310 | v499 |
| 4 | `PESSOA_FORNECEDOR_CODIGO` | `VARCHAR(10)` | NULL |  | v310 | v310 |
| 5 | `PESSOA_FORNECEDOR_TIPO` | `VARCHAR(3)` | NULL |  | v310 | v310 |
| 6 | `PESSOA_FORNECEDOR_SEQUENCIA` | `INTEGER` | NULL |  | v310 | v310 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v310 | v310 |
| 8 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v310 | v310 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v827 | v827 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 310 | CREATE | CREATE TABLE com 9 colunas |
| 349 | ADD_COL | + INCLUIRNOUNICO VARCHAR(1) |
| 370 | DROP_COL | - INCLUIRNOUNICO |
| 371 | DROP_COL | - TIPO |
| 371 | DROP_COL | - GRUPO_VALE |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 827 | ADD_COL | + ATIVO VARCHAR(1) |

