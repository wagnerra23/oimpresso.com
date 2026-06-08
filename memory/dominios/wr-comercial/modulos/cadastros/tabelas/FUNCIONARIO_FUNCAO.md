---
table: FUNCIONARIO_FUNCAO
module: cadastros
created_at_version: 174
last_modified_version: 1372
target_version: 1468
columns_count: 3
foreign_keys_count: 1
foreign_keys:
  CODCENTRO_CUSTO: CENTRO_CUSTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO_FUNCAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 174;
- **Última mudança:** UPDATE 1372;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_CUSTO` | [`CENTRO_CUSTO`](../../producao/tabelas/CENTRO_CUSTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `timestamp` | NULL |  | v174 | v174 |
| 2 | `CODCENTRO_CUSTO` | `VARCHAR(50)` | NULL | → `CENTRO_CUSTO` | v1372 | v1372 |
| 3 | `PERCENTUAL` | `DOUBLE PRECISION` | NULL |  | v1372 | v1372 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 174 | ADD_COL | + DT_ALTERACAO timestamp |
| 1372 | ADD_COL | + CODCENTRO_CUSTO VARCHAR(50) |
| 1372 | ADD_COL | + PERCENTUAL DOUBLE PRECISION |

