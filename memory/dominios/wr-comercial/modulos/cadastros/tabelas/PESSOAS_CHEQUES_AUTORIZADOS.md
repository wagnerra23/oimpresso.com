---
table: PESSOAS_CHEQUES_AUTORIZADOS
module: cadastros
created_at_version: 518
last_modified_version: 518
target_version: 1468
columns_count: 5
foreign_keys_count: 1
foreign_keys:
  CODPESSOA: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_CHEQUES_AUTORIZADOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 518;
- **Última mudança:** UPDATE 518;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v518 | v518 |
| 2 | `CODPESSOA` | `VARCHAR(15)` | NOT NULL | → `PESSOAS` | v518 | v518 |
| 3 | `DOCUMENTO` | `VARCHAR(14)` | NULL |  | v518 | v518 |
| 4 | `TITULAR` | `VARCHAR(60)` | NULL |  | v518 | v518 |
| 5 | `RESTRICAO` | `VARCHAR(1)` | NULL |  | v518 | v518 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 518 | CREATE | CREATE TABLE com 5 colunas |

