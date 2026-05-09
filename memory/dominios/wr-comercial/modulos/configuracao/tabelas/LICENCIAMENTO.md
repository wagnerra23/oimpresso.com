---
table: LICENCIAMENTO
module: configuracao
created_at_version: 74
last_modified_version: 74
target_version: 1468
columns_count: 33
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `LICENCIAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 74;
- **Última mudança:** UPDATE 74;
- **Total colunas (versão 1468):** 33

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v74 | v74 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v74 | v74 |
| 3 | `TIPODEACESSO` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 4 | `CONEXAO` | `VARCHAR(100)` | NULL |  | v74 | v74 |
| 5 | `USUARIO` | `VARCHAR(15)` | NULL |  | v74 | v74 |
| 6 | `SENHA` | `VARCHAR(15)` | NULL |  | v74 | v74 |
| 7 | `SISTEMA_OPERACIONAL` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 8 | `IP_INTERNO` | `VARCHAR(15)` | NULL |  | v74 | v74 |
| 9 | `ANTIVIRUS` | `VARCHAR(15)` | NULL |  | v74 | v74 |
| 10 | `PASTA_INSTALACAO` | `VARCHAR(255)` | NULL |  | v74 | v74 |
| 11 | `VERSAO_EXE` | `VARCHAR(15)` | NULL |  | v74 | v74 |
| 12 | `VERSAO_BANCO` | `VARCHAR(15)` | NULL |  | v74 | v74 |
| 13 | `DT_ULTIMA_ASSISTENCIA` | `TIMESTAMP` | NULL |  | v74 | v74 |
| 14 | `HD` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 15 | `BACKUP_AUTOMATICO` | `VARCHAR(1)` | NULL |  | v74 | v74 |
| 16 | `PAF` | `VARCHAR(1)` | NULL |  | v74 | v74 |
| 17 | `PROCESSADOR` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 18 | `MEMORIA` | `VARCHAR(20)` | NULL |  | v74 | v74 |
| 19 | `VELOCIDADE_CONEXAO` | `VARCHAR(20)` | NULL |  | v74 | v74 |
| 20 | `IMPRESSORA_FISCAL` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 21 | `LEITOR_BARRAS` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 22 | `GERA_MENSALIDADE` | `VARCHAR(1)` | NULL |  | v74 | v74 |
| 23 | `HOSTNAME` | `VARCHAR(50)` | NULL |  | v74 | v74 |
| 24 | `LIBERADO` | `VARCHAR(1)` | NULL |  | v74 | v74 |
| 25 | `DT_VALIDADE` | `TIMESTAMP` | NULL |  | v74 | v74 |
| 26 | `SERIAL` | `VARCHAR(20)` | NULL |  | v74 | v74 |
| 27 | `CONTRA_SENHA` | `VARCHAR(20)` | NULL |  | v74 | v74 |
| 28 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v74 | v74 |
| 29 | `MOTIVO` | `VARCHAR(500)` | NULL |  | v74 | v74 |
| 30 | `ATIVO` | `VARCHAR(1)` | NULL |  | v74 | v74 |
| 31 | `DT_CADASTRO` | `timestamp` | NULL |  | v74 | v74 |
| 32 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v74 | v74 |
| 33 | `CODEMPRESA` | `INTEGER` | NULL | → `EMPRESA` | v74 | v74 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 74 | CREATE | CREATE TABLE com 33 colunas |

