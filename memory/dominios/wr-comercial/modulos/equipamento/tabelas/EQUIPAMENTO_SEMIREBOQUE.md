---
table: EQUIPAMENTO_SEMIREBOQUE
module: equipamento
created_at_version: 588
last_modified_version: 588
target_version: 1468
columns_count: 4
foreign_keys_count: 3
foreign_keys:
  CODEQUIPAMENTO: EQUIPAMENTO
  CODEQUIPAMENTO_SEMIREBOQUE: EQUIPAMENTO_SEMIREBOQUE
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_SEMIREBOQUE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 588;
- **Última mudança:** UPDATE 588;
- **Total colunas (versão 1468):** 4

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEQUIPAMENTO` | [`EQUIPAMENTO`](../../equipamento/tabelas/EQUIPAMENTO.md) |
| `CODEQUIPAMENTO_SEMIREBOQUE` | [`EQUIPAMENTO_SEMIREBOQUE`](../../equipamento/tabelas/EQUIPAMENTO_SEMIREBOQUE.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODEQUIPAMENTO` | `INTEGER` | NOT NULL | → `EQUIPAMENTO` | v588 | v588 |
| 2 | `CODEQUIPAMENTO_SEMIREBOQUE` | `INTEGER` | NOT NULL | → `EQUIPAMENTO_SEMIREBOQUE` | v588 | v588 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v588 | v588 |
| 4 | `CODUSUARIO` | `INTEGER` | NOT NULL | → `USUARIO` | v588 | v588 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 588 | CREATE | CREATE TABLE com 4 colunas |

