---
table: NF_ENTRADA_PRODUTOS_AFETADOS
module: nfe
created_at_version: 725
last_modified_version: 887
target_version: 1468
columns_count: 10
foreign_keys_count: 3
foreign_keys:
  CODNF_ENTRADA: NF_ENTRADA
  CODNF_ENTRADA_PRODUTOS: NF_ENTRADA_PRODUTOS
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_PRODUTOS_AFETADOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 725;
- **Última mudança:** UPDATE 887;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODNF_ENTRADA_PRODUTOS` | [`NF_ENTRADA_PRODUTOS`](../../nfe/tabelas/NF_ENTRADA_PRODUTOS.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v725 | v725 |
| 2 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | → `NF_ENTRADA` | v725 | v725 |
| 3 | `CODNF_ENTRADA_PRODUTOS` | `INTEGER` | NOT NULL | → `NF_ENTRADA_PRODUTOS` | v725 | v725 |
| 4 | `PARENT` | `INTEGER` | NULL |  | v725 | v725 |
| 5 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v725 | v725 |
| 6 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v725 | v725 |
| 7 | `ATUALIZAR_VALOR` | `DOM_BOOLEAN` | NULL |  | v725 | v725 |
| 8 | `CUSTO_ANTERIOR` | `DOUBLE PRECISION` | NULL |  | v887 | v887 |
| 9 | `VALOR_ANTERIOR` | `DOUBLE PRECISION` | NULL |  | v887 | v887 |
| 10 | `CUSTO` | `DOUBLE PRECISION` | NULL |  | v725 | v887 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 725 | CREATE | CREATE TABLE com 8 colunas |
| 887 | ADD_COL | + CUSTO_ANTERIOR DOUBLE PRECISION |
| 887 | ADD_COL | + VALOR_ANTERIOR DOUBLE PRECISION |
| 887 | RENAME_COL | × CUSTO_VENDA → CUSTO |
| 887 | ADD_COL | + CUSTO_ANTERIOR DOUBLE PRECISION |
| 887 | ADD_COL | + VALOR_ANTERIOR DOUBLE PRECISION |

