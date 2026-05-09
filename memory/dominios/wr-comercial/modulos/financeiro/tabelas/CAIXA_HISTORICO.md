---
table: CAIXA_HISTORICO
module: financeiro
created_at_version: 9
last_modified_version: 9
target_version: 1468
columns_count: 22
foreign_keys_count: 2
foreign_keys:
  CODCONTA: CONTAS
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CAIXA_HISTORICO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 9;
- **Última mudança:** UPDATE 9;
- **Total colunas (versão 1468):** 22

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONTA` | [`CONTAS`](../../financeiro/tabelas/CONTAS.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v9 | v9 |
| 2 | `CODIGO_CAIXA` | `INTEGER` | NOT NULL |  | v9 | v9 |
| 3 | `VALOR_ABERTURA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 4 | `DATA_ABERTURA` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 5 | `DATA_FECHAMENTO` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 6 | `VALOR_FECHAMENTO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 7 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v9 | v9 |
| 8 | `CODCONTA` | `INTEGER` | NULL | → `CONTAS` | v9 | v9 |
| 9 | `TOTAL_DINHEIRO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 10 | `TOTAL_CHEQUE` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 11 | `TOTAL_BOLETO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 12 | `TOTAL_CARTAODECREDITO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 13 | `TOTAL_CARTAODEDEBITO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 14 | `TOTAL_CREDIARIO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 15 | `TOTAL_DEPOSITO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 16 | `TOTAL_NOTASIMPLES` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 17 | `TOTAL_NOTAPROMISSORIA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 18 | `TOTAL_PERMUTA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 19 | `TOTAL_CREDITO` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 20 | `TOTAL_CARTEIRA` | `DOUBLE PRECISION` | NULL |  | v9 | v9 |
| 21 | `HISTORICO` | `TIMESTAMP` | NULL |  | v9 | v9 |
| 22 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v9 | v9 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 9 | CREATE | CREATE TABLE com 22 colunas |

