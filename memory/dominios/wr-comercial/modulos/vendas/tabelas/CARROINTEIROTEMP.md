---
table: CARROINTEIROTEMP
module: vendas
created_at_version: 15
last_modified_version: 758
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODCARRO: CARRO
  CODLOCAL: LOCAL
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CARROINTEIROTEMP`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 15;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCARRO` | [`CARRO`](../../vendas/tabelas/CARRO.md) |
| `CODLOCAL` | [`LOCAL`](../../cadastros/tabelas/LOCAL.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODLOCAL` | `SMALLINT` | NOT NULL | → `LOCAL` | v15 | v15 |
| 2 | `LOCAL` | `VARCHAR(30)` | NULL |  | v15 | v15 |
| 3 | `CODCARRO` | `SMALLINT` | NOT NULL | → `CARRO` | v758 | v758 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 15 | CREATE | CREATE TABLE com 3 colunas |
| 758 | ADD_COL | + CODCARRO SMALLINT |
| 758 | DROP_COL | - CODCARROINTEIRO |

