---
table: FOLHA_PAGAMENTO
module: rh
created_at_version: 310
last_modified_version: 310
target_version: 1468
columns_count: 9
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FOLHA_PAGAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 310;
- **Última mudança:** UPDATE 310;
- **Total colunas (versão 1468):** 9

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v310 | v310 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v310 | v310 |
| 3 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v310 | v310 |
| 4 | `DT_EMISSAO` | `TIMESTAMP` | NULL |  | v310 | v310 |
| 5 | `DT_REFERENCIA` | `DATE` | NULL |  | v310 | v310 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v310 | v310 |
| 7 | `DT_FINANCEIRO` | `TIMESTAMP` | NULL |  | v310 | v310 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL |  | v310 | v310 |
| 9 | `TIPO` | `VARCHAR(15)` | NULL |  | v310 | v310 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 310 | CREATE | CREATE TABLE com 9 colunas |

