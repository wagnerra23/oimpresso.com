---
table: SETOR_FUNCIONARIO
module: cadastros
created_at_version: 56
last_modified_version: 365
target_version: 1468
columns_count: 2
foreign_keys_count: 2
foreign_keys:
  CODFUNCIONARIO: FUNCIONARIO
  CODSETOR: SETOR
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SETOR_FUNCIONARIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 56;
- **Última mudança:** UPDATE 365;
- **Total colunas (versão 1468):** 2

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFUNCIONARIO` | [`FUNCIONARIO`](../../cadastros/tabelas/FUNCIONARIO.md) |
| `CODSETOR` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODSETOR` | `INTEGER` | NOT NULL | → `SETOR` | v56 | v56 |
| 2 | `CODFUNCIONARIO` | `VARCHAR(10)` | NOT NULL | → `FUNCIONARIO` | v56 | v56 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 56 | CREATE | CREATE TABLE com 2 colunas |
| 318 | ADD_COL | + CODPRODUTO varchar (15) |
| 365 | DROP_COL | - CODPRODUTO |

