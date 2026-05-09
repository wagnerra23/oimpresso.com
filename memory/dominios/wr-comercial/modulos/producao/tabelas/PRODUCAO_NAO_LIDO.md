---
table: PRODUCAO_NAO_LIDO
module: producao
created_at_version: 916
last_modified_version: 916
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODPRODUCAO: PRODUCAO
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_NAO_LIDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 916;
- **Última mudança:** UPDATE 916;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUCAO` | `INTEGER` | NULL | → `PRODUCAO` | v916 | v916 |
| 2 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v916 | v916 |
| 3 | `LIDO` | `VARCHAR(1)` | NULL |  | v916 | v916 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 916 | CREATE | CREATE TABLE com 3 colunas |

