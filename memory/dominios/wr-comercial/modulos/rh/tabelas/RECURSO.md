---
table: RECURSO
module: rh
created_at_version: 589
last_modified_version: 1005
target_version: 1468
columns_count: 11
foreign_keys_count: 3
foreign_keys:
  CODPESSOA: PESSOAS
  CODPRODUTO: PRODUTO
  CODTEMPO_TRABALHO: TEMPO_TRABALHO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `RECURSO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 589;
- **Última mudança:** UPDATE 1005;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODTEMPO_TRABALHO` | [`TEMPO_TRABALHO`](../../rh/tabelas/TEMPO_TRABALHO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v589 | v589 |
| 2 | `TIPO` | `VARCHAR(20)` | NULL |  | v589 | v589 |
| 3 | `CODPESSOA` | `VARCHAR(10)` | NULL | → `PESSOAS` | v589 | v589 |
| 4 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v589 | v589 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v589 | v589 |
| 6 | `CUSTO_HORA` | `DOUBLE PRECISION` | NULL |  | v701 | v701 |
| 7 | `CODTEMPO_TRABALHO` | `INTEGER` | NULL | → `TEMPO_TRABALHO` | v702 | v702 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v729 | v729 |
| 9 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v1005 | v1005 |
| 10 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v1005 | v1005 |
| 11 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v1005 | v1005 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 589 | CREATE | CREATE TABLE com 5 colunas |
| 701 | ADD_COL | + CUSTO_HORA DOUBLE PRECISION |
| 702 | ADD_COL | + CODTEMPO_TRABALHO INTEGER |
| 729 | ADD_COL | + ATIVO VARCHAR(1) |
| 1005 | ADD_COL | + PESSOA_RESPONSAVEL_CODIGO VARCHAR(10) |
| 1005 | ADD_COL | + PESSOA_RESPONSAVEL_TIPO VARCHAR(3) |
| 1005 | ADD_COL | + PESSOA_RESPONSAVEL_SEQUENCIA INTEGER |

