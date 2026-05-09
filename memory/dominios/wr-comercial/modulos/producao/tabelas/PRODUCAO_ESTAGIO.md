---
table: PRODUCAO_ESTAGIO
module: producao
created_at_version: 1029
last_modified_version: 1362
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_ESTAGIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1029;
- **Última mudança:** UPDATE 1362;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1029 | v1029 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1029 | v1029 |
| 3 | `ICONE` | `INTEGER` | NULL |  | v1029 | v1029 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1029 | v1029 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1029 | v1029 |
| 6 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v1362 | v1362 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1029 | CREATE | CREATE TABLE com 5 colunas |
| 1362 | ADD_COL | + CODCENTRO_TRABALHO INTEGER |

