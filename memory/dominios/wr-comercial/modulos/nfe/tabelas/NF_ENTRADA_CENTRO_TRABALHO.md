---
id: dominios-wr-comercial-modulos-nfe-tabelas-nf-entrada-centro-trabalho
table: NF_ENTRADA_CENTRO_TRABALHO
module: nfe
created_at_version: 659
last_modified_version: 751
target_version: 1468
columns_count: 8
foreign_keys_count: 4
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODNF_ENTRADA: NF_ENTRADA
  CODNF_ENTRADA_PRODUTO: NF_ENTRADA_PRODUTOS
  CODPRODUTO_CT_PRE_REQUISITO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_CENTRO_TRABALHO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 659;
- **Última mudança:** UPDATE 751;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODNF_ENTRADA_PRODUTO` | [`NF_ENTRADA_PRODUTOS`](../../nfe/tabelas/NF_ENTRADA_PRODUTOS.md) |
| `CODPRODUTO_CT_PRE_REQUISITO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v659 | v659 |
| 2 | `CODNF_ENTRADA_PRODUTO` | `INTEGER` | NOT NULL | → `NF_ENTRADA_PRODUTOS` | v659 | v659 |
| 3 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | → `NF_ENTRADA` | v659 | v659 |
| 4 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | → `CENTRO_TRABALHO` | v659 | v659 |
| 5 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v659 | v659 |
| 6 | `TEMPO` | `DOUBLE PRECISION` | NULL |  | v659 | v659 |
| 7 | `CODPRODUTO_CT_PRE_REQUISITO` | `INTEGER` | NULL | → `PRODUTO` | v659 | v659 |
| 8 | `SEQUENCIA` | `INTEGER` | NULL |  | v659 | v659 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 659 | CREATE | CREATE TABLE com 13 colunas |
| 675 | ADD_COL | + CUSTO_VENDA DOUBLE PRECISION |
| 675 | DROP_COL | - CUSTO |
| 751 | DROP_COL | - CUSTO_VENDA |
| 751 | DROP_COL | - CUSTO_EXTRA |
| 751 | DROP_COL | - CUSTO_EXTRA_TOTAL |
| 751 | DROP_COL | - MARGEM |
| 751 | DROP_COL | - VALOR |

