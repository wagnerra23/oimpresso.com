---
table: CENTRO_TRABALHO_AUSENCIA
module: producao
created_at_version: 565
last_modified_version: 565
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

# `CENTRO_TRABALHO_AUSENCIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 565;
- **Última mudança:** UPDATE 565;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v565 | v565 |
| 2 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v565 | v565 |
| 3 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v565 | v565 |
| 4 | `DATA_INICIO` | `TIMESTAMP` | NULL |  | v565 | v565 |
| 5 | `DATA_FIM` | `TIMESTAMP` | NULL |  | v565 | v565 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v565 | v565 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 565 | CREATE | CREATE TABLE com 6 colunas |

