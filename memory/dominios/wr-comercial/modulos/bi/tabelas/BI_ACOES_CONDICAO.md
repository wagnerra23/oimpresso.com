---
table: BI_ACOES_CONDICAO
module: bi
created_at_version: 1265
last_modified_version: 1265
target_version: 1468
columns_count: 11
foreign_keys_count: 1
foreign_keys:
  CODBI_ACOES: BI_ACOES
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BI_ACOES_CONDICAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1265;
- **Última mudança:** UPDATE 1265;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBI_ACOES` | [`BI_ACOES`](../../bi/tabelas/BI_ACOES.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1265 | v1265 |
| 2 | `CODBI_ACOES` | `INTEGER` | NULL | → `BI_ACOES` | v1265 | v1265 |
| 3 | `CAMPO_BASE` | `VARCHAR(50)` | NULL |  | v1265 | v1265 |
| 4 | `COMPARADOR` | `VARCHAR(50)` | NULL |  | v1265 | v1265 |
| 5 | `TIPO_VALOR` | `VARCHAR(1)` | NULL |  | v1265 | v1265 |
| 6 | `CAMPO_COMPARADO` | `VARCHAR(100)` | NULL |  | v1264 | v1264 |
| 7 | `VALOR_COMPARADO` | `VARCHAR(50)` | NULL |  | v1265 | v1265 |
| 8 | `IS_PADRAO` | `VARCHAR(1)` | NULL |  | v1264 | v1264 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1265 | v1265 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1265 | v1265 |
| 11 | `ACAO` | `VARCHAR(50)` | NULL |  | v1264 | v1264 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1264 | CREATE | CREATE TABLE com 11 colunas |
| 1265 | DROP_TABLE | DROP TABLE |
| 1265 | CREATE | CREATE TABLE com 8 colunas |

