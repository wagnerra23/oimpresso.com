---
table: COMPETENCIA
module: configuracao
created_at_version: 1295
last_modified_version: 1295
target_version: 1468
columns_count: 5
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMPETENCIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1295;
- **Última mudança:** UPDATE 1295;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1295 | v1295 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1295 | v1295 |
| 3 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v1295 | v1295 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1295 | v1295 |
| 5 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1295 | v1295 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 605 | CREATE | CREATE TABLE com 5 colunas |
| 805 | CREATE | CREATE TABLE com 5 colunas |
| 1295 | CREATE | CREATE TABLE com 5 colunas |

