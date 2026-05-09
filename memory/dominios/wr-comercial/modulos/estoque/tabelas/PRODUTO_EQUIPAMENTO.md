---
table: PRODUTO_EQUIPAMENTO
module: estoque
created_at_version: 198
last_modified_version: 198
target_version: 1468
columns_count: 3
foreign_keys_count: 2
foreign_keys:
  CODEQUIPAMENTO: EQUIPAMENTO
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_EQUIPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 198;
- **Última mudança:** UPDATE 198;
- **Total colunas (versão 1468):** 3

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEQUIPAMENTO` | [`EQUIPAMENTO`](../../equipamento/tabelas/EQUIPAMENTO.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v198 | v198 |
| 2 | `CODEQUIPAMENTO` | `INTEGER` | NOT NULL | → `EQUIPAMENTO` | v198 | v198 |
| 3 | `MINUTOS` | `INTEGER` | NULL |  | v198 | v198 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 198 | CREATE | CREATE TABLE com 3 colunas |

