---
table: CONFIGURACAO_CRONJOB
module: configuracao
created_at_version: 1249
last_modified_version: 1249
target_version: 1468
columns_count: 10
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_CRONJOB`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1249;
- **Última mudança:** UPDATE 1249;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1249 | v1249 |
| 2 | `SERVIDOR_CRON_HOST` | `VARCHAR(100)` | NULL |  | v1249 | v1249 |
| 3 | `SERVIDOR_CRON_PROCESS` | `VARCHAR(100)` | NULL |  | v1249 | v1249 |
| 4 | `SERVIDOR_CRON_OS_USER` | `VARCHAR(100)` | NULL |  | v1249 | v1249 |
| 5 | `SERVIDOR_CRON_ADDRESS` | `VARCHAR(200)` | NULL |  | v1249 | v1249 |
| 6 | `DT_ULTIMA_ATUALIZACAO` | `TIMESTAMP` | NULL |  | v1249 | v1249 |
| 7 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1249 | v1249 |
| 8 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1249 | v1249 |
| 9 | `ACAO` | `VARCHAR(50)` | NULL |  | v1249 | v1249 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1249 | v1249 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1249 | CREATE | CREATE TABLE com 10 colunas |

