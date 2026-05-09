---
table: PRODUTO_GRUPO_IMPOSTOUF
module: estoque
created_at_version: 79
last_modified_version: 662
target_version: 1468
columns_count: 11
foreign_keys_count: 2
foreign_keys:
  CODNF_NATUREZA_OPERACAO: NF_NATUREZA_OPERACAO
  CODPRODUTO_GRUPO: PRODUTO_GRUPO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_GRUPO_IMPOSTOUF`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 79;
- **Última mudança:** UPDATE 662;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_NATUREZA_OPERACAO` | [`NF_NATUREZA_OPERACAO`](../../nfe/tabelas/NF_NATUREZA_OPERACAO.md) |
| `CODPRODUTO_GRUPO` | [`PRODUTO_GRUPO`](../../estoque/tabelas/PRODUTO_GRUPO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO_GRUPO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO_GRUPO` | v79 | v79 |
| 2 | `ESTADO` | `VARCHAR(2)` | NOT NULL |  | v79 | v79 |
| 3 | `MVA` | `DOUBLE PRECISION` | NULL |  | v79 | v79 |
| 4 | `PRECO_PAUTA` | `DOUBLE PRECISION` | NULL |  | v79 | v79 |
| 5 | `PICMSST` | `DOUBLE PRECISION` | NULL |  | v82 | v82 |
| 6 | `PICMS` | `DOUBLE PRECISION` | NULL |  | v83 | v83 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 8 | `PREDBCST` | `double precision` | NULL |  | v222 | v222 |
| 9 | `PFCP` | `DOUBLE PRECISION` | NULL |  | v406 | v406 |
| 10 | `PREDMVA_SIMPLES` | `DOUBLE PRECISION` | NULL |  | v439 | v439 |
| 11 | `CODNF_NATUREZA_OPERACAO` | `INTEGER` | NULL | → `NF_NATUREZA_OPERACAO` | v662 | v662 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 79 | CREATE | CREATE TABLE com 4 colunas |
| 82 | ADD_COL | + PICMSST DOUBLE PRECISION |
| 83 | ADD_COL | + PICMS DOUBLE PRECISION |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 222 | ADD_COL | + PREDBCST double precision |
| 406 | ADD_COL | + PFCP DOUBLE PRECISION |
| 439 | ADD_COL | + PREDMVA_SIMPLES DOUBLE PRECISION |
| 662 | ADD_COL | + CODNF_NATUREZA_OPERACAO INTEGER |

