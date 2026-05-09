---
table: DICA_COMPONENTES
module: ui_metadata
created_at_version: 728
last_modified_version: 728
target_version: 1468
columns_count: 8
foreign_keys_count: 1
foreign_keys:
  CODDICA: DICA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DICA_COMPONENTES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 728;
- **Última mudança:** UPDATE 728;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODDICA` | [`DICA`](../../ui_metadata/tabelas/DICA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v728 | v728 |
| 2 | `CODDICA` | `INTEGER` | NOT NULL | → `DICA` | v728 | v728 |
| 3 | `SEQUENCIA` | `INTEGER` | NULL |  | v728 | v728 |
| 4 | `COMPONENTE` | `VARCHAR(255)` | NULL |  | v728 | v728 |
| 5 | `MSG_CABECALHO` | `VARCHAR(1000)` | NULL |  | v728 | v728 |
| 6 | `MSG_CORPO` | `VARCHAR(1000)` | NULL |  | v728 | v728 |
| 7 | `MSG_RODAPE` | `VARCHAR(1000)` | NULL |  | v728 | v728 |
| 8 | `LARGURA` | `INTEGER` | NULL |  | v728 | v728 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 728 | CREATE | CREATE TABLE com 8 colunas |

