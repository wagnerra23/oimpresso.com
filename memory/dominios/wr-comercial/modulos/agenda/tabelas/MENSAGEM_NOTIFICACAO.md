---
id: dominios-wr-comercial-modulos-agenda-tabelas-mensagem-notificacao
table: MENSAGEM_NOTIFICACAO
module: agenda
created_at_version: 1431
last_modified_version: 1431
target_version: 1468
columns_count: 12
foreign_keys_count: 5
foreign_keys:
  CODHISTORICO: HISTORICO
  CODMENSAGEM: MENSAGEM
  CODPESSOA_NOTIFICADA: PESSOAS
  CODPESSOA_NOTIFICANTE: PESSOAS
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MENSAGEM_NOTIFICACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1431;
- **Última mudança:** UPDATE 1431;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODHISTORICO` | [`HISTORICO`](../../wr_metadata/tabelas/HISTORICO.md) |
| `CODMENSAGEM` | [`MENSAGEM`](../../agenda/tabelas/MENSAGEM.md) |
| `CODPESSOA_NOTIFICADA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODPESSOA_NOTIFICANTE` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1431 | v1431 |
| 2 | `CODMENSAGEM` | `INTEGER` | NULL | → `MENSAGEM` | v1431 | v1431 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1431 | v1431 |
| 4 | `DT_LIDO` | `TIMESTAMP` | NULL |  | v1431 | v1431 |
| 5 | `TEM_FAVORITO` | `VARCHAR(1)` | NULL |  | v1431 | v1431 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1431 | v1431 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1431 | v1431 |
| 8 | `LIDO` | `VARCHAR(1)` | NULL |  | v1431 | v1431 |
| 9 | `CODPESSOA_NOTIFICANTE` | `VARCHAR(15)` | NULL | → `PESSOAS` | v1431 | v1431 |
| 10 | `CODPESSOA_NOTIFICADA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v1431 | v1431 |
| 11 | `FOI_NOTIFICADA` | `VARCHAR(1)` | NULL |  | v1431 | v1431 |
| 12 | `CODHISTORICO` | `INTEGER` | NULL | → `HISTORICO` | v1431 | v1431 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1431 | CREATE | CREATE TABLE com 12 colunas |

