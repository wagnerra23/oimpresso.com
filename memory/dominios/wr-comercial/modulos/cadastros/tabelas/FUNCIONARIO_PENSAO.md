---
table: FUNCIONARIO_PENSAO
module: cadastros
created_at_version: 1289
last_modified_version: 1289
target_version: 1468
columns_count: 8
foreign_keys_count: 2
foreign_keys:
  CODBANCO: BANCOS
  CODFUNCIONARIO: FUNCIONARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO_PENSAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1289;
- **Última mudança:** UPDATE 1289;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |
| `CODFUNCIONARIO` | [`FUNCIONARIO`](../../cadastros/tabelas/FUNCIONARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1289 | v1289 |
| 2 | `CODIGO` | `INTEGER` | NOT NULL |  | v1289 | v1289 |
| 3 | `CODFUNCIONARIO` | `VARCHAR(10)` | NOT NULL | → `FUNCIONARIO` | v1289 | v1289 |
| 4 | `BENEFICIARIO` | `VARCHAR(150)` | NULL |  | v1289 | v1289 |
| 5 | `CODBANCO` | `VARCHAR(10)` | NULL | → `BANCOS` | v1289 | v1289 |
| 6 | `AGENCIA` | `VARCHAR(15) CHARACTER SET NONE` | NULL |  | v1289 | v1289 |
| 7 | `CONTA` | `VARCHAR(10)` | NULL |  | v1289 | v1289 |
| 8 | `BANCO` | `VARCHAR(100)` | NULL |  | v1289 | v1289 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 67 | ALTER_TYPE | ~ AGENCIA TYPE VARCHAR(15) |
| 174 | ADD_COL | + DT_ALTERACAO timestamp |
| 1289 | CREATE | CREATE TABLE com 8 colunas |

