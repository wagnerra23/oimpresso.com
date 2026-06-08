---
table: EMAIL_MASSA_MENSAGEM
module: agenda
created_at_version: 730
last_modified_version: 1468
target_version: 1468
columns_count: 10
foreign_keys_count: 2
foreign_keys:
  CODEMAIL_MASSA: EMAIL_MASSA
  CODPESSOA: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_MASSA_MENSAGEM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 730;
- **Última mudança:** UPDATE 1468;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMAIL_MASSA` | [`EMAIL_MASSA`](../../agenda/tabelas/EMAIL_MASSA.md) |
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v730 | v730 |
| 2 | `CODEMAIL_MASSA` | `INTEGER` | NOT NULL | → `EMAIL_MASSA` | v730 | v730 |
| 3 | `DESTINATARIO` | `VARCHAR(500) CHARACTER SET NONE` | NULL |  | v730 | v1468 |
| 4 | `ASSUNTO` | `VARCHAR(150)` | NULL |  | v730 | v730 |
| 5 | `SITUACAO` | `VARCHAR(30)` | NULL |  | v730 | v730 |
| 6 | `SITUACAO_MOTIVO` | `VARCHAR(500)` | NULL |  | v730 | v730 |
| 7 | `DT_ENVIADO` | `TIMESTAMP` | NULL |  | v730 | v730 |
| 8 | `CONTEUDO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v730 | v730 |
| 9 | `CODPESSOA` | `VARCHAR(10)` | NULL | → `PESSOAS` | v730 | v730 |
| 10 | `CONTEUDO_HTML` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v730 | v730 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 730 | CREATE | CREATE TABLE com 10 colunas |
| 1468 | ALTER_TYPE | ~ DESTINATARIO TYPE VARCHAR(500) CHARACTER SET NONE |

