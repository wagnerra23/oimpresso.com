---
table: SINTEGRA_R60A
module: nfe
created_at_version: 735
last_modified_version: 735
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODSINTEGRA: SINTEGRA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_R60A`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 735;
- **Última mudança:** UPDATE 735;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODSINTEGRA` | [`SINTEGRA`](../../nfe/tabelas/SINTEGRA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v735 | v735 |
| 2 | `CODSINTEGRA` | `INTEGER` | NULL | → `SINTEGRA` | v735 | v735 |
| 3 | `DT_EMISSAO` | `DATE` | NULL |  | v735 | v735 |
| 4 | `NUM_SERIE` | `VARCHAR(20)` | NULL |  | v735 | v735 |
| 5 | `SITUACAO_TRIBUTARIA` | `VARCHAR(4)` | NULL |  | v735 | v735 |
| 6 | `VALOR_ACUMULADO` | `DOUBLE PRECISION` | NULL |  | v735 | v735 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 735 | CREATE | CREATE TABLE com 6 colunas |

