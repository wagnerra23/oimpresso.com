---
slug: legacy-delphi-mapeamento
title: "Mapeamento Delphi → Laravel — migração WR Comercial → oimpresso"
type: knowledge-reference
authority: canonical
lifecycle: ativo
owner: felipe
last_updated: 2026-05-15
pii: false
---

# Mapeamento Delphi → Laravel — migração WR Comercial → oimpresso

> Mapa de migração feature-por-feature. **Felipe preenche conforme descobre** via skill `officeimpresso-source-analysis` (lê `Controller.<X>.pas`) + comparação com `Modules/<X>/` Laravel atual.

## Forms/Controllers Delphi → telas Laravel

Cada Controller Delphi (`app/Controller/Controller.<X>.pas`) tem o SQL exato + validações + valores default da tela. Cadeia: `T<X>Controller → TControllerMestre → TObject`. Documentação detalhada do TControllerMestre + bridge OImpresso em [skill officeimpresso-source-analysis](../../.claude/skills/officeimpresso-source-analysis/SKILL.md).

| Delphi Controller/Form | Função (tela Delphi) | Alvo Laravel | Status migração | Notas |
|---|---|---|---|---|
| `Controller.Venda.pas` (4010 LOC) | Lista de Vendas — `Tabela='VENDA'`, SQL `SELECT V.* FROM VENDA V` | `Modules/Sells/Http/Controllers/SellPosController` + `resources/js/Pages/Sells/Create.tsx` (MWART) + `resources/js/Pages/Sells/Index.tsx` | ✅ migrado prod (ROTA LIVRE biz=4) | UltimatePOS core `transactions` table com `type='sell'` |
| `Controller.Venda.Definicoes.pas` | Validações + valores default da VENDA (`AdicionarValorPadrao`, `AdicionarRegra`, contextos `CTX_EMPRESA_NFE`) | Validations em `SellPosController@store` + Pest fixtures | 🟡 parcial — validações ainda inferidas, não 1:1 com Delphi | Felipe pode mapear regra-por-regra |
| `Controller.Venda.Orcamento.pas` | Variação: tela Orçamento | `Modules/Sells/...` com flag `type='quote'` (a confirmar) | ⏳ não iniciado | FSM Pipeline canônico ([ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) `quote_draft` → ... → `completed` substitui |
| `Controller.Venda.NotaFiscal.pas` | Variação: tela NF | `Modules/NfeBrasil/...` | 🟡 parcial — Modules/NfeBrasil existe mas mapping campo-a-campo pendente | |
| `Controller.Venda.PDV.pas` | Variação: PDV (caixa) | `Modules/Sells/.../Pdv*` | ✅ existe Laravel | confirmar paridade fiscal ECF (legacy Delphi tinha `ECF` table) |
| `Controller.OImpresso.pas` | **BRIDGE** pro oimpresso.com novo (sync de Contatos/Vendas/Financeiro/Produto) | `Modules/Officeimpresso/Http/Controllers/DataController` (recebe `POST /connector/api/processa-dados-cliente`) | ✅ ativo prod | Contrato imutável ([ADR 0021](../decisions/0021-officeimpresso-contrato-api-delphi.md)) — não mudar |
| `Controller.OImpresso_Configuracao.pas` | Config sync por cliente (endpoint, credenciais) | `Modules/Officeimpresso/...` config | (a investigar) | tabela Firebird `OIMPRESSO_CONFIGURACAO` |
| `Controller.OImpresso_Log.pas` | Log de cada sync (sucesso/erro/timestamp) | `Modules/Officeimpresso/Entities/LicencaLog` (parcial — outro tipo de log) | 🟡 parcial | Considerar adicionar event type ao LicencaLog ou criar tabela própria |
| `Controller.Pessoas.OImpresso.pas` | Sync de PESSOAS Delphi → oimpresso | `Modules/Contact/...` (UltimatePOS core `contacts` table) | ✅ ativo prod via Connector API | |
| `Controller.Configuracoes_Grid.pas` | META: configuração de COLUNAS de grid (tabela `CONFIGURACOES_GRID`) | Sem equivalente direto — DataTables config no React | ⏳ não-portável (UI diferente) | Padrão React/Inertia não tem equivalente; cada Page tem colunas próprias |
| `Controller.Mestre.pas` (3444 LOC) | Classe BASE — TODOS Controllers herdam (`SQLInit`, `SQLWhere`, `OImpressoPrepareFieldsForSet`, `Insert/Edit/Cancel/Post/Delete` virtuais) | Sem equivalente — Laravel não tem herança Controller universal (cada Module é independente) | ⏳ não-portável (arquitetura diferente) | Documentar comportamento default ao migrar Controllers filhos |
| `Controller.<CadCliente>.pas` (a confirmar nome exato) | Cadastro de cliente/pessoa | `Modules/Contact/...` (UltimatePOS) | ✅ existe Laravel | mapping campos `PESSOAS` (329 cols!) → `contacts` ainda parcial |
| `Controller.<Financeiro>.pas` (a confirmar nome exato) | Tela Financeiro — `FINANCEIRO` master | `Modules/Financeiro/...` | 🟡 em construção | Visão unificada AR/AP é diferencial do oimpresso |
| `Controller.<Boleto>.pas` | Boletos bancários — `BOLETOS` + `FINANCEIRO_BOLETO_HISTORICO` | `Modules/Financeiro/...` + integração Asaas/Inter | 🟡 parcial — Asaas/Inter em construção (US-RB-044) | |
| `Controller.<Estoque>.pas` | Estoque/produtos/lotes | `Modules/Product` (UltimatePOS core) | ✅ existe Laravel | confirmar campos custom Delphi |
| `Controller.<NFe>.pas` | NF-e entrada/saída | `Modules/NfeBrasil/...` | ✅ Modules/NfeBrasil existe | 54 tabelas `nfe` Firebird → mapping pendente |
| `Controller.<Producao>.pas` (kanban gráfico) | Kanban de produção + OS gráfica | `Modules/Project/...` + `Modules/Repair/...` (compartilhado) | 🟡 em construção (vertical ComunicacaoVisual) | Wagner já tem Kanban Delphi (`Classes.Kanban.pas`) |
| `Controller.<Agenda>.pas` | Agenda + kanban + helpdesk + email | Sem equivalente Laravel direto — fragmentado em Jana/Repair/CRM | ⏳ não-portável 1:1 | 35 tabelas; análise caso-a-caso |
| `Controller.<BI>.pas` (dashboards) | KPIs, metas, dashboards | `Modules/Copiloto` (Jana IA gera insights) + dashboards Inertia | 🟡 em construção (estratégia diferente: IA conversacional > dashboards estáticos) | |

## Procedures/Functions Delphi → Services Laravel

| Delphi Proc | Função (Delphi) | Alvo Laravel | Status |
|---|---|---|---|
| `TControllerMestre.OImpressoPrepareFieldsForSet(query, json)` | Transforma dados Delphi → JSON pra POST API | Sem equivalente — Delphi-side apenas | N/A |
| `TControllerMestre.OImpressoPrepareFieldsForGet(query, json)` | Transforma JSON → Delphi | Sem equivalente — Delphi-side apenas | N/A |
| `TControllerMestre.GeraFDQueryOImpressoPost(json)` | Gera FDQuery pra POST | Sem equivalente — Delphi-side apenas | N/A |
| `TServicesRegistroSistema.RegistrarSistema(True)` | Registra licença + heartbeat (thread) — `POST /connector/api/processa-dados-cliente` array | `Modules/Officeimpresso/Http/Controllers/DataController@processaDadosCliente` (Connector API) | ✅ ativo prod |
| `TThreadLicenca.Execute` | Fluxo legado flat (sem CNPJ) — endpoint mesmo | `Modules/Officeimpresso/Http/Controllers/DataController` (aceita ambos formatos array+flat) | ✅ ativo prod |
| `TServiceOImpressoToken.GetToken('WR23', 'wscrct*2312')` | OAuth password grant — `POST /oauth/token` (client_id=39) | Passport OAuth | ✅ ativo prod |
| `TOImpressoAPI.DoRequest` | HTTP client base — `POST https://oimpresso.com/connector/api${endpoint}` com `Bearer ${token}` | N/A (Delphi-side) | — |
| `ControllerLicenciamento_GetVersaoBanco` | Lê versão schema banco — `SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'` | Sem equivalente Laravel (schema oimpresso usa migrations Laravel) | N/A |

## Tabelas Firebird → tabelas Laravel (alto nível)

Para mapping fino veja [SCHEMA-FIREBIRD.md](SCHEMA-FIREBIRD.md) + docs por módulo em [`memory/dominios/wr-comercial/modulos/<dom>/tabelas/`](../dominios/wr-comercial/modulos/).

| Tabela Firebird | Volume típico ServidorWR2 | Tabela MySQL oimpresso candidata | Module Laravel |
|---|---|---|---|
| `PESSOAS` (329 cols, 30 canônicas) | 13.703 | `contacts` (UltimatePOS) | `Modules/Contact` (core) |
| `FINANCEIRO` | 59.186 | `lancamentos` / `transaction_payments` (a definir) | `Modules/Financeiro` |
| `MENSALIDADE_FINANCEIRO` | 17.749 | `recurring_invoices` | `Modules/RecurringBilling` |
| `CONTRATO` | 313 (244 ativos) | `subscriptions` | `Modules/RecurringBilling` |
| `BOLETOS` | 29.946 | `boletos` ou `transaction_payments` com flag | `Modules/Financeiro` + Asaas/Inter |
| `VENDA` | 1.866 | `transactions` (type=sell) | `Modules/Sells` (core) |
| `VENDA_PRODUTO` | — | `transaction_sell_lines` | `Modules/Sells` (core) |
| `VENDA_FINANCEIRO` | 3.404 | `transaction_payments` | `Modules/Sells` (core) |
| `NOTA_FISCAL` | 231 | `nfe_emissoes` | `Modules/NfeBrasil` |
| `BALANCO_TITULO` | 152 | (a investigar) | `Modules/Financeiro` |
| `OIMPRESSO*` (bridge tables) | — | `oauth_clients`, `licenca_computador`, `licenca_log` | `Modules/Officeimpresso` |
| `AGENDA*` (35 tabelas) | — | fragmentado (Jana memória + Repair OS + CRM) | múltiplos |
| `BI*` / `KPI*` / `DASHBOARDS` | — | sem equivalente direto — Jana IA gera insights conversacionais | `Modules/Copiloto` |

## Variável Delphi → variável Laravel (vocabulário)

Cuidado com **vocabulário Delphi PT-BR** que não bate 1:1 com convenção Laravel. Anticorruption Layer documentada (Eric Evans DDD 2003) — vai aqui:

| Delphi (PT-BR canon WR) | Laravel oimpresso |
|---|---|
| `CODIGO` (PK) | `id` (PK Eloquent) |
| `RAZAOSOCIAL` | `name` (campo `contacts.name`) |
| `FANTASIA` | `supplier_business_name` (contacts) |
| `CNPJCPF` | `tax_number` (contacts) |
| `EMISSAO` (data emissão) | `created_at` ou `transaction_date` (Sells) |
| `VENCTO` (data vencimento) | `pay_term_number` + `pay_term_type` (UltimatePOS) ou `due_date` (Financeiro) |
| `DATAPAGTO` (data pagamento) | `paid_on` |
| `TIPO` (`RECEBIDA`/`A RECEBER`/`PAGA`/`A PAGAR`) | `payment_status` + flag receivable/payable |
| `STATUS` (`ATIVO`/`INATIVO`) | `is_active` BOOLEAN ou soft-delete (`deleted_at`) |
| `BLOQUEADO` (`S`/`N`) | `is_blocked` BOOLEAN |
| `DT_INICIO` / `DT_FIM` | `start_date` / `end_date` |
| `CODPESSOA` (FK) | `contact_id` (FK) |
| `BOLETO_NOSSO_NR` | `nosso_numero` ou `boleto_number` |
| `VERSAO_BANCO` (schema version Delphi) | migrations Laravel (`schema_migrations`) |
| `PROVISORIO` (`S`/`N`) | sem equivalente direto — filtrar antes de migrar ou criar flag `is_provisional` |

## Fluxo recomendado pra Felipe migrar tela X

1. **Identificar Controller Delphi:** `ls "D:/Programas/WR Comercial/app/Controller/" | grep -i <X>`
2. **Ler `Controller.<X>.pas`** — `constructor Create` (50 linhas top) → `Tabela`, `Caption`, `SQLInit.Text`
3. **Ler `Controller.<X>.Definicoes.pas`** — regras + defaults (`AdicionarValorPadrao`, `AdicionarRegra`, `DefinirContexto`)
4. **Ler `SQL.<X>.pas`** (se existir) — statements custom
5. **Cruzar com schema:** ver tabelas envolvidas em [SCHEMA-FIREBIRD.md](SCHEMA-FIREBIRD.md) + docs por tabela em [`memory/dominios/wr-comercial/modulos/<dom>/tabelas/<T>.md`](../dominios/wr-comercial/modulos/)
6. **Identificar Module Laravel alvo** (esta tabela)
7. **Verificar heatmap real:** cruzar com `scripts/sells_grade_heatmap.py` ou skill `officeimpresso-financial-snapshot` pra ver o que clientes **usam de fato** (vs. o que está no schema)
8. **Implementar Laravel** seguindo skill `mwart-process` (5 fases obrigatórias — ADR 0104)
9. **Documentar descoberta:** criar `memory/legacy-delphi/descobertas/2026-MM-DD-tela-<X>.md` com SQL base, mapping campos, decisões, esforço
10. **PR no git** com referência ao Controller Delphi (caminho relativo, sem expor código)

## Bridge runtime — sync Delphi ↔ oimpresso.com (já funciona em prod)

Cliente legacy pode operar Delphi + oimpresso.com em paralelo via `Controller.OImpresso.SincronizarTudo` (descoberto via skill `officeimpresso-source-analysis` em 2026-05-11):

```
Delphi cliente (campo)
  └→ SincronizarContatos    → POST /api/oimpresso/contatos       → Modules/Officeimpresso → contacts (oimpresso MySQL)
  └→ SincronizarVendas      → POST /api/oimpresso/vendas         → ...                     → transactions
  └→ SincronizarFinanceiro  → POST /api/oimpresso/financeiro     → ...                     → lancamentos
  └→ SincronizarProduto     → POST /api/oimpresso/produto        → ...                     → products
```

Bridge tables Firebird: `OIMPRESSO`, `OIMPRESSO_LOG`, `OIMPRESSO_CONFIGURACAO`, `WEB_SERVICE` (ver [SCHEMA-FIREBIRD.md](SCHEMA-FIREBIRD.md) §"Tabelas BRIDGE").

> **Implicação estratégica:** migração não é cutover Big Bang — é **convivência gradual**. Cliente continua usando Delphi pro operacional (já familiar) e acessa oimpresso.com pra features cloud/IA/relatórios. Migra em ritmo próprio.

## Contrato API imutável

`POST /connector/api/processa-dados-cliente` aceita 2 formatos (array + flat). Resposta SEMPRE string `S;<msg>` ou `N;<motivo>` — **não é JSON**. Quebra Delphi se backend retornar JSON ([ADR 0021](../decisions/0021-officeimpresso-contrato-api-delphi.md)).

Detalhes em [`memory/dominios/wr-comercial/ARQUITETURA.md`](../dominios/wr-comercial/ARQUITETURA.md) §"Contrato `/connector/api/processa-dados-cliente`".
