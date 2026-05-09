---
table: HISTORICO_EDITANDO
module: wr_metadata
created_at_version: 1027
last_modified_version: 1027
target_version: 1468
columns_count: 17
foreign_keys_count: 2
foreign_keys:
  CODEMPRESA: EMPRESA
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_EDITANDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1027;
- **Última mudança:** UPDATE 1027;
- **Total colunas (versão 1468):** 17

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1027 | v1027 |
| 2 | `CODEMPRESA` | `VARCHAR(250)` | NULL | → `EMPRESA` | v1027 | v1027 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1027 | v1027 |
| 4 | `DATA` | `DATE` | NULL |  | v1027 | v1027 |
| 5 | `HORA` | `TIME` | NULL |  | v1027 | v1027 |
| 6 | `FORMULARIO` | `VARCHAR(250)` | NULL |  | v1027 | v1027 |
| 7 | `TELA` | `VARCHAR(250)` | NULL |  | v1027 | v1027 |
| 8 | `EVENTO` | `VARCHAR(50)` | NULL |  | v1027 | v1027 |
| 9 | `OBS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v1027 | v1027 |
| 10 | `TABELA` | `VARCHAR(50)` | NULL |  | v1027 | v1027 |
| 11 | `CHAVE_PK` | `VARCHAR(250)` | NULL |  | v1027 | v1027 |
| 12 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1027 | v1027 |
| 13 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1027 | v1027 |
| 14 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1027 | v1027 |
| 15 | `MENSAGEM` | `VARCHAR(5000)` | NULL |  | v1027 | v1027 |
| 16 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1027 | v1027 |
| 17 | `DT_FECHAMENTO` | `TIMESTAMP` | NULL |  | v1027 | v1027 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1027 | CREATE | CREATE TABLE com 17 colunas |

