---
table: PESSOAS_SKYPE
module: cadastros
created_at_version: 343
last_modified_version: 343
target_version: 1468
columns_count: 2
foreign_keys_count: 1
foreign_keys:
  CODPESSOA: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_SKYPE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 343;
- **Última mudança:** UPDATE 343;
- **Total colunas (versão 1468):** 2

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `SKYPE_ID` | `VARCHAR(30)` | NOT NULL |  | v343 | v343 |
| 2 | `CODPESSOA` | `VARCHAR(10)` | NOT NULL | → `PESSOAS` | v343 | v343 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 343 | CREATE | CREATE TABLE com 2 colunas |

