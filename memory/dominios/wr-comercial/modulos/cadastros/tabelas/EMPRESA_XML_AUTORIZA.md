---
table: EMPRESA_XML_AUTORIZA
module: cadastros
created_at_version: 944
last_modified_version: 944
target_version: 1468
columns_count: 3
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMPRESA_XML_AUTORIZA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 944;
- **Última mudança:** UPDATE 944;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v944 | v944 |
| 2 | `DOCUMENTO` | `VARCHAR(18)` | NOT NULL |  | v944 | v944 |
| 3 | `DESCRICAO` | `VARCHAR(300)` | NULL |  | v944 | v944 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 944 | CREATE | CREATE TABLE com 3 colunas |

