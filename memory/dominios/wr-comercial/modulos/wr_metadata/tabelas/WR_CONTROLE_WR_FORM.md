---
table: WR_CONTROLE_WR_FORM
module: wr_metadata
created_at_version: 1340
last_modified_version: 1340
target_version: 1468
columns_count: 2
foreign_keys_count: 2
foreign_keys:
  CODWR_CONTROLE: WR_CONTROLE
  CODWR_FORM: WR_FORM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_CONTROLE_WR_FORM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1340;
- **Última mudança:** UPDATE 1340;
- **Total colunas (versão 1468):** 2

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_CONTROLE` | [`WR_CONTROLE`](../../wr_metadata/tabelas/WR_CONTROLE.md) |
| `CODWR_FORM` | [`WR_FORM`](../../wr_metadata/tabelas/WR_FORM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODWR_FORM` | `INTEGER` | NULL | → `WR_FORM` | v1340 | v1340 |
| 2 | `CODWR_CONTROLE` | `INTEGER` | NULL | → `WR_CONTROLE` | v1340 | v1340 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1340 | CREATE | CREATE TABLE com 2 colunas |

