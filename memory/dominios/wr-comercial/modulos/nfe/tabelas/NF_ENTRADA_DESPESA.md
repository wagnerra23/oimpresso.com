---
table: NF_ENTRADA_DESPESA
module: nfe
created_at_version: 976
last_modified_version: 978
target_version: 1468
columns_count: 6
foreign_keys_count: 3
foreign_keys:
  CODFINANCEIRO: FINANCEIRO
  CODNF_ENTRADA: NF_ENTRADA
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_DESPESA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 976;
- **Última mudança:** UPDATE 978;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFINANCEIRO` | [`FINANCEIRO`](../../financeiro/tabelas/FINANCEIRO.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODNF_ENTRADA` | `VARCHAR(10)` | NOT NULL | → `NF_ENTRADA` | v976 | v976 |
| 2 | `CODFINANCEIRO` | `INTEGER` | NOT NULL | → `FINANCEIRO` | v976 | v976 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v976 | v976 |
| 4 | `OBSERVACAO` | `VARCHAR(600)` | NULL |  | v976 | v976 |
| 5 | `TOTAL` | `DOUBLE PRECISION` | NULL |  | v978 | v978 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v978 | v978 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 976 | CREATE | CREATE TABLE com 4 colunas |
| 978 | ADD_COL | + TOTAL DOUBLE PRECISION |
| 978 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

