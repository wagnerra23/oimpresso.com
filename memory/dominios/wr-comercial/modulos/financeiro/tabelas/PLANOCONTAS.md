---
id: dominios-wr-comercial-modulos-financeiro-tabelas-planocontas
table: PLANOCONTAS
module: financeiro
created_at_version: 305
last_modified_version: 1370
target_version: 1468
columns_count: 22
foreign_keys_count: 5
foreign_keys:
  CODCENTRO_CUSTO: CENTRO_CUSTO
  CODDRE_CLASSIFICACAO: DRE_CLASSIFICACAO
  CODMARCADOR: MARCADOR
  CODMARCADOR_LISTA: MARCADOR_LISTA
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PLANOCONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 305;
- **Última mudança:** UPDATE 1370;
- **Total colunas (versão 1468):** 22

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_CUSTO` | [`CENTRO_CUSTO`](../../producao/tabelas/CENTRO_CUSTO.md) |
| `CODDRE_CLASSIFICACAO` | [`DRE_CLASSIFICACAO`](../../financeiro/tabelas/DRE_CLASSIFICACAO.md) |
| `CODMARCADOR` | [`MARCADOR`](../../producao/tabelas/MARCADOR.md) |
| `CODMARCADOR_LISTA` | [`MARCADOR_LISTA`](../../producao/tabelas/MARCADOR_LISTA.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TIPO_CUSTO` | `varchar (15)` | NULL |  | v305 | v305 |
| 2 | `ICONE` | `blob sub_type 0 segment size 80` | NULL |  | v344 | v344 |
| 3 | `DT_ALTERACAO` | `timestamp` | NULL |  | v344 | v344 |
| 4 | `PERIODICIDADE` | `VARCHAR(20)` | NULL |  | v420 | v420 |
| 5 | `TABELA` | `varchar (255)` | NULL |  | v364 | v364 |
| 6 | `CODTABELA` | `varchar (40)` | NULL |  | v364 | v364 |
| 7 | `AGRUPAREMOCULTO` | `varchar (1)` | NULL |  | v364 | v364 |
| 8 | `TOTAL_RECEBIMENTOS` | `double precision` | NULL |  | v364 | v364 |
| 9 | `TOTAL_PAGAMENTOS` | `double precision` | NULL |  | v364 | v364 |
| 10 | `TOTAL_QUANT_FINANCEIRO` | `integer` | NULL |  | v364 | v364 |
| 11 | `CODMARCADOR` | `INTEGER` | NULL | → `MARCADOR` | v390 | v390 |
| 12 | `CODMARCADOR_LISTA` | `INTEGER` | NULL | → `MARCADOR_LISTA` | v390 | v390 |
| 13 | `INDICE5` | `INTEGER` | NULL |  | v499 | v499 |
| 14 | `INDICE6` | `INTEGER` | NULL |  | v499 | v499 |
| 15 | `CODDRE_CLASSIFICACAO` | `INTEGER` | NULL | → `DRE_CLASSIFICACAO` | v499 | v499 |
| 16 | `TIPO_CLASSIFICACAO` | `VARCHAR(50)` | NULL |  | v955 | v955 |
| 17 | `TEM_RATEIO_AUTOMATICO` | `VARCHAR(1)` | NULL |  | v961 | v961 |
| 18 | `RATEIO_DIRETO` | `DOUBLE PRECISION` | NULL |  | v966 | v966 |
| 19 | `RATEIO_INDIRETO` | `DOUBLE PRECISION` | NULL |  | v966 | v966 |
| 20 | `RATEIO_AUTOMATICO` | `VARCHAR(1)` | NULL |  | v966 | v966 |
| 21 | `CODPLANOCONTAS` | `VARCHAR(30)` | NULL | → `PLANOCONTAS` | v1257 | v1257 |
| 22 | `CODCENTRO_CUSTO` | `INTEGER` | NULL | → `CENTRO_CUSTO` | v1370 | v1370 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 305 | ADD_COL | + TIPO_CUSTO varchar (15) |
| 344 | ADD_COL | + ICONE blob sub_type 0 segment size 80 |
| 344 | ALTER_TYPE | ~ DESCRICAO TYPE varchar(300) |
| 344 | ADD_COL | + DT_ALTERACAO timestamp |
| 364 | ADD_COL | + PERIODICIDADE varchar (20) |
| 364 | ADD_COL | + TABELA varchar (255) |
| 364 | ADD_COL | + CODTABELA varchar (40) |
| 364 | ADD_COL | + PERIODICIDADE varchar (20) |
| 364 | ADD_COL | + AGRUPAREMOCULTO varchar (1) |
| 364 | ADD_COL | + TOTAL_RECEBIMENTOS double precision |
| 364 | ADD_COL | + TOTAL_PAGAMENTOS double precision |
| 364 | ADD_COL | + TOTAL_QUANT_FINANCEIRO integer |
| 390 | ADD_COL | + CODMARCADOR INTEGER |
| 390 | ADD_COL | + CODMARCADOR_LISTA INTEGER |
| 420 | ADD_COL | + PERIODICIDADE VARCHAR(20) |
| 499 | ADD_COL | + INDICE5 INTEGER |
| 499 | ADD_COL | + INDICE6 INTEGER |
| 499 | ADD_COL | + CODDRE_CLASSIFICACAO INTEGER |
| 955 | ADD_COL | + TIPO_CLASSIFICACAO VARCHAR(50) |
| 961 | ADD_COL | + TEM_RATEIO_AUTOMATICO VARCHAR(1) |
| 966 | ADD_COL | + RATEIO_DIRETO DOUBLE PRECISION |
| 966 | ADD_COL | + RATEIO_INDIRETO DOUBLE PRECISION |
| 966 | ADD_COL | + RATEIO_AUTOMATICO VARCHAR(1) |
| 1257 | ADD_COL | + CODPLANOCONTAS VARCHAR(30) |
| 1370 | ADD_COL | + CODCENTRO_CUSTO INTEGER |

