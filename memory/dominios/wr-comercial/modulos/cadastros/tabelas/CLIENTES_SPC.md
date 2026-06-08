---
table: CLIENTES_SPC
module: cadastros
created_at_version: 27
last_modified_version: 27
target_version: 1468
columns_count: 1
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CLIENTES_SPC`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 27;
- **Última mudança:** UPDATE 27;
- **Total colunas (versão 1468):** 1

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v27 | v27 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 27 | ADD_COL | + CODUSUARIO INTEGER |
| 27 | ALTER_TYPE | ~ SITUACAO TYPE VARCHAR(40) |

