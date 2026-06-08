---
table: BANCOS
module: financeiro
created_at_version: 286
last_modified_version: 728
target_version: 1468
columns_count: 5
foreign_keys_count: 1
foreign_keys:
  CODBANCO_COOPERATIVA: BANCOS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `BANCOS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 286;
- **Última mudança:** UPDATE 728;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODBANCO_COOPERATIVA` | [`BANCOS`](../../financeiro/tabelas/BANCOS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CONCILIACAO_USAR_DESC_RAZAO` | `varchar (1)` | NULL |  | v286 | v286 |
| 2 | `CODBANCO_COOPERATIVA` | `INTEGER` | NULL | → `BANCOS` | v679 | v679 |
| 3 | `TIPO_CONVENIO` | `VARCHAR(50)` | NULL |  | v679 | v679 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 286 | ADD_COL | + CONCILIACAO_USAR_DESC_RAZAO varchar (1) |
| 679 | ADD_COL | + CODBANCO_COOPERATIVA INTEGER |
| 679 | ADD_COL | + TIPO_CONVENIO VARCHAR(50) |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

