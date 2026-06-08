---
table: CARROINTEIROVALOR
module: vendas
created_at_version: 15
last_modified_version: 758
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODCARRO: CARRO
  CODCARROINTEIRO: CARROINTEIRO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CARROINTEIROVALOR`

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
| `CODCARROINTEIRO` | [`CARROINTEIRO`](../../vendas/tabelas/CARROINTEIRO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODCARROINTEIRO` | `SMALLINT` | NOT NULL | → `CARROINTEIRO` | v15 | v15 |
| 2 | `CODCARRO` | `INTEGER` | NOT NULL | → `CARRO` | v15 | v758 |
| 3 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v15 | v15 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 15 | CREATE | CREATE TABLE com 3 colunas |
| 758 | ALTER_TYPE | ~ CODCARRO TYPE INTEGER |

