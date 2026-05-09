---
table: PRODUTO_ETAPA
module: estoque
created_at_version: 1229
last_modified_version: 1359
target_version: 1468
columns_count: 13
foreign_keys_count: 3
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUTO: PRODUTO
  CODPRODUTO_COMPOSICAO: PRODUTO_COMPOSICAO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_ETAPA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1229;
- **Última mudança:** UPDATE 1359;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_COMPOSICAO` | [`PRODUTO_COMPOSICAO`](../../estoque/tabelas/PRODUTO_COMPOSICAO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1229 | v1229 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1229 | v1229 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1229 | v1229 |
| 4 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1229 | v1229 |
| 5 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v1229 | v1229 |
| 6 | `TEMPO_HORAS` | `DOUBLE PRECISION` | NULL |  | v1229 | v1229 |
| 7 | `ORDEM` | `DOUBLE PRECISION` | NULL |  | v1229 | v1229 |
| 8 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v1229 | v1229 |
| 9 | `CODPRODUTO_COMPOSICAO` | `INTEGER` | NULL | → `PRODUTO_COMPOSICAO` | v1233 | v1233 |
| 10 | `CODETAPA_ORIGINAL` | `INTEGER` | NULL |  | v1233 | v1233 |
| 11 | `TEMPO_STRING` | `VARCHAR(150)` | NULL |  | v1233 | v1233 |
| 12 | `TEMPO_MINUTOS` | `INTEGER` | NULL |  | v1233 | v1233 |
| 13 | `DIAS_PRAZO` | `INTEGER` | NULL |  | v1359 | v1359 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1229 | CREATE | CREATE TABLE com 8 colunas |
| 1233 | ADD_COL | + CODPRODUTO_COMPOSICAO INTEGER |
| 1233 | ADD_COL | + CODETAPA_ORIGINAL INTEGER |
| 1233 | ADD_COL | + TEMPO_STRING VARCHAR(150) |
| 1233 | ADD_COL | + TEMPO_MINUTOS INTEGER |
| 1359 | ADD_COL | + DIAS_PRAZO INTEGER |

