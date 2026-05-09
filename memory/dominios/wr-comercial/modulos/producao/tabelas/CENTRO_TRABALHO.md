---
table: CENTRO_TRABALHO
module: producao
created_at_version: 493
last_modified_version: 1306
target_version: 1468
columns_count: 23
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODTEMPO_TRABALHO: TEMPO_TRABALHO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CENTRO_TRABALHO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 493;
- **Última mudança:** UPDATE 1306;
- **Total colunas (versão 1468):** 23

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODTEMPO_TRABALHO` | [`TEMPO_TRABALHO`](../../rh/tabelas/TEMPO_TRABALHO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v493 | v493 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v493 | v493 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v493 | v493 |
| 4 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v493 | v493 |
| 5 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v493 | v493 |
| 6 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v493 | v493 |
| 7 | `PARENT` | `INTEGER` | NULL |  | v493 | v493 |
| 8 | `TIPO` | `VARCHAR(10)` | NULL |  | v493 | v493 |
| 9 | `DT_CADASTRO` | `TIMESTAMP` | NULL |  | v493 | v493 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL |  | v493 | v493 |
| 11 | `PLANEJAMENTO_AUTOMATICO` | `VARCHAR(1)` | NULL |  | v493 | v493 |
| 12 | `HORAS_MENSAL` | `DOUBLE PRECISION` | NULL |  | v493 | v493 |
| 13 | `HORAS_DIARIA` | `DOUBLE PRECISION` | NULL |  | v493 | v493 |
| 14 | `PRIVADO` | `VARCHAR(1)` | NULL |  | v537 | v537 |
| 15 | `CODTEMPO_TRABALHO` | `integer` | NULL | → `TEMPO_TRABALHO` | v565 | v565 |
| 16 | `ICONE` | `INTEGER` | NULL |  | v859 | v859 |
| 17 | `MENSAGEM_HISTORICO` | `VARCHAR(200)` | NULL |  | v966 | v966 |
| 18 | `TEM_TRAVA_CANCELAR` | `VARCHAR(1)` | NULL |  | v935 | v935 |
| 19 | `TEM_TRAVA_FINANCEIRO` | `VARCHAR(1)` | NULL |  | v935 | v935 |
| 20 | `SUBNIVEL` | `VARCHAR(50)` | NULL |  | v1035 | v1035 |
| 21 | `PODE_ORCAMENTO` | `VARCHAR(1)` | NULL |  | v1070 | v1070 |
| 22 | `MIGRADO_SLA` | `VARCHAR(1)` | NULL |  | v1239 | v1239 |
| 23 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1306 | v1306 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 493 | CREATE | CREATE TABLE com 14 colunas |
| 537 | ADD_COL | + PRIVADO VARCHAR(1) |
| 565 | ADD_COL | + CODTEMPO_TRABALHO integer |
| 632 | ADD_COL | + CUSTO DOUBLE PRECISION |
| 675 | RENAME_COL | × CUSTO → CUSTO_VENDA |
| 758 | DROP_COL | - VALOR |
| 758 | DROP_COL | - CUSTO_VENDA |
| 859 | ADD_COL | + ICONE INTEGER |
| 905 | ADD_COL | + MENSAGEM_HISTORICO VARCHAR(200) |
| 935 | ADD_COL | + TEM_TRAVA_CANCELAR VARCHAR(1) |
| 935 | ADD_COL | + TEM_TRAVA_FINANCEIRO VARCHAR(1) |
| 966 | ADD_COL | + MENSAGEM_HISTORICO VARCHAR(200) |
| 1035 | ADD_COL | + SUBNIVEL VARCHAR(50) |
| 1070 | ADD_COL | + PODE_ORCAMENTO VARCHAR(1) |
| 1239 | ADD_COL | + MIGRADO_SLA VARCHAR(1) |
| 1306 | ADD_COL | + CODPRODUTO VARCHAR(15) |

