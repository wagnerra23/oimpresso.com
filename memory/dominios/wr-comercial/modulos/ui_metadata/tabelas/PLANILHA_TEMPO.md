---
table: PLANILHA_TEMPO
module: ui_metadata
created_at_version: 993
last_modified_version: 993
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODPESSOA: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PLANILHA_TEMPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 993;
- **Última mudança:** UPDATE 993;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v993 | v993 |
| 2 | `CODPESSOA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v993 | v993 |
| 3 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v993 | v993 |
| 4 | `DT_FIM` | `TIMESTAMP` | NULL |  | v993 | v993 |
| 5 | `DURACAO` | `INTEGER` | NULL |  | v993 | v993 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP, ADD ATIVO VARCHAR(1)` | NULL |  | v993 | v993 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 993 | CREATE | CREATE TABLE com 5 colunas |
| 993 | ADD_COL | + DT_ALTERACAO TIMESTAMP, ADD ATIVO VARCHAR(1) |

