---
slug: legacy-delphi-schema-firebird
title: "Schema Firebird — WR Comercial legacy"
type: knowledge-reference
authority: canonical
lifecycle: ativo
owner: felipe
last_updated: 2026-05-15
pii: false
---

# Schema Firebird — WR Comercial legacy

> Esqueleto canônico das tabelas Firebird do WR Comercial. **Não duplica** o doc técnico exaustivo em [`memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md`](../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) (queries-template, restrições de acesso, dicas isql) — aqui é o **mapa de navegação** Felipe-first com pointers pra detalhes.

## Visão macro — 393 tabelas vivas v1468

Inferido de [`memory/dominios/wr-comercial/modulos/_summary.md`](../dominios/wr-comercial/modulos/_summary.md) (auto-gerado em 2026-05-09 a partir do `UpdateSQL.txt` v1468):

- **Total registrado:** 448 tabelas
- **Vivas em v1468:** 393
- **Dropadas em versões anteriores:** 55
- **Statements DDL não-reconhecidos:** 764 (esperado — INSERTs, EXECUTE PROCEDURE, etc; não-schema)

### Distribuição por módulo Delphi

| Módulo Delphi | Tabelas vivas | Detalhe |
|---|---|---|
| `nfe` | 54 | NF-e entrada/saída — pode ter overlap com Modules/NfeBrasil novo |
| `estoque` | 48 | Produtos, lotes, movimentos |
| `financeiro` | 46 | `FINANCEIRO`, `CONTAS_BANCARIAS`, `BOLETOS`, `BANCO*` |
| `cadastros` | 39 | `PESSOAS` (329 cols!), `FORNECEDOR`, `FUNCIONARIO*`, `CLIENTES*` |
| `agenda` | 35 | `AGENDA*`, `EMAIL*`, `OCORRENCIA`, `SLA` (kanban + helpdesk) |
| `wr_metadata` | 34 | Framework de configuração (`WR_APP`, `CONFIGURACOES_GRID`) |
| `producao` | 31 | Orçamentos gráficos, kanban produção, OS |
| `vendas` | 23 | `VENDA`, `VENDA_PRODUTO`, `VENDA_FINANCEIRO`, `ECF`, `CONTRATO` |
| `equipamento` | 21 | Equipamentos de cliente (campos `PLACA/MARCAMODELO/ANO` indicam herança oficina) |
| `configuracao` | 19 | Config global do sistema |
| `bi` | 15 | KPIs, dashboards, metas |
| `ui_metadata` | 13 | Filtros/grids/agrupamentos por usuário |
| `rh` | 8 | Folha simplificada |
| `api` | 4 | `OIMPRESSO`, `OIMPRESSO_LOG`, `OIMPRESSO_CONFIGURACAO`, `WEB_SERVICE` (bridge oimpresso.com) |
| `tributario` | 3 | NCM, CST, regimes |

## Tabelas principais Firebird WR Comercial — críticas pra migração

Lista das 9 tabelas críticas confirmadas em produção (extraído de [`OFFICEIMPRESSO-FIREBIRD-SCHEMA.md`](../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) §2):

| Tabela | Propósito | Volume típico (ServidorWR2) | Campos chave | Alvo Laravel (candidato) |
|---|---|---|---|---|
| **`PESSOAS`** | Cadastro mestre — clientes, fornecedores, funcionários, transportadores | 13.703 | `CODIGO` (PK), `RAZAOSOCIAL`, `FANTASIA`, `CNPJCPF`, `TIPO` (`C`/`F`/`T`), `BLOQUEADO`, endereço, contatos, `LIMITECREDITO`, `DATACADASTRO` — **329 colunas** (só ~30 canônicas) | `Modules/Contact/Entities/Contact.php` (UltimatePOS core) |
| **`CONTRATO`** | Contratos vivos (MRR contratado) | 313 (244 ativos; 62 com `VALOR=NULL` precisam reconciliação) | `CODIGO`, `ATIVO` (`S`/`N`), `VALOR`, `DT_INICIO`, `DT_FIM`, `CODPESSOA` (FK PESSOAS) | `Modules/RecurringBilling/Entities/Subscription.php` (candidato) |
| **`MENSALIDADE`** | Templates de cobrança recorrente | — | (FK pra MENSALIDADE_FINANCEIRO) | idem RecurringBilling |
| **`MENSALIDADE_FINANCEIRO`** | Lançamentos individuais de mensalidade (sub-tipo de FINANCEIRO escopo SaaS) | 17.749 | `CODMENSALIDADE`, `VALOR`, `DT_VENCTO`, `STATUS` (`ATIVO`/`INATIVO`), `TIPO` (`A RECEBER`), `RAZAOSOCIAL` ⚠️ PII, `TIPOPAGTO`, `PESSOA_RESPONSAVEL_CODIGO`. ⚠️ Tem campos `PLACA/MARCAMODELO/ANO` que **vazaram** do contexto oficina pro schema geral | `Modules/RecurringBilling/Entities/RecurringInvoice.php` |
| **`FINANCEIRO`** | Master de TODOS os lançamentos financeiros (receita + despesa) — tabela canônica pra análise | 59.186 | `CODIGO`, `RAZAOSOCIAL` ⚠️ PII, `VALOR`, `EMISSAO`/`VENCTO`/`DATAPAGTO`, `TIPO` (`RECEBIDA`/`A RECEBER`/`PAGA`/`A PAGAR`), `STATUS` (`ATIVO`/`INATIVO`), `DOCUMENTO`, `NOTAFISCAL`, `HISTORICO`, `JUROS`/`DESCONTO`/`MULTA`, `CODPLANOCONTAS`, `CODTIPOPAGTO`, `PARCELA`, `BOLETO_NOSSO_NR`, `CODNF_ENTRADA`, `MOTIVO_EXCLUSAO`, `PROVISORIO` | `Modules/Financeiro/Entities/Lancamento.php` (existe? confirmar) |
| **`BOLETOS`** | Boletos bancários emitidos | 29.946 | `CODIGO`, `CODFINANCEIRO` (FK), `CODBANCO`, `CARTEIRA`, `TIPO`, `OCORENCIA`, `JUROS_MORA`, `MULTA`, `DESCONTO`, `SITUACAO`, `BAIXA_DEVOLUCAO`, `DT_REMESSA`, `DT_RETORNO` | `Modules/Financeiro` + integração Asaas/Inter |
| **`BALANCO_TITULO`** | Demonstrativos contábeis (fluxo de caixa estruturado) | 152 | — | (a investigar) |
| **`VENDA`** + **`VENDA_FINANCEIRO`** | OS / vendas comerciais com lançamento financeiro associado | 1.866 / 3.404 | (ver detalhes em [`memory/dominios/wr-comercial/modulos/vendas/tabelas/VENDA.md`](../dominios/wr-comercial/modulos/vendas/tabelas/VENDA.md)) | `Modules/Sells/...` (UltimatePOS core `transactions`) |
| **`NOTA_FISCAL`** | NFs emitidas (volume pequeno — clientes legacy usam pouco NFC-e/NFS-e do OfficeImpresso) | 231 | — | `Modules/NfeBrasil/...` |

> ⚠️ Volume típico = banco do Wagner (`ServidorWR2`). Cada cliente tem volume próprio — usar skill `officeimpresso-financial-snapshot` pra probe específico.

## Tabelas BRIDGE — sync Delphi → oimpresso.com

Estas tabelas Firebird controlam o sync do Delphi pro oimpresso.com novo (descobertas via skill `officeimpresso-source-analysis` — `Controller.OImpresso.pas`):

| Tabela Firebird | Função | Doc detalhe |
|---|---|---|
| `OIMPRESSO` | Master — registros sincronizados | [memory/dominios/wr-comercial/modulos/api/tabelas/OIMPRESSO.md](../dominios/wr-comercial/modulos/api/tabelas/OIMPRESSO.md) |
| `OIMPRESSO_LOG` | Log de cada operação (sucesso/erro/timestamp) | [memory/dominios/wr-comercial/modulos/api/tabelas/OIMPRESSO_LOG.md](../dominios/wr-comercial/modulos/api/tabelas/OIMPRESSO_LOG.md) |
| `OIMPRESSO_CONFIGURACAO` | Config por cliente (endpoint, credenciais) | [memory/dominios/wr-comercial/modulos/api/tabelas/OIMPRESSO_CONFIGURACAO.md](../dominios/wr-comercial/modulos/api/tabelas/OIMPRESSO_CONFIGURACAO.md) |
| `WEB_SERVICE` | Endpoints registrados | [memory/dominios/wr-comercial/modulos/api/tabelas/WEB_SERVICE.md](../dominios/wr-comercial/modulos/api/tabelas/WEB_SERVICE.md) |

**Métodos Delphi que populam essas tabelas** (em `app/Controller/Controller.OImpresso.pas`):

| Método Delphi | O que faz |
|---|---|
| `LoginDaAPI(Sender)` | autentica na API REST do oimpresso.com via `Controller.Pessoas.OImpresso` |
| `SincronizarContatos(Sender)` | `POST /api/oimpresso/contatos` pra cada PESSOAS modificado |
| `SincronizarVendas(Sender)` | `POST /api/oimpresso/vendas` |
| `SincronizarFinanceiro(Sender)` | `POST /api/oimpresso/financeiro` |
| `SincronizarProduto(Sender)` | `POST /api/oimpresso/produto` |
| `SincronizarTudo(Sender)` | dispara todos sequencial |

**Implicação estratégica:** migração **NÃO precisa ser cutover Big Bang** — cliente pode rodar Delphi + oimpresso.com em paralelo via sync incremental ("modelo Asaas-like" — ferramenta nova como complemento cloud, não substituição imediata).

## Tabelas por módulo Delphi (detalhe granular já mapeado)

Os schemas detalhados de cada tabela individual já estão em `memory/dominios/wr-comercial/modulos/<dom>/tabelas/<X>.md`. Subset relevante pra migração:

### Módulo `vendas` (23 tabelas)

Lista completa em [`memory/dominios/wr-comercial/modulos/vendas/_index.md`](../dominios/wr-comercial/modulos/vendas/_index.md). Destaques:

- `VENDA` — venda principal
- `VENDA_PRODUTO` — itens da venda
- `VENDA_FINANCEIRO` / `VENDA_FINANCEIRO_TEF` — lançamentos + TEF
- `VENDA_PRODUTO_ETAPA` — workflow produção por item (gráfica)
- `VENDA_PRODUTO_CENTRO_TRABALHO` — alocação centro de trabalho
- `VENDA_AUDIT` — auditoria
- `VENDA_ESTAGIO` / `VENDA_SITUACAO` / `VENDA_TIPO` — taxonomia de estado
- `CONTRATO` / `CONTRATO_TIPO` — contratos recorrentes
- `ECF` — emissor de cupom fiscal (legacy)
- `CARRO*` (CARRO, CARROINTEIRO, CARROTEMP, etc) — carrinho de venda intermediário

### Módulo `cadastros` (39 tabelas)

Lista completa em [`memory/dominios/wr-comercial/modulos/cadastros/_index.md`](../dominios/wr-comercial/modulos/cadastros/_index.md). Destaques:

- `PESSOAS` (master) + extensões: `PESSOAS_CHEQUES_AUTORIZADOS`, `PESSOAS_CONTATO`, `PESSOAS_CREDITO`, `PESSOAS_ENTREGA`, `PESSOAS_GRUPO`, `PESSOAS_PRODUTO`, `PESSOAS_REPRESENTANTE`, `PESSOAS_SKYPE`, `PESSOAS_TIPO`
- `CLIENTES`, `CLIENTES_EQUIPAMENTO`, `CLIENTES_FINANCEIRO`, `CLIENTES_PRODUTO`, `CLIENTES_SPC`
- `EMPRESA`, `EMPRESA_XML_AUTORIZA`
- `FORNECEDOR`
- `FUNCIONARIO` + extensões: `_ANOTACOES`, `_BENEFICIARIO`, `_DEMISSAO`, `_FERIAS`, `_FUNCAO`, `_HORARIO`, `_PENSAO`, `_PONTO`, `_PONTO_ARQUIVO`, `_SALARIO`
- `REPRESENTANTE`, `SETOR`, `SETOR_FUNCIONARIO`, `SETOR_STATUS`
- `CIDADES`, `PAIS`, `LOCAL`

### Módulo `agenda` (35 tabelas)

Lista completa em [`memory/dominios/wr-comercial/modulos/agenda/_index.md`](../dominios/wr-comercial/modulos/agenda/_index.md). Destaques:

- `AGENDA`, `AGENDA_BLOQUEIO`, `AGENDA_FAQ`, `AGENDA_FILTRO`, `AGENDA_HISTORICO`, `AGENDA_MENSAGEM`, `AGENDA_TAREFAS`, `AGENDA_TITULO`, `AGENDA_TITULO_WORKFLOW`
- `EMAIL` + extensões: `_ANEXO`, `_CAIXA`, `_CONTA`, `_LOG`, `_MASSA`, `_MASSA_MENSAGEM*`, `_MODELO`, `_PRECONFIG`
- `MENSAGEM` + extensões: `_ASSUNTO`, `_CONTATO`, `_INTENCAO`, `_LIDO`, `_NOTIFICACAO`
- `OCORRENCIA`, `OCORRENCIA_EQUIPAMENTO`
- `REGISTRO_ATIVIDADE`
- `SLA`, `SLA_SEGUIDOR`
- `SOLICITACAO`, `TIPOOCORRENCIA`

### Módulo `bi` (15 tabelas)

Lista completa em [`memory/dominios/wr-comercial/modulos/bi/_index.md`](../dominios/wr-comercial/modulos/bi/_index.md). Destaques:

- `BALANCO`, `BALANCO_TITULO`
- `BI_ACOES`, `BI_ACOES_CONDICAO`, `BI_ACOES_EXECUCAO`
- `BI_KPI`, `KPI`, `KPI_ANO`, `KPI_DIA`, `KPI_MENU`, `KPI_MES`
- `DASHBOARDS`, `DASHBOARDS_ATALHO_RAPIDO`
- `META`, `META_DETALHE`

### Módulos restantes (financeiro, estoque, producao, nfe, equipamento, configuracao, wr_metadata, ui_metadata, rh, api, tributario)

Cada um tem `_index.md` próprio em `memory/dominios/wr-comercial/modulos/<dom>/_index.md` listando tabelas. Felipe pode navegar diretamente conforme escopo do dia.

> 🟡 **(TODO Felipe)** — preencher esquema **completo** das tabelas críticas individuais (DDL Firebird) abaixo via probe. Comando sugerido:
>
> ```python
> # scripts/legacy-delphi/probe-table.py <alias> <table>
> import firebird.driver as fb
> con = fb.connect('192.168.0.55:Banco', user='SYSDBA', password='masterkey')
> cur = con.cursor()
> cur.execute("""
>   SELECT RF.RDB$FIELD_NAME, F.RDB$FIELD_TYPE, F.RDB$FIELD_LENGTH, F.RDB$FIELD_PRECISION, F.RDB$FIELD_SCALE, RF.RDB$NULL_FLAG
>   FROM RDB$RELATION_FIELDS RF
>   JOIN RDB$FIELDS F ON F.RDB$FIELD_NAME = RF.RDB$FIELD_SOURCE
>   WHERE RF.RDB$RELATION_NAME = ?
>   ORDER BY RF.RDB$FIELD_POSITION
> """, [table_name.upper()])
> ```
>
> Resultado vai numa subpasta `descobertas/2026-MM-DD-schema-<table>.md`.

## Encoding e quirks

- **Charset:** WIN1252 (Windows-1252) — `firebird-driver` Python resolve automaticamente; isql precisa de `-ch WIN1252`
- **Datas:** SQL Dialect 3 (TIMESTAMP nativo, não string). `DATEADD(YEAR, -1, CURRENT_DATE)` é o padrão.
- **`PROVISORIO`** em FINANCEIRO: lançamentos previstos não-confirmados — filtrar `PROVISORIO='N'` ou ignorar conforme análise (default: ignorar)
- **`STATUS='INATIVO'`**: soft-delete. SEMPRE filtrar `STATUS='ATIVO'` em queries de saúde do banco
- **Tabela `PESSOAS` com 329 colunas**: schema cresceu organicamente em 26 anos. Só ~30 são canônicas — resto vazou de contextos específicos (oficina, gráfica, RH)
- **Campos vazados (anti-pattern)**: `PLACA/MARCAMODELO/ANO` em `MENSALIDADE_FINANCEIRO` (oficina) e em `PESSOAS` (deveria estar só em `CLIENTES_EQUIPAMENTO`)

## Acesso

| Ambiente | Conexão | Credencial |
|---|---|---|
| Servidor WR Sistemas (Wagner) | `192.168.0.55:Banco` (port 3050) | `SYSDBA` / `masterkey` |
| Cliente legacy específico | `servidor-crm:D:\DadosClientes\<NomeCliente>\Dados\BANCO.FDB` | `SYSDBA` / `masterkey` |

**Restrições:**

- ❌ SELECT-only. **NUNCA** INSERT/UPDATE/DELETE em banco produção cliente.
- ❌ NUNCA exportar dados sem anonimização pra git público.
- ✅ Gerar `.gitignore` em qualquer pasta que armazene resultados com PII real.

## Como descobrir versão do schema do banco

```sql
SELECT VALOR FROM CONFIGURACOES WHERE CONFIG = 'VERSAO_BANCO';
```

Banco zerado começa em `1308`. Versão MAIS NOVA observada no registry: **1474** (cliente Zoom). MAIS ANTIGA: **571** (GoldenPrint — gap de ~900 updates). Maioria entre 1408-1472 — ver matriz em [`memory/clientes-legacy/_index.md`](../clientes-legacy/_index.md).

Detalhes do formato `UpdateSQL.txt` (parser, blocos v6→v1999) em [`memory/dominios/wr-comercial/UPDATESQL.md`](../dominios/wr-comercial/UPDATESQL.md).
