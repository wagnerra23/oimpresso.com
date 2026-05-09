---
table: PRODUCAO_ROTEIRO_ORGANOGRAMA
module: producao
created_at_version: 821
last_modified_version: 822
target_version: 1468
columns_count: 15
foreign_keys_count: 2
foreign_keys:
  CODPRODUCAO_ROTEIRO: PRODUCAO_ROTEIRO
  CODPRODUCAO_ROTEIRO_PERGUNTA: PRODUCAO_ROTEIRO_PERGUNTA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_ROTEIRO_ORGANOGRAMA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 821;
- **Última mudança:** UPDATE 822;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUCAO_ROTEIRO` | [`PRODUCAO_ROTEIRO`](../../producao/tabelas/PRODUCAO_ROTEIRO.md) |
| `CODPRODUCAO_ROTEIRO_PERGUNTA` | [`PRODUCAO_ROTEIRO_PERGUNTA`](../../producao/tabelas/PRODUCAO_ROTEIRO_PERGUNTA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v821 | v821 |
| 2 | `CODPRODUCAO_ROTEIRO` | `INTEGER` | NOT NULL | → `PRODUCAO_ROTEIRO` | v821 | v821 |
| 3 | `PARENT` | `INTEGER` | NULL |  | v821 | v821 |
| 4 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v821 | v821 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v821 | v821 |
| 6 | `RESPONSAVEL` | `VARCHAR(150)` | NULL |  | v821 | v821 |
| 7 | `WIDTH` | `INTEGER` | NULL |  | v821 | v821 |
| 8 | `HEIGHT` | `INTEGER` | NULL |  | v821 | v821 |
| 9 | `TIPO` | `VARCHAR(20)` | NULL |  | v821 | v821 |
| 10 | `COR` | `INTEGER` | NULL |  | v821 | v821 |
| 11 | `IMAGEM` | `INTEGER` | NULL |  | v821 | v821 |
| 12 | `IMAGEM_ALINHAMENTO` | `VARCHAR(20)` | NULL |  | v821 | v821 |
| 13 | `ORDEM` | `INTEGER` | NULL |  | v821 | v821 |
| 14 | `ALINHAMENTO` | `VARCHAR(20)` | NULL |  | v821 | v821 |
| 15 | `CODPRODUCAO_ROTEIRO_PERGUNTA` | `INTEGER` | NULL | → `PRODUCAO_ROTEIRO_PERGUNTA` | v821 | v822 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 821 | CREATE | CREATE TABLE com 15 colunas |
| 822 | RENAME_COL | × CODPRODUCAO_PERGUNTA → CODPRODUCAO_ROTEIRO_PERGUNTA |

