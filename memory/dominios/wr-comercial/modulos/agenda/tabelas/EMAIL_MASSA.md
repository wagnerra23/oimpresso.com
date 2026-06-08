---
table: EMAIL_MASSA
module: agenda
created_at_version: 730
last_modified_version: 730
target_version: 1468
columns_count: 8
foreign_keys_count: 3
foreign_keys:
  CODEMAIL_CONTA: EMAIL_CONTA
  CODEMAIL_MODELO: EMAIL_MODELO
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_MASSA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 730;
- **Última mudança:** UPDATE 730;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMAIL_CONTA` | [`EMAIL_CONTA`](../../agenda/tabelas/EMAIL_CONTA.md) |
| `CODEMAIL_MODELO` | [`EMAIL_MODELO`](../../agenda/tabelas/EMAIL_MODELO.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v730 | v730 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v730 | v730 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v730 | v730 |
| 4 | `CODUSUARIO` | `INTEGER` | NOT NULL | → `USUARIO` | v730 | v730 |
| 5 | `SITUACAO` | `VARCHAR(30)` | NULL |  | v730 | v730 |
| 6 | `CODEMAIL_CONTA` | `INTEGER` | NULL | → `EMAIL_CONTA` | v730 | v730 |
| 7 | `CODEMAIL_MODELO` | `INTEGER` | NULL | → `EMAIL_MODELO` | v730 | v730 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v730 | v730 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 730 | CREATE | CREATE TABLE com 7 colunas |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |

