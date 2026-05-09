---
table: DRE_CLASSIFICACAO_PLANOCONTAS
module: financeiro
created_at_version: 495
last_modified_version: 495
target_version: 1468
columns_count: 2
foreign_keys_count: 2
foreign_keys:
  CODDRE_CLASSIFICACAO: DRE_CLASSIFICACAO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DRE_CLASSIFICACAO_PLANOCONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 495;
- **Última mudança:** UPDATE 495;
- **Total colunas (versão 1468):** 2

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODDRE_CLASSIFICACAO` | [`DRE_CLASSIFICACAO`](../../financeiro/tabelas/DRE_CLASSIFICACAO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODDRE_CLASSIFICACAO` | `INTEGER` | NOT NULL | → `DRE_CLASSIFICACAO` | v495 | v495 |
| 2 | `CODPLANOCONTAS` | `VARCHAR(15)` | NOT NULL | → `PLANOCONTAS` | v495 | v495 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 495 | CREATE | CREATE TABLE com 2 colunas |

