---
table: PRODUCAO_CENTRO_TRABALHO
module: producao
created_at_version: 519
last_modified_version: 832
target_version: 1468
columns_count: 10
foreign_keys_count: 4
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUCAO: PRODUCAO
  CODPRODUCAO_OS: PRODUCAO_OS
  CODVENDA: VENDA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_CENTRO_TRABALHO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 519;
- **Última mudança:** UPDATE 832;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODPRODUCAO_OS` | [`PRODUCAO_OS`](../../producao/tabelas/PRODUCAO_OS.md) |
| `CODVENDA` | [`VENDA`](../../vendas/tabelas/VENDA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v519 | v519 |
| 2 | `CODVENDA` | `VARCHAR(10)` | NULL | → `VENDA` | v519 | v519 |
| 3 | `CODPRODUCAO` | `INTEGER` | NOT NULL | → `PRODUCAO` | v519 | v519 |
| 4 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | → `CENTRO_TRABALHO` | v519 | v519 |
| 5 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v519 | v519 |
| 6 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v519 | v519 |
| 7 | `CODPRODUCAO_OS` | `INTEGER` | NULL | → `PRODUCAO_OS` | v519 | v519 |
| 8 | `PRE_REQUISITO_CENTRO_TRABALHO` | `INTEGER` | NULL |  | v531 | v531 |
| 9 | `CUSTO` | `DOUBLE PRECISION` | NULL |  | v715 | v832 |
| 10 | `TEMPO_TOTAL` | `DOUBLE PRECISION` | NULL |  | v519 | v832 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 519 | CREATE | CREATE TABLE com 6 colunas |
| 519 | ADD_COL | + DT_INICIO TIMESTAMP |
| 519 | ADD_COL | + CODPRODUCAO_OS INTEGER |
| 531 | ADD_COL | + PRE_REQUISITO_CENTRO_TRABALHO INTEGER |
| 715 | ADD_COL | + CUSTO_VENDA DOUBLE PRECISION |
| 832 | RENAME_COL | × CUSTO_VENDA → CUSTO |
| 832 | RENAME_COL | × TEMPO → TEMPO_TOTAL |

