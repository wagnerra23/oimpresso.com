---
table: PRODUTO_ESTOQUE_LOTE
module: estoque
created_at_version: 233
last_modified_version: 233
target_version: 1468
columns_count: 7
foreign_keys_count: 3
foreign_keys:
  CODEMPRESA: EMPRESA
  CODNF_ENTRADA: NF_ENTRADA
  CODPRODUTO: PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_ESTOQUE_LOTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 233;
- **Última mudança:** UPDATE 233;
- **Total colunas (versão 1468):** 7

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v233 | v233 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v233 | v233 |
| 3 | `LOTE` | `INTEGER` | NOT NULL |  | v233 | v233 |
| 4 | `DT_ENTRADA` | `TIMESTAMP` | NULL |  | v233 | v233 |
| 5 | `DT_FINALIZADO` | `TIMESTAMP` | NULL |  | v233 | v233 |
| 6 | `CODNF_ENTRADA` | `VARCHAR(10)` | NULL | → `NF_ENTRADA` | v233 | v233 |
| 7 | `QUANT` | `DOUBLE PRECISION` | NULL |  | v233 | v233 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 233 | CREATE | CREATE TABLE com 7 colunas |

