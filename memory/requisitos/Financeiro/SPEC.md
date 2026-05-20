---
slug: financeiro
title: "Especificação funcional — Financeiro"
type: spec
module: Financeiro
status: ativo
related_adrs: [0154, 0155, 0156]
na_justified_v3:
  D1.c: "Job `CriarTituloDeVendaJob` é `@deprecated` órfão (Onda 2, 2026-04-25) e nunca foi dispatched em produção; sincronização canônica de títulos a partir de transactions ocorre via `TituloAutoService::sincronizarDeTransacao` chamado diretamente pelo `TransactionObserver`. O constructor do Job recebe apenas `$transactionId` e extrai `business_id` da Eloquent (pattern legítimo de Job-por-ID), portanto a checagem `$businessId` no constructor não se aplica ao módulo."
pii: false
updated_at: 2026-05-16
last_updated: 2026-05-19
version: 1.1
owner: wagner
---

# Especificação funcional — Financeiro

> Convenção do ID: `US-FIN-NNN` para user stories, `R-FIN-NNN` para regras Gherkin.
> Campo `Implementado em` linka com a página React (`resources/js/Pages/...`) que atende a story.

## 1. Glossário rápido

- **Título** — direito a receber (`tipo=receber`) ou obrigação a pagar (`tipo=pagar`)
- **Baixa** — registro de pagamento parcial ou total de um título
- **Aging** — bucket de inadimplência (`<30 / 30-60 / 60-90 / >90 / >180 dias`)
- **OFX** — Open Financial Exchange, formato padrão de extrato bancário BR
- **CNAB 240/400** — formato remessa/retorno bancário brasileiro

(Vocabulário completo: [GLOSSARY.md](GLOSSARY.md))

## User stories

### US-FIN-001 · Listar Contas a Receber em aberto

> **Área:** ContasReceber
> **Rota:** `GET /financeiro/contas-receber`
> **Controller/ação:** `ContaReceberController@index`
> **Permissão Spatie:** `financeiro.contas_receber.view`

**Como** Larissa-financeiro
**Quero** ver todos os títulos a receber em aberto, com filtro por aging e por cliente
**Para** decidir quem ligar primeiro pra cobrar e quanto entra esta semana

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasReceber/Index.tsx`]_

**Definition of Done:**
- [ ] Rota acessível apenas com `financeiro.contas_receber.view` (`403` caso contrário)
- [ ] Scope `business_id = session('user.business_id')` em toda query
- [ ] Filtros via FormRequest: `aging`, `cliente_id`, `vence_de`, `vence_ate`, `valor_min`, `valor_max`
- [ ] Shape JSON via `->transform()` (sem Model inteiro, só `id`, `numero`, `cliente_nome`, `vencimento`, `valor_aberto`, `aging_bucket`, `origem_label`)
- [ ] Total agregado no header (somatório do filtro corrente, não da página)
- [ ] Test Feature `ContaReceberIndexTest` com auth + permissão + isolamento + filtros + paginação
- [ ] Dark mode + responsivo (`grid cols-1 md:cols-3 lg:cols-5`)
- [ ] Toast `sonner` em ações de baixa rápida

### US-FIN-002 · Lançar título a receber manual

> **Área:** ContasReceber
> **Rota:** `POST /financeiro/contas-receber`
> **Controller/ação:** `ContaReceberController@store`
> **Permissão Spatie:** `financeiro.contas_receber.create`

**Como** Larissa-financeiro
**Quero** cadastrar título a receber sem venda associada (ex: aluguel sublocação, comissão extra)
**Para** ter visão integral do que entra, mesmo o que não passa pelo POS

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasReceber/Create.tsx`]_

**Definition of Done:**
- [ ] FormRequest valida: `cliente_id` ou `cliente_descricao` (livre); `valor>0`; `vencimento >= hoje` (ou flag `retroativo` true); `categoria_id` opcional; `plano_conta_id` opcional; `parcelas[]` se `parcelado=true`
- [ ] Geração automática de `numero` sequencial business-isolado (com `lockForUpdate`)
- [ ] `origem='manual'`, `origem_id=null`
- [ ] Suporta parcelamento: 1 título com N parcelas linkadas via `titulo_pai_id`
- [ ] Test Feature cobre: validação, sequência, parcelamento (3x), permissão, isolamento
- [ ] Toast `sonner` "Título R$X criado para [cliente]"

### US-FIN-003 · Baixar título (parcial ou total)

> **Área:** ContasReceber
> **Rota:** `POST /financeiro/contas-receber/{titulo}/baixar`
> **Controller/ação:** `ContaReceberController@baixar`
> **Permissão Spatie:** `financeiro.contas_receber.baixar`

**Como** Larissa-financeiro
**Quero** baixar título quando recebo o pagamento (parcial ou total) com data, valor, conta bancária e meio
**Para** atualizar saldo da conta + status do título sem dupla digitação

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasReceber/Show.tsx` (modal de baixa)]_

**Definition of Done:**
- [ ] FormRequest valida: `valor_baixa > 0`, `valor_baixa <= titulo.valor_aberto`, `data_baixa <= hoje`, `conta_bancaria_id` exists business, `meio_pagamento` enum
- [ ] `BaixaService::registrar()` cria `titulo_baixas` row + `caixa_movimentos` row (entrada) com `idempotency_key` único
- [ ] Recalcula `titulo.valor_aberto` = `valor_total - sum(baixas.valor)`
- [ ] Atualiza `titulo.status`: `aberto` se `valor_aberto = valor_total`, `parcial` se `0 < valor_aberto < valor_total`, `quitado` se `valor_aberto = 0`
- [ ] Dispara evento `Modules\Financeiro\Events\TituloBaixado`
- [ ] Cria `transaction_payment` retro-vinculado se `titulo.origem='venda'` (atualiza UltimatePOS core)
- [ ] Test Feature: parcial + total + over-baixa rejeitada + idempotência (mesmo `idempotency_key` 2x = 1 baixa) + isolamento

### US-FIN-004 · Listar Contas a Pagar com vencimento próximo

> **Área:** ContasPagar
> **Rota:** `GET /financeiro/contas-pagar`
> **Controller/ação:** `ContaPagarController@index`
> **Permissão Spatie:** `financeiro.contas_pagar.view`

**Como** Larissa-financeiro
**Quero** ver fornecedores a pagar com filtro "vence nos próximos 7 dias", "vencidos", "agendados"
**Para** evitar juros por esquecimento e priorizar pagamentos críticos (ex: fornecedor que corta material)

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasPagar/Index.tsx`]_

**Definition of Done:**
- [ ] Mesmo padrão US-FIN-001, ajustado para `tipo=pagar`
- [ ] Filtro especial "Próximos 7 dias" como tab default
- [ ] Indicador visual de "atrasado" (badge vermelho) e "vence hoje" (badge âmbar)
- [ ] Total agregado: "Total a pagar próximos 7 dias: R$ X"

### US-FIN-005 · Cadastrar título a pagar com upload de boleto OCR

> **Área:** ContasPagar
> **Rota:** `POST /financeiro/contas-pagar`
> **Controller/ação:** `ContaPagarController@store`
> **Permissão Spatie:** `financeiro.contas_pagar.create`

**Como** Larissa-financeiro
**Quero** anexar PDF/imagem do boleto e o sistema preencher fornecedor, valor, vencimento, linha digitável
**Para** lançar 10 boletos em 5 minutos em vez de 25

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasPagar/Create.tsx`]_

**Definition of Done:**
- [ ] Upload aceita `application/pdf`, `image/png`, `image/jpeg` até 5MB
- [ ] `BoletoOcrService::extract()` retorna shape `{linha_digitavel, valor, vencimento, beneficiario_nome, beneficiario_documento}` (Onda 4 — fallback manual em Onda 1-2)
- [ ] Storage privado em `storage/app/financeiro/{business_id}/boletos/{uuid}.pdf` (NÃO public)
- [ ] FormRequest valida: arquivo opcional; se sem arquivo, todos os campos manuais
- [ ] Detecta duplicidade por `linha_digitavel` (warn, não bloqueia)
- [ ] Test Feature: upload + extração mockada + duplicidade + isolamento

### US-FIN-006 · Pagar título (registrar saída do caixa)

> **Área:** ContasPagar
> **Rota:** `POST /financeiro/contas-pagar/{titulo}/pagar`
> **Controller/ação:** `ContaPagarController@pagar`
> **Permissão Spatie:** `financeiro.contas_pagar.pagar`

**Como** Larissa-financeiro
**Quero** marcar título como pago indicando data, valor, conta bancária debitada e meio
**Para** atualizar saldo + ter histórico auditável

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasPagar/Show.tsx` (modal pagar)]_

**Definition of Done:**
- [ ] Cria `caixa_movimentos` row (saída) com `idempotency_key`
- [ ] Mesmo padrão US-FIN-003 (status: aberto/parcial/quitado)
- [ ] Calcula automaticamente juros de mora se `data_pagamento > vencimento` (config tenant: 0,33% a.d. + 2% multa)
- [ ] Test Feature: pagamento atrasado calcula juros + multa corretamente

### US-FIN-007 · Visualizar fluxo de caixa projetado

> **Área:** Caixa
> **Rota:** `GET /financeiro/caixa/projetado`
> **Controller/ação:** `CaixaController@projetado`
> **Permissão Spatie:** `financeiro.caixa.view`

**Como** Gestor (Wagner ou dono do tenant)
**Quero** ver gráfico de barras com saldo projetado dia-a-dia nos próximos 30/60/90 dias
**Para** decidir antecipar recebível, pegar empréstimo, segurar pagamento, etc.

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/Caixa/Projetado.tsx`]_

**Definition of Done:**
- [ ] Endpoint retorna shape `{dias: [{data, saldo_inicial, entradas, saidas, saldo_final, alertas[]}], saldo_atual, periodo}` (não Model)
- [ ] Considera todos títulos abertos com `vencimento <= hoje + periodo`
- [ ] Alerta automático em dias com `saldo_final < 0` (badge "DESCOBERTO" + valor)
- [ ] Filtro por conta bancária (default: todas consolidado)
- [ ] Cache `business_id:caixa_projetado:{periodo}` invalidado em `TituloBaixado`/`TituloCriado` (5 min TTL)
- [ ] Gráfico Recharts com fill negativo vermelho
- [ ] Test Feature: cenário com descoberto + sem descoberto + isolamento

### US-FIN-008 · Cadastrar conta bancária

> **Área:** ContaBancaria
> **Rota:** `POST /financeiro/contas-bancarias`
> **Controller/ação:** `ContaBancariaController@store`
> **Permissão Spatie:** `financeiro.contas_bancarias.manage`

**Como** Larissa-financeiro
**Quero** cadastrar contas bancárias do business com banco, agência, conta, saldo inicial
**Para** segregar fluxo por conta e conciliar OFX por conta

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasBancarias/Form.tsx`]_

**Definition of Done:**
- [ ] FormRequest valida: `banco_codigo` (FEBRABAN), `agencia`, `conta`, `digito`, `tipo` enum (cc/poup/inv/caixa), `saldo_inicial >= 0`, `saldo_data` (default hoje)
- [ ] Cria `caixa_movimentos` row "saldo inicial" (`tipo=ajuste`, `valor=saldo_inicial`)
- [ ] Soft delete: conta com movimento histórico não pode ser hard-deleted (apenas inativada)
- [ ] Test Feature: criação + dupla com mesmo banco/agência/conta proibida (regra) + soft delete

### US-FIN-009 · Importar extrato OFX e conciliar

> **Área:** Conciliacao
> **Rota:** `POST /financeiro/conciliacao`
> **Controller/ação:** `ConciliacaoController@importar`
> **Permissão Spatie:** `financeiro.conciliacao.manage`

**Como** Larissa-financeiro
**Quero** subir o OFX que baixei do internet banking e o sistema mostrar match automático com meus títulos abertos
**Para** dar baixa em lote sem digitar nada e fechar mês com saldo batendo

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/Conciliacao/Index.tsx`]_

**Definition of Done:**
- [ ] Upload `.ofx` até 10MB
- [ ] `OfxParserService::parse()` retorna shape `{transactions: [{fitid, data, valor, tipo, descricao}]}`
- [ ] `ConciliacaoMatcher::match()` heurística: `valor_exato + tolerancia_3_dias + descricao_fuzzy >= 80%`
- [ ] Cada extrato gera `conciliacao_runs` row com hash do arquivo (idempotente: 2x mesmo OFX = sem dupla)
- [ ] UI: 3 colunas — extrato (esquerda), match sugerido (centro), título oimpresso (direita)
- [ ] Aceitar match em lote (checkbox + "Confirmar X matches")
- [ ] Item sem match vira título manual ou descarte (com motivo)
- [ ] Test Feature: parse OFX real (fixture) + match exato + tolerância + idempotência

### US-FIN-010 · Emitir boleto bancário (CNAB ou via gateway)

> **Área:** Boleto
> **Rota:** `POST /financeiro/contas-receber/{titulo}/boleto`
> **Controller/ação:** `BoletoController@emitir`
> **Permissão Spatie:** `financeiro.boletos.emitir`

**Como** Larissa-financeiro
**Quero** gerar boleto pra título a receber em 1 clique e mandar pro cliente por e-mail/WhatsApp
**Para** não depender do sistema do banco

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/ContasReceber/Show.tsx` (botão "Emitir boleto")]_

**Definition of Done:**
- [ ] BoletoService strategy: `CnabDirectStrategy` (lib `eduardokum/laravel-boleto`) OU `GatewayStrategy` (Asaas/Iugu) baseado em config do business
- [ ] Gera PDF + linha digitável + QR PIX (boleto híbrido)
- [ ] Storage `storage/app/financeiro/{business_id}/boletos-emitidos/{numero}.pdf`
- [ ] Cria `boleto_remessa` row (status `gerado` → `enviado` → `pago`/`vencido`)
- [ ] Webhook do gateway atualiza status (`BoletoController@webhook` com idempotência por `event_id`)
- [ ] Test Feature: geração + idempotência por `titulo_id` (re-emitir não duplica) + webhook update

### US-FIN-011 · DRE (Demonstração de Resultado)

> **Área:** Relatorio
> **Rota:** `GET /financeiro/relatorios/dre`
> **Controller/ação:** `RelatorioController@dre`
> **Permissão Spatie:** `financeiro.relatorios.view`

**Como** Contador (terceiro com role limitada) ou Gestor
**Quero** DRE do período (mês/trimestre/ano) com receita, custo, despesa, lucro líquido
**Para** declarar imposto / tomar decisão estratégica sem ligar pra Larissa

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/Relatorios/Dre.tsx`]_

**Definition of Done:**
- [ ] Considera regime do business (`caixa` ou `competência`)
- [ ] Estrutura DRE BR padrão: Receita Bruta → (-) Deduções → Receita Líquida → (-) CMV → Lucro Bruto → (-) Despesas → EBITDA → (-) D&A → (-) Impostos → Lucro Líquido
- [ ] Drill-down: clicar em conta abre lista de transações que somaram
- [ ] Export PDF + Excel (SheetJS server-side) com cabeçalho fiscal do business
- [ ] Token shareable read-only (`/financeiro/relatorios/dre/share/{token}`) válido 7 dias — gerado por demanda do contador
- [ ] Test Feature: cenário com vendas + compras + despesas + DRE bate com soma manual

### US-FIN-012 · Aging de inadimplência

> **Área:** Relatorio
> **Rota:** `GET /financeiro/relatorios/aging`
> **Controller/ação:** `RelatorioController@aging`
> **Permissão Spatie:** `financeiro.relatorios.view`

**Como** Larissa-financeiro / Gestor
**Quero** ver quem deve, agrupado por bucket (`<30 / 30-60 / 60-90 / >90 / >180`) com total e detalhe
**Para** atacar inadimplência da maior pra menor (régua manual ou via Dunning futuro)

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/Relatorios/Aging.tsx`]_

**Definition of Done:**
- [ ] Buckets configuráveis por tenant (default: 30/60/90/180)
- [ ] Agrupamento por cliente, com expansão pro detalhe dos títulos
- [ ] CTA "Cobrar via WhatsApp" abre `wa.me/{telefone}?text=...` template
- [ ] Test Feature: 5 títulos em buckets diferentes + total bate

### US-FIN-013 · Dashboard unificado de títulos (4 estados na mesma tela)

> **Área:** Dashboard
> **Rota:** `GET /financeiro` (entry point do módulo)
> **Controller/ação:** `DashboardController@index`
> **Permissão Spatie:** `financeiro.dashboard.view`

**Como** Larissa-financeiro
**Quero** abrir o módulo e ver os 4 estados (a receber abertos, a pagar abertos, recebidos no mês, pagos no mês) **na mesma tela**, com drill-down por click
**Para** ter overview do caixa em 5 segundos sem navegar entre 4 telas separadas

**Implementado em:** _[TODO — `resources/js/Pages/Financeiro/Dashboard/Index.tsx`]_

**Layout obrigatório (ADR ui/0002):**

```
┌─ KPI Grid (4 cards clicáveis, mobile: 2x2 / desktop: 1x4) ─────────────┐
│ [📥 A RECEBER]  [📤 A PAGAR]   [✓ RECEBIDOS]   [✓ PAGOS]              │
│ Abertos:        Abertos:       Este mês:       Este mês:               │
│ R$ [redacted Tier 0]       R$ [redacted Tier 0]       R$ [redacted Tier 0]       R$ [redacted Tier 0]               │
│ 14 títulos      9 títulos      32 baixas       21 baixas               │
│ ⚠ 3 vencidos    ⚠ 2 vencidos   ↑ +12% vs mês   ↑ +5% vs mês           │
└────────────────────────────────────────────────────────────────────────┘

┌─ Filtros (collapsible em mobile) ──────────────────────────────────────┐
│ Tipo: [Todos] [Receber] [Pagar]   Status: [Todos] [Aberto] [Parcial]  │
│ Período vencimento: [DateRangePicker]   Cliente/Fornecedor: [autocomplete] │
│ Aging: [<30] [30-60] [60-90] [>90]   Conta bancária: [select]         │
└────────────────────────────────────────────────────────────────────────┘

┌─ Tabela única (TanStack Table, server-side pagination) ────────────────┐
│ # | Cliente/Forn. | Tipo  | Status   | Venc.   | Valor   | Saldo  | … │
│   |               |  📥📤 | ●○◐      |         |         |        |   │
├──┼───────────────┼───────┼──────────┼─────────┼─────────┼────────┼───┤
│ 1234 | João Silva | 📥 R  | ● aberto | 28/04   | 1.500   | 1.500  | …│
│ 1238 | Petrobras  | 📤 P  | ● aberto | 30/04   | 850     | 850    | …│
│ 1230 | Maria S.   | 📥 R  | ✓ quita. | 22/04   | 500     | 0      | …│
└────────────────────────────────────────────────────────────────────────┘
```

**Interações-chave:**
- Click no KPI "A RECEBER" → filtra tabela `tipo=receber, status IN (aberto, parcial)`
- Click no KPI "RECEBIDOS" → filtra `tipo=receber, status=quitado, data_baixa>=início_mês`
- Click em ⚠ vencidos → filtra `vencimento < hoje, status != quitado`
- Click em row da tabela → abre detalhe (modal ou drawer com baixas/eventos)
- Botão flutuante `[+ Novo título]` em desktop; FAB em mobile

**Definition of Done:**
- [ ] Endpoint retorna shape `{kpis: {receber_aberto, pagar_aberto, recebido_mes, pago_mes}, titulos: PaginatedCollection}`
- [ ] KPIs são server-side aggregations (não calcula no front); cache 5 min, invalidado em `TituloCriado`/`TituloBaixado`/`TituloCancelado`
- [ ] Tabela usa server-side pagination + sort + filter (TanStack Query + URL state)
- [ ] Filtros refletem em URL (`?tipo=receber&status=aberto`) — bookmarkable
- [ ] Mobile: KPIs em 2x2 grid, filtros em accordion, tabela em cards
- [ ] Dark mode + responsivo (`md:grid-cols-4`)
- [ ] Test Feature: KPIs corretos com seed de 20 títulos misturados + isolamento + drill-down
- [ ] Test E2E (Playwright): click KPI → URL muda → tabela filtra
- [ ] Performance: < 500ms p95 em 5k títulos

**NÃO faz parte do MVP (mover pra US futura se sair do escopo):**
- Gráfico de fluxo de caixa projetado (US-FIN-007 separada)
- Gráficos de tendência mês-a-mês (Onda 4)
- Export PDF do dashboard

### US-FIN-014 · Imprimir 2ª via boleto Inter pelo título financeiro (botão na tela /boletos)

> owner: wagner · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: —

## Contexto

Pedido Wagner 2026-05-13 (sessão fin-4 esclarecida) — fatia A do epic "Financeiro OfficeImpresso base + Inter boleto + integrar venda". OfficeImpresso (legacy Delphi) imprime 2ª via boleto via PDF — paridade.

`InterDriver` ([Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php:86](../../../Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php#L86)) JÁ TEM método `pdf(string $nossoNumero): string` retornando PDF base64 via `InterApi->getPdfNossoNumero()`. Falta só wiring: rota + controller + botão na UI já existente `/boletos` (Onda 1 MVP).

Pré-req: titulo precisa ter `nosso_numero` populado (emissão prévia manual via tinker OU via fatia C de auto-emissão). Smoke usa titulo manualmente criado com nosso_numero gravado direto no DB.

## Acceptance criteria

- [ ] Rota nomeada `financeiro.boletos.pdf` — `GET /financeiro/boletos/{titulo}/pdf` com middleware `['auth','business','permission:financeiro.boletos.view']`
- [ ] Controller `Modules\Financeiro\Http\Controllers\BoletoController@imprimir(Titulo $titulo)` com route-model-binding (escopo Tier 0 automático via global scope `business_id`)
- [ ] Retorna 422 PT-BR `"Boleto ainda não foi emitido neste banco"` se `$titulo->nosso_numero` IS NULL
- [ ] Resolve driver via `app(BoletoDriverFactoryContract::class)->resolve($titulo->business_id)` — se factory não existe, criar adapter inline `match($business->boleto_driver_active) { 'inter' => new InterDriver($config), default => throw }`
- [ ] Try/catch sobre `$driver->pdf()` — exception API Inter → 502 PT-BR `"Banco indisponível, tente em alguns segundos"` + log com `[REDACTED]` no cpf_cnpj pagador (skill commit-discipline)
- [ ] Response `application/pdf` inline: `response(base64_decode($pdf), 200)->header('Content-Type','application/pdf')->header('Content-Disposition','inline; filename="boleto-'.$titulo->numero.'.pdf"')`
- [ ] Botão `<Button variant="outline" onClick={() => window.open(route('financeiro.boletos.pdf', titulo.id), '_blank')}>Imprimir 2ª via</Button>` em `Pages/Financeiro/Boletos/Index.tsx` linha da tabela — só renderiza se `titulo.nosso_numero != null`
- [ ] Pest Feature `Modules/Financeiro/Tests/Feature/ImprimirBoletoInterTest.php` (5 cenários): (1) titulo com nosso_numero biz=1 retorna 200 + content-type pdf; (2) titulo sem nosso_numero retorna 422 PT-BR; (3) sem permission retorna 403; (4) biz=99 logado tenta titulo biz=1 → 404 (Tier 0 enforcement); (5) Inter API throw mock → 502 PT-BR + log redacted
- [ ] Zero migration, zero model novo, zero ADR (puro wiring dentro de contrato existente)

## Plano de implementação

1. **Localizar controller** — `grep -r "BoletoController" Modules/Financeiro/` — adicionar método `imprimir`; se controller não existe ainda, criar minimal `Modules/Financeiro/Http/Controllers/BoletoController.php` com apenas action `imprimir`
2. **Rota** — `Modules/Financeiro/Routes/web.php` add `Route::get('boletos/{titulo}/pdf', [BoletoController::class,'imprimir'])->name('boletos.pdf')` dentro do group existente
3. **Driver resolver** — checar se `BoletoDriverFactory` existe em `Modules/RecurringBilling/Services/Boleto/`; se não, criar service simples lendo `rb_boleto_credentials` da tabela (DTO `BoletoConfig`)
4. **Permission Spatie** — registrar `financeiro.boletos.view` no boot do FinanceiroServiceProvider se ainda não estiver (R-FIN-002)
5. **Frontend** — coluna nova ou ícone Print na DataTable existente — usar `lucide-react` Printer icon
6. **Test fixture** — usar `InterApi` mock via Laravel `$this->mock(InterApi::class)` retornando `'%PDF-1.4\n... fake binary ...'` ou intercept via Saloon mock se driver migrar
7. **Smoke local** — criar titulo manual via tinker biz=1 com `nosso_numero='000000123'` (não chama Inter de verdade), clica botão, verifica PDF abre. Smoke real biz=4 só com Inter homologação configurado (US-RB-045 desbloqueada)

## Pegadinhas

- ⚠️ **Cert temp file**: InterDriver linha 91-99 grava `.pem` em `sys_get_temp_dir()` — em CI sem dir escrita, fail. Test usa Storage::fake('local') OU mock direto do `InterApi`
- ⚠️ **Tier 0 enforcement**: route-model-binding usa global scope `business_id` automaticamente — confirmar `Titulo` model tem trait `HasBusinessScope` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ⚠️ **PDF size**: boletos Inter ~80-300KB; `Content-Length` setado pelo Laravel auto. Timeout default 30s ok
- ⚠️ **window.open `_blank`** — bloqueio popup; se Wagner reclamar, trocar por `<a target="_blank" href=...>` direto
- ⚠️ **Idempotência GET**: chamada idempotente — Inter API `getPdfNossoNumero` é GET no lado deles, sem custo de chamar 2x

## Out of scope (NÃO fazer aqui — fatias separadas)

- ❌ Emissão de boleto novo (US-FIN-016 — auto-emite via Observer)
- ❌ Fix BUG-3 purchase→titulo_pagar (US-FIN-015)
- ❌ Suporte a outros bancos no botão (C6/Asaas — fatia futura quando driver wireado)
- ❌ Download em massa (vários boletos zip) — não pediu
- ❌ Print server-side (envio direto pra impressora) — apenas abre PDF, usuário usa Ctrl+P do browser

## Refs

- [Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php#L86](../../../Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php#L86) — método `pdf()` base
- US-FIN-010 ARQ-0003 Boleto via Strategy (acima)
- [memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md](../RecurringBilling/RUNBOOK-inter-pj.md) — config cert Inter
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant
- Skill `multi-tenant-patterns` (Tier A) — Pest cross-tenant biz=1 vs biz=99 obrigatório

### US-FIN-015 · Fix BUG-3 — Listener cria titulo_pagar pra purchase com payment_status=due

> owner: wagner · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: —

## Contexto

Pedido Wagner 2026-05-13 — fatia B do epic Financeiro/integrar-venda. **BUG-3 documentado em [CHANGELOG.md:23](CHANGELOG.md#L23):** `sincronizarDeVenda` retorna null pra `type !== 'sell'` → compras com `payment_status=due` não geram `fin_titulo_pagar`. Resultado: tela `/contas-pagar` está vazia mesmo com compras a prazo no UltimatePOS core. Bloqueia desbloqueio das US-FIN-004/005/006 (Contas a Pagar UI integration).

Spec gherkin já existe em §R-FIN-004 abaixo — implementação do contrato documentado, não decisão arquitetural nova.

## Acceptance criteria

- [ ] `Modules\Financeiro\Listeners\CriarTituloDeVenda::handle()` deixa de retornar early pra `$event->transaction->type !== 'sell'`
- [ ] Refatorar pra dispatch interno via `match($transaction->type) { 'sell' => $this->criarReceber($transaction), 'purchase' => $this->criarPagar($transaction), default => null }`
- [ ] `criarPagar(Transaction $transaction)` cria `fin_titulo_pagar` quando `payment_status === 'due'` com:
  - `origem = 'compra'`, `origem_id = $transaction->id`
  - `fornecedor_id = $transaction->contact_id` (valida `$contact->type IN ['supplier','both']` — se customer puro, log warning + skip)
  - `valor_total = $transaction->final_total`
  - `vencimento = $transaction->transaction_date + ($business->prazo_padrao_dias_pagar ?? 30) dias`
  - `business_id = $transaction->business_id` (Tier 0)
  - `parcela_numero = 1` (single) OU N rows se `transaction->payment_lines` tem N lines com `is_advance=false` e `paid_on > today`
- [ ] **DRY** — extrai `criarParcelas(Titulo $base, Collection $paymentLines): Collection<Titulo>` reutilizado em criarReceber + criarPagar
- [ ] Idempotência preservada — unique index `(business_id, origem, origem_id, parcela_numero)` já existe; re-disparo de evento não duplica
- [ ] **Zero regressão** type=sell — suite gold `Modules/Financeiro/Tests/Feature/AutoCriacaoTituloVendaTest.php` continua 6/6 PASS
- [ ] Pest Feature novo `Modules/Financeiro/Tests/Feature/AutoCriacaoTituloCompraTest.php` (5 cenários):
  1. purchase due single → 1 titulo_pagar com vencimento = transaction_date+30d
  2. purchase paid → nenhum titulo (early return correto)
  3. purchase due 3 parcelas → 3 titulos sequenciais com `parcela_numero=1,2,3`
  4. idempotência: dispatch 2x mesmo evento → 1 titulo (unique constraint catch + log)
  5. Tier 0: purchase biz=1 NÃO cria titulo em biz=99 (smoke cross-tenant obrigatório skill `multi-tenant-patterns`)
- [ ] Atualizar [CHANGELOG.md](CHANGELOG.md) — mover BUG-3 de "🟡 BUG-3" pra "✅ BUG-3 fix (US-FIN-015)"

## Plano de implementação

1. **Repro** — abrir `audits/2026-04-25-bugs-integration-test.md` (referenciado no CHANGELOG) — confirmar stack trace + linha exata do early-return
2. **Job órfão** — varrer `Modules/Financeiro/Jobs/` por job dispatched mas que não faz nada quando type=purchase (CHANGELOG menciona "Job órfão") — deletar ou wirear
3. **Refactor** — `CriarTituloDeVenda::sincronizarDeVenda()` → renomear pra `sincronizar()` (descritivo); manter wrapper deprecated 1 release
4. **Contact validation** — `Contact::find($contact_id)->type` em UltimatePOS é coluna varchar com valores `customer|supplier|both`; usar Enum cast PHP 8.4 OU validation em FormRequest se houver
5. **payment_lines fonte** — UltimatePOS grava parcelamento em `transaction_payments` (linhas pagas) E `transaction_sell_lines_purchase_lines` (não relevante aqui). Pra DUE não-pago, usar `transactions.final_total` + business config; pra parcelado due, usar `transactions.additional_notes` JSON (campo legacy) ou criar coluna `parcelas` se faltar — investigar primeiro
6. **Test fixtures** — Pest factories `TransactionFactory::purchase()->due()->forBusiness(1)` (criar se faltar)

## Pegadinhas

- ⚠️ **Job órfão**: CHANGELOG menciona "Job órfão" — antes de tocar Listener, audit `grep -r "TransactionSaved" Modules/Financeiro/Jobs/` pra entender se existe job duplicado/morto que precisa morrer junto
- ⚠️ **`type` enum UltimatePOS**: além de sell/purchase tem `sell_return`, `purchase_return`, `expense`, `opening_stock`, `production_*`, `stock_adjustment`, `stock_transfer` — `match` precisa ter `default => null` explícito (não cair em criarReceber por acidente)
- ⚠️ **Contact `type=both`**: cliente que também é fornecedor (raro mas existe — Larissa tem alguns) — purchase com Contact type=both é válido, NÃO logar warning nesse caso
- ⚠️ **Idempotência via unique index**: insert duplicado lança `Illuminate\Database\QueryException` SQLSTATE[23000] — catch + log info "idempotência: titulo já existe" SEM rethrow (evento pode ser retried em fila)
- ⚠️ **payment_status='due'**: UltimatePOS também tem `partial` (pago parcial) — decidir: criar titulo_pagar com valor_aberto = final_total - paid? Manter simples nesta fatia: só FULL due cria titulo; partial fica out-of-scope (BUG separado)
- ⚠️ **Tier 0**: skill `multi-tenant-patterns` exige fixture biz=99 + assert query biz=1 não vaza — sem isso PR é rejeitado em review

## Out of scope (NÃO fazer)

- ❌ Fix BUG-1/BUG-2 (TituloBaixa/CaixaMovimento missing em transaction_payment) — fatia separada Onda 2
- ❌ Fix BUG-4 cosmético (due→paid marca cancelado) — fatia separada
- ❌ UI Contas a Pagar — telas existem (Onda 1); só precisa do backend gerar os títulos
- ❌ Cálculo juros/multa pra vencido — R-FIN-006 Onda 2 separado
- ❌ Suporte a `partial` payment_status — fica out-of-scope (criar BUG-5?)

## Refs

- §R-FIN-004 (abaixo) — gherkin oficial
- [CHANGELOG.md:23](CHANGELOG.md#L23) — BUG-3 catalogado
- `Modules/Financeiro/audits/2026-04-25-bugs-integration-test.md` — root cause + repro
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0
- Skill `multi-tenant-patterns` (Tier A always-on)
- `Modules/Financeiro/Tests/Feature/AutoCriacaoTituloVendaTest.php` — gold suite preservada
- `Modules/Financeiro/Tests/Feature/TransactionObserverIntegrationTest.php` — 3 SKIP relacionados (Onda 1 deixou)

### US-FIN-016 · Auto-emite boleto Inter ao criar titulo_receber (Observer + Job idempotente)

> owner: wagner · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: US-RB-045

## Contexto

Pedido Wagner 2026-05-13 — fatia C do epic Financeiro/integrar-venda + paridade OfficeImpresso. **Esta é a peça que fecha o ciclo**: venda due → titulo_receber criado (Onda 1 funciona) → boleto Inter emitido automaticamente → linha digitável + PDF persistidos no titulo → tela `/boletos` lista pronto pra imprimir (US-FIN-014).

Bloqueado por **US-RB-045** (Inter PJ Banking API homologação biz=4) — sem cert válido + client_id/secret, smoke real impossível. Pode rodar dev/test isolado com mocks antes, mas merge production exige homolog SEFAZ-Inter passar.

`InterDriver::emitir()` ([Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php:25](../../../Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php#L25)) já implementa cria-boleto via API + retorna `BoletoResult` DTO. Falta wiring assíncrono via Observer + Job.

Trade-off arquitetural: persistir PDF base64 inline na coluna (`pdf_base64` longtext ~300KB/linha) OU storage externo (`pdf_path` apontando pra `storage/app/boletos/{biz}/{id}.pdf`). **Recomendação**: storage externo — tabela leve + backup separado + cleanup periódico. Decisão antes da migration.

## Acceptance criteria

### Schema
- [ ] Migration `2026_05_13_HHMMSS_add_boleto_columns_to_fin_titulos.php`:
  - `nosso_numero` VARCHAR(50) NULL + UNIQUE index `(business_id, nosso_numero)`
  - `linha_digitavel` VARCHAR(60) NULL
  - `codigo_barras` VARCHAR(60) NULL
  - `pix_qrcode` TEXT NULL
  - `pdf_path` VARCHAR(255) NULL (storage relativo: `boletos/{biz}/{id}.pdf`)
  - `boleto_status` ENUM('pendente','emitido','falha_emissao','cancelado') DEFAULT 'pendente'
  - `boleto_emitido_em` TIMESTAMP NULL
  - `boleto_falha_motivo` TEXT NULL
- [ ] Idempotente (Up: check `Schema::hasColumn` antes de add); Down reverso

### Observer
- [ ] `Modules\Financeiro\Observers\TituloObserver::created(Titulo $titulo)` dispatch `EmitirBoletoJob::dispatch($titulo->id, $titulo->business_id)` quando TODAS true:
  - `$titulo->tipo === 'receber'`
  - `$titulo->valor_aberto > 0`
  - `$business->boleto_driver_active === 'inter'` (coluna em businesses OU config `fin_boleto_config`)
  - `$business->hasBoletoCredentials()` (cert + client_id configurados em `rb_boleto_credentials`)
  - `$titulo->cliente->permite_boleto !== false` (opt-out por cliente — Larissa B2C raramente quer boleto)
- [ ] Registrado em `FinanceiroServiceProvider::boot()`: `Titulo::observe(TituloObserver::class)`

### Job
- [ ] `Modules\RecurringBilling\Jobs\EmitirBoletoJob` (criar se não existe) com:
  - Queue `financeiro-boleto` (dedicated — não bloqueia financeiro genérica)
  - `tries = 3`, `backoff = [60, 120, 240]` (segundos)
  - Constructor `(int $tituloId, int $businessId)` — NUNCA `Titulo $titulo` direto (skill multi-tenant: business_id explícito em fila)
  - `handle()`: `Titulo::withoutGlobalScopes()->where('business_id',$this->businessId)->findOrFail($this->tituloId)` (job já scopa explícito)
  - **Idempotência guard**: `if ($titulo->nosso_numero) return;` no início
  - **Lock**: `Cache::lock("emitir-boleto-{$tituloId}", 60)->block(5, function() { ... })` — evita race 2 jobs paralelos
  - Resolve `BoletoDriverFactory::resolve($businessId)` → `BoletoDriverContract`
  - Chama `$driver->emitir([pagador_nome, pagador_cpf_cnpj, pagador_*, valor, data_vencimento, numero_documento => "FIN-{$titulo->id}", instrucoes => $business->boleto_instrucoes])`
  - Persiste: `$titulo->update(['nosso_numero' => $r->nossoNumero, 'linha_digitavel' => $r->linhaDigitavel, 'codigo_barras' => $r->codigoBarras, 'pix_qrcode' => $r->pixQrCode, 'pdf_path' => $this->salvarPdf($r->pdfBase64, $titulo), 'boleto_status' => 'emitido', 'boleto_emitido_em' => now()])`
  - `salvarPdf()`: `Storage::disk('local')->put("boletos/{$titulo->business_id}/{$titulo->id}.pdf", base64_decode($r->pdfBase64))` → retorna path relativo
  - `failed(Throwable $e)`: `Titulo::find($this->tituloId)?->update(['boleto_status' => 'falha_emissao', 'boleto_falha_motivo' => substr($e->getMessage(), 0, 500)])` + Log alert PII redacted

### Factory + Config
- [ ] `Modules\RecurringBilling\Services\Boleto\BoletoDriverFactory::resolve(int $businessId): BoletoDriverContract` lê `rb_boleto_credentials` table (driver + config encrypted)
- [ ] Bindar em `RecurringBillingServiceProvider::register()` como singleton
- [ ] Coluna `businesses.boleto_driver_active` ENUM('inter','c6','asaas','cnab','none') DEFAULT 'none' (migration separada OU adicionar nesta)

### Tests Pest (`Modules/Financeiro/Tests/Feature/AutoEmissaoBoletoInterTest.php`)
- [ ] Cenário 1: biz sem `boleto_driver_active='inter'` → criar titulo NÃO dispatch (Bus::fake assertNotDispatched)
- [ ] Cenário 2: biz com inter + cert → criar titulo dispatch job, job executa mock InterApi → titulo recebe nosso_numero + linha_digitavel + pdf_path
- [ ] Cenário 3: idempotência: rodar handle() 2x mesmo titulo → 1 chamada Inter API (lock + guard)
- [ ] Cenário 4: Inter API throw → status=falha_emissao + motivo gravado + log PII redacted
- [ ] Cenário 5: Tier 0 cross-tenant: dispatch biz=1 não toca titulo biz=99 (skill multi-tenant-patterns)
- [ ] Cenário 6: cliente com `permite_boleto=false` → NÃO dispatch
- [ ] Cenário 7: titulo tipo='pagar' → NÃO dispatch (só receber emite boleto pra cobrar terceiro)

### Smoke real biz=4 (ROTA LIVRE)
- [ ] **Pré-flight**: US-RB-045 desbloqueada + cert Inter biz=4 carregado em `rb_boleto_credentials` (homologação)
- [ ] Criar titulo manual biz=4 valor R$ [redacted Tier 0] vencimento +5d via tinker
- [ ] Job processa → Inter homolog retorna boleto válido → linha_digitavel passa validação DV mod10 → PDF salvo em `storage/app/boletos/4/{id}.pdf` abre
- [ ] Cancelar boleto via tinker `$driver->cancelar($titulo->nosso_numero, 'ACERTOS')` — não deixar lixo Inter homolog
- [ ] Wagner aprova screenshot do boleto homolog antes de prosseguir prod

### Docs
- [ ] `memory/requisitos/Financeiro/RUNBOOK-emitir-boleto-inter.md` com: config cert, config business, troubleshooting 5 erros comuns Inter (401 cert inválido, 403 conta sem PJ, 422 CPF pagador inválido, 500 Inter outage, timeout)
- [ ] Atualizar [CHANGELOG.md](CHANGELOG.md) Onda 3 → mover boleto Strategy pra Entregue

## Plano de implementação ordenado

1. **Decisão arquitetural** (30min): pdf_path vs pdf_base64. Recomendação storage externo — confirmar com Wagner ou ADR mini se preferir base64
2. **Migration** (1h): colunas + índices + idempotente
3. **Factory + Config** (1h): BoletoDriverFactory + binding + `businesses.boleto_driver_active`
4. **Observer + boot wiring** (30min)
5. **Job** (2h): EmitirBoletoJob com lock + idempotência + failed handler + PII redact
6. **Tests Pest** (2h): 7 cenários — mocks InterApi via `$this->mock(InterApi::class)` ou Saloon Mock
7. **Smoke local** (30min): tinker biz=1 com mock cert + assert DB persistido
8. **Smoke real biz=4** (1h): só após US-RB-045 — Wagner participa
9. **Docs RUNBOOK** (30min)

## Pegadinhas críticas

- ⚠️ **Storage path Hostinger**: `storage/app/boletos/` precisa permission 755 + writable; smoke deploy primeiro
- ⚠️ **Cert mTLS arquivo físico**: `InterDriver::writeTempCert()` linha 91-99 grava em `sys_get_temp_dir()` — em CT 100 ok; em **Hostinger** investigar se tmp persiste entre requests (PHP-FPM pode limpar). Workaround: gravar em `storage/app/inter-cert-cache/{biz}/` permanente
- ⚠️ **Race condition**: 2 jobs paralelos mesmo titulo (retry sem idempotência) → 2 boletos Inter cobrando o cliente 2x. Lock pessimista OBRIGATÓRIO. Test cenário 3 verifica.
- ⚠️ **LGPD**: PDF contém CPF/CNPJ pagador embed binário. Retenção: enquanto titulo `valor_aberto > 0` mantém; após quitado +90d arquivar S3 cold storage (fora-de-escopo desta fatia mas anotar)
- ⚠️ **Inter rate limit**: ~10 boletos/seg conta PJ básica. Em criação massiva (importação 1000 vendas pendentes), job precisa `RateLimiter::for('inter-emissao')->limit(8)->everySeconds(1)` — anotar pra Onda 4 batch
- ⚠️ **business.boleto_driver_active='none'**: default. Não emitir nunca a menos que admin explícito ative — evita emissão inadvertida em biz que não pediu
- ⚠️ **ROTA LIVRE Modules/Vestuario (biz=4)**: 99% volume B2C balcão → boleto só faz sentido B2B/atacado. `cliente.permite_boleto` flag opt-in evita emitir pra todo CPF de varejo. **CONFIRMAR com Wagner**: default `null` (não-emite) ou `true` (emite e cliente desativa caso a caso)?
- ⚠️ **InterDriver retorna `pdfBase64`** no `BoletoResult` — converter pra path no Job, não persistir base64 no DB
- ⚠️ **Numero_documento Inter unique**: usar `"FIN-{$titulo->id}"` (8-12 chars); se titulo deletado e recriado com mesmo id (impossível em Eloquent normal), Inter rejeita 409 — sufixar `-RETRY-{n}` em retry após 409
- ⚠️ **`numero_documento` aceita apenas alfanumérico ≤30 chars** na Inter API — sanitizar

## Out of scope (NÃO fazer aqui)

- ❌ Boleto C6/Asaas auto-emissão — driver existe, mas wiring é fatia separada (Factory já abstrai)
- ❌ NFe55 automática pós-pagamento — US-RB-001 já existe separada
- ❌ Conciliação webhook Inter pagou → baixa titulo — `ProcessInterWebhookJob` já existe; integração com Titulo é fatia separada (na real é US-RB-041)
- ❌ PIX cob — `InterPixCobDriver` separado, fatia futura
- ❌ Régua cobrança quando boleto vence — US-RB-031 separado
- ❌ Storage S3 boletos — local agora; S3 depois (sem ADR mãe ainda)
- ❌ Batch backfill (emitir boleto pra TODOS titulos abertos existentes) — comando artisan separado se Wagner pedir

## Refs

- [Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php](../../../Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php) — base
- [memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md](../RecurringBilling/RUNBOOK-inter-pj.md) — config homolog
- [memory/requisitos/RecurringBilling/SPEC.md](../RecurringBilling/SPEC.md) §US-RB-001 — peça seguinte (NFe55)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 IRREVOGÁVEL
- US-RB-045 — Inter PJ Banking API (bloqueador homolog)
- US-RB-040 — Cobertura Pest 3 drivers (relacionado, deve seguir junto)
- Skill `multi-tenant-patterns` (Tier A) — Pest cross-tenant
- Skill `commit-discipline` (Tier A) — PII redact + 1 PR ≤300 LOC (esta fatia talvez vire 2 PRs: migration+observer separado de job+tests)

## 3. Regras de negócio (Gherkin)

### R-FIN-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Financeiro
Então só vê registros com `business_id = A`
E qualquer query manual que não inclua o scope é bloqueada por GlobalScope
```

**Implementação:** Trait `Modules\Financeiro\Models\Concerns\BusinessScope` com `addGlobalScope` em todo Model do módulo. Controllers fazem `where('business_id', session('user.business_id'))` defensivamente.
**Testado em:** `Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest` — valida 12 rotas com 2 businesses + assert nenhum vazamento cross-business.

### R-FIN-002 · Permissão Spatie `financeiro.contas_receber.view`

```gherkin
Dado que um usuário não tem `financeiro.contas_receber.view`
Quando ele acessa GET /financeiro/contas-receber
Então recebe 403
```

**Implementação:** `Route::middleware('can:financeiro.contas_receber.view')` no group do módulo. Permissões registradas no `ServiceProvider::boot` via `Permission::create()` se não existir, gated por config flag.
**Testado em:** `Modules/Financeiro/Tests/Feature/SpatiePermissionsTest` — 12 permissões × 2 direções (sem/com) = 24 asserts.

### R-FIN-003 · Auto-criação de título a partir de venda `due`

```gherkin
Dado uma venda finalizada com `payment_status = due` e `final_total = 1000`
Quando o evento `Modules\Connector\Events\TransactionSaved` é disparado
Então o módulo Financeiro cria um `titulo_receber` com `valor_total = 1000`, `status = aberto`, `origem = venda`, `origem_id = transaction.id`, `vencimento = transaction.transaction_date + business.prazo_padrao_dias`
E o título tem `business_id = transaction.business_id`
E criar 2x não duplica (idempotência por `origem + origem_id`)
```

**Implementação:** `Modules\Financeiro\Listeners\CriarTituloDeVenda` escuta `TransactionSaved` (do core) e roda em queue `financeiro`. Idempotência: `unique index (business_id, origem, origem_id, parcela_numero)`.
**Testado em:** `Modules/Financeiro/Tests/Feature/AutoCriacaoTituloVendaTest` — 6 cenários (paga/parcial/due/parcelado/cancelada/refunded).

### R-FIN-004 · Auto-criação de título a partir de compra `due`

```gherkin
Dado uma compra (purchase) finalizada com `payment_status = due`
Quando o evento `TransactionSaved` é disparado com `type = purchase`
Então cria `titulo_pagar` análogo a R-FIN-003
```

**Implementação:** Mesmo listener, branch por `transaction.type`.
**Testado em:** `AutoCriacaoTituloCompraTest`.

### R-FIN-005 · Idempotência de baixa por `idempotency_key`

```gherkin
Dado um título com valor_aberto = 500
Quando duas requests POST /baixar chegam com mesmo `idempotency_key`
Então apenas a primeira efetiva a baixa
E a segunda retorna 200 com o mesmo registro de baixa (sem efeito colateral)
E `caixa_movimentos` não é duplicado
```

**Implementação:** `BaixaService::registrar()` faz `firstOrCreate(['idempotency_key' => $key], [...])` em transação. Frontend gera `idempotency_key = uuid()` no submit.
**Testado em:** `BaixaIdempotenciaTest` — 100 requests concorrentes mesma key = 1 baixa.

### R-FIN-006 · Cálculo de juros de mora

```gherkin
Dado um título a pagar vencido há 10 dias
E o business tem config `juros_mora_diario = 0.0033`, `multa_atraso = 0.02`
E o valor original = 1000
Quando Larissa registra pagamento hoje
Então o sistema sugere `valor_total_pagar = 1000 * (1 + 0.02) + (1000 * 0.0033 * 10) = 1053`
E Larissa pode override (com motivo audit log)
```

**Implementação:** `JurosMoraService::calcular(Titulo, dataPagamento)` retorna `{principal, multa, juros, total}`. UI pre-fill no modal de pagamento.
**Testado em:** `JurosMoraServiceTest` — datatable com 8 cenários (0d, 1d, 30d, com/sem multa, valores quebrados).

### R-FIN-007 · Conciliação OFX idempotente por hash do arquivo

```gherkin
Dado um arquivo OFX com hash SHA256 = X
Quando Larissa importa o mesmo arquivo 2x
Então a 2ª importação detecta duplicidade e retorna `conciliacao_run` existente
E nenhuma transação extra é criada
```

**Implementação:** `conciliacao_runs.file_hash` UNIQUE por `business_id`. Antes de parse, calcular hash + check.
**Testado em:** `ConciliacaoIdempotenciaTest`.

### R-FIN-008 · Soft delete preserva integridade contábil

```gherkin
Dado uma conta bancária com 50 movimentos históricos
Quando o usuário tenta deletar essa conta
Então a request é bloqueada com erro "Conta com histórico não pode ser removida"
E a conta pode ser inativada (`status = inativo`)
E continua aparecendo em relatórios históricos mas não em selects de novos lançamentos
```

**Implementação:** Trait `SoftDeletes` + override `delete()` que verifica `caixa_movimentos()->exists()`.
**Testado em:** `ContaBancariaSoftDeleteTest`.

### R-FIN-009 · Plano de contas BR pré-seedado por business

```gherkin
Dado um novo business é criado
Quando o evento `BusinessCreated` (UltimatePOS core) dispara
Então 47 contas do plano padrão Receita Federal são seedadas com `business_id` correto
E o tenant pode editar (renomear/criar/inativar) mas códigos protegidos (`1.1.01.001` Caixa, `3.1.01.001` Receita Bruta) não podem ser deletados
```

**Implementação:** `Modules\Financeiro\Listeners\SeedPlanoContasPadrao` + array em `database/seed-data/plano_contas_br.php`.
**Testado em:** `PlanoContasSeedTest` — novo business → 47 contas; tentar delete protegida → 422.

### R-FIN-010 · DRE respeita regime do business (caixa vs competência)

```gherkin
Dado business com `regime_contabil = competencia`
Quando uma venda é emitida em 2026-04-30 com vencimento 2026-05-15
Então a receita aparece no DRE de abril (data da venda)

Dado business com `regime_contabil = caixa`
Quando a mesma venda é baixada apenas em 2026-05-20
Então a receita aparece no DRE de maio (data da baixa)
```

**Implementação:** `RelatorioService::dreQuery(Business)` switch em regime, group by `transaction_date` ou `paid_at`.
**Testado em:** `DreRegimeTest` — mesmo business com config diferente → DRE diferente.

### R-FIN-011 · Boleto remessa não duplica

```gherkin
Dado um título a receber sem boleto emitido
Quando Larissa clica "Emitir boleto" 2x rápido
Então apenas 1 PDF/linha-digitável é gerado
E a 2ª chamada retorna o boleto existente
```

**Implementação:** `boleto_remessa.titulo_id` UNIQUE WHERE status IN (gerado, enviado). Re-emitir só após cancelar anterior.
**Testado em:** `BoletoIdempotenciaTest`.

### R-FIN-012 · Webhook gateway com `event_id` único

```gherkin
Dado um webhook do Asaas chega com `event_id = ASAAS-X-001`
Quando o mesmo `event_id` chega 2x (Asaas at-least-once)
Então a 2ª request retorna 200 sem reprocessar
E `boleto_remessa.status` não muda 2x
```

**Implementação:** Tabela `pg_webhook_events` (compartilhada com PaymentGateway de RecurringBilling) com `(provider, event_id) UNIQUE`.
**Testado em:** `BoletoWebhookIdempotenciaTest`.

### R-FIN-013 · Permissão `financeiro.relatorios.share` para link público

```gherkin
Dado um usuário sem `financeiro.relatorios.share`
Quando ele tenta gerar um link compartilhável de DRE
Então recebe 403
```

**Implementação:** Permissão separada de `financeiro.relatorios.view`. Token assinado HMAC-SHA256 com payload `{business_id, periodo, exp}`. Validação no controller `share`.
**Testado em:** `RelatorioShareTest`.

### R-FIN-014 · Auditoria via `activity_log` Spatie

```gherkin
Dado uma baixa de R$ [redacted Tier 0] em 2026-04-24
Quando Wagner consulta `activity_log`
Então existe row com `causer_id = user`, `subject_type = TituloBaixa`, `subject_id`, `description = baixa.criada`, `properties.valor = 500`
```

**Implementação:** Trait `LogsActivity` em todo Model crítico (`Titulo`, `TituloBaixa`, `CaixaMovimento`).
**Testado em:** `AuditoriaTituloTest`.

### R-FIN-015 · Pré-população de "agora" sem shift +3h

```gherkin
Dado o business tem `time_zone = America/Sao_Paulo`
Quando Larissa abre o form de baixa
Então o campo `data_baixa` vem pré-preenchido com `format_now_local()` (não `format_date(now())`)
E o valor refletido é o "agora" no fuso do business, sem shift histórico
```

**Implementação:** Helpers `format_now_local()` (já existe em `App\Util`) — ver auto-memória `feedback_format_now_local_e_default_datetime.md`.
**Testado em:** `FormPrePopulateTest`.

## 4. Decisões pendentes

- [ ] Gateway boleto/PIX MVP: Sicoob (banco da ROTA LIVRE) vs Asaas (multi-banco)?
- [ ] Plano free vs Pro: limite 50 títulos/mês ou só remover boleto/PIX/OFX do free?
- [ ] Take rate sobre boleto: oimpresso retém vs split com tenant?
- [ ] DRE básico já no MVP ou só na Onda 4?
- [ ] OCR de boleto: Tesseract local ou API (AWS Textract / Google Cloud Vision)?

## 5. Referências cruzadas

- **Auto-memória:** `reference_ultimatepos_integracao.md`, `reference_db_schema.md`, `feedback_format_now_local_e_default_datetime.md`, `cliente_rotalivre.md`
- **Origem da ideia:** `_Ideias/Financeiro/evidencias/conversa-claude-2026-04-mobile.md` (após import)
- **Design:** `memory/requisitos/_DesignSystem/adr/ui/0006-padrao-tela-operacional.md`
- **Módulos relacionados:** [NfeBrasil](../NfeBrasil/), [RecurringBilling](../RecurringBilling/)

### US-FIN-017 · Boletos — Sheet Emitir multi-título (bulk emission)

> owner: wagner · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

Sheet lateral em `/financeiro/boletos` pra emitir N boletos de uma vez a partir de títulos a receber em aberto.

**Origem:** Q2 do amendment Q1-Q5 (sessão 2026-05-14) — cortado do F3 inicial #845 pra manter 1 PR = 1 intent. Pré-requisito de escala quando >10 boletos/dia.

**Acceptance criteria:**
- Botão "Emitir boleto" no header da tela `/financeiro/boletos` (orange primary)
- Click abre Sheet lateral 600px com header "a partir de títulos a receber"
- Filter row: dropdown "Conta emissora" + input "Vencimento padrão" (date)
- Lista checkbox Titulo (status=aberto, tipo=receber, sem boleto emitido ainda) — paginada
- Backend: `BoletoBatchService::emitirBatch(business_id, titulo_ids, conta_id, vencimento)` em transação — itera + delega `TituloService::emitirBoleto`
- Idempotência: `idempotency_key Str::uuid` (ADR tech/0001)
- Error handling parcial: se 1 falha, mostra `{ok: [...], failed: [...]}` e abre só os ok
- Pest GUARD: cross-tenant + idempotência + bulk transaction

**Refs:** F3 PR #845, `memory/requisitos/Financeiro/boletos-visual-comparison.md` §Q2, `prototipo-ui/prototipos/boletos/cowork-app.jsx` §SheetEmitirBoleto
**Estimate:** 6h (IA-pair fator 10x)

### US-FIN-018 · Boletos — Sheet Remessa/Retorno CNAB upload + processing

> owner: wagner · priority: p2 · estimate: 16h · status: todo · type: story
> blocked_by: —

Sheet lateral pra upload de arquivo de Remessa CNAB (.REM) + processamento de arquivo de Retorno CNAB (.RET).

**Origem:** Q3 do amendment Q1-Q5 (sessão 2026-05-14) — cortado do F3 #845 porque `CnabDirectStrategy` hoje é MOCK. Onda 2 quando virar prod.

**Depende:** ADR `arq/0011-cnab-direct-strategy-prod.md` pendente + lib `eduardokum/laravel-boleto` fork.

**Acceptance criteria:**
- Botão "Remessa/Retorno" no header (outline, ao lado de "Emitir boleto")
- Sheet com 2 tabs: Remessa + Retorno
- Tab Remessa: dropdown conta + lista pendentes + botão "Gerar arquivo REM" (download CNAB 240/400)
- Tab Retorno: file upload .RET + parser → summary (liquidados/vencidos/rejeitados) + botão "Processar"
- Backend: `CnabDirectStrategy::gerarRemessa()` + `CnabDirectStrategy::processarRetorno()`
- Idempotency: `hash_sha256` no Arquivo do .RET; mesmo arquivo não processa 2×
- Pest GUARD: parser layout 240 + 400 + cross-tenant

**Estimate:** 16h (M-L)

### US-FIN-019 · Boletos — Drawer timeline cronológica rica via activity_log Spatie

> owner: wagner · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —

Adicionar timeline cronológica no drawer detalhe do `/financeiro/boletos` mostrando todos os eventos do BoletoRemessa (criação → envio → pagamento → cancelamento).

**Origem:** Q5 do amendment Q1-Q5 (sessão 2026-05-14) — drawer F3 #845 é simplificado; timeline rica fica F2.

**Backbone:** `BoletoRemessa` JÁ usa `LogsActivity` trait Spatie. Falta expor frontend.

**Acceptance criteria:**
- Endpoint `GET /financeiro/boletos/{id}/timeline` retorna `[{ts, action, causer_name, properties_diff}]`
- Drawer renderiza seção "Linha do tempo" abaixo dos campos atuais
- Mapping `action → label PT-BR` (created → "Boleto criado", updated.status=enviado → "Enviado ao gateway", etc)
- Pest GUARD: timeline shape + Tier 0

**Estimate:** 4h

### US-FIN-020 · Boletos — Jobs automáticos cobrança (lembrete + ativa + protesto)

> owner: wagner · priority: p2 · estimate: 12h · status: todo · type: story
> blocked_by: US-FIN-017 + Modules/Whatsapp omnichannel canal preferido

Substituir funil de cobrança UI-only por estado backend persistente. 3 jobs automatizam lembrete + escalação + protesto.

**Origem:** Q1 do amendment Q1-Q5 (sessão 2026-05-14) — funil F3 #845 derivava de regras `vencimento BETWEEN today±N`. Onda 2 traz jobs reais.

**Acceptance criteria:**
- `SendLembreteCobrancaJob` — daily 09:00, busca BoletoRemessa `vencimento BETWEEN today+3..today+5 AND status=registrado`, envia WhatsApp ou email
- `EscalateCobrancaAtivaJob` — daily 10:00, `vencimento BETWEEN today-5..today-1 AND status=registrado` → escala pra Eliana (tarefa em ProjectMgmt)
- `ProtestoJob` — weekly seg 08:00, `vencimento < today-30d AND status=vencido AND not_protested` → PDF + CSV pro cartório (manual review Wagner)
- Migration: `cobranca_eventos` table (boleto_id, tipo, data, payload JSON, idempotency_key UNIQUE)
- Funil tela vira backend-driven: counts vem de queries `cobranca_eventos`
- Pest GUARD: jobs idempotentes + Tier 0 + sem regressão UI

**Estimate:** 12h (3 jobs + migration + whatsapp integration + Pest)

### US-FIN-021 · Fluxo de caixa — Margem mínima configurável via business_settings

> owner: wagner · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —

Substituir hardcode R$ [redacted Tier 0] da margem mínima do `/financeiro/fluxo` por config por tenant.

**Origem:** Q3 do amendment Q1-Q4 Fluxo (sessão 2026-05-14) — F3 PR #838 tem hardcode aceito no F1.

**Acceptance criteria:**
- Migration: `ALTER TABLE business_settings ADD COLUMN margem_minima_caixa DECIMAL(15,2) DEFAULT 5000`
- `FluxoCaixaService::projetar()` lê `BusinessSetting::where('business_id')->value('margem_minima_caixa')` fallback 5000
- Tela `/configuracoes/financeiro` — input "Margem mínima de caixa" tabular-nums BRL
- Pest GUARD: respeita business_setting + fallback default + multi-tenant scope

**Estimate:** 2h

### US-FIN-022 · Onda 4d.6.1 — Widget Asaas JS tokenização cartão (PCI-DSS)

> owner: wagner · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —

Integrar widget Asaas JS pra tokenização cartão PCI-DSS SAQ-A. Backend POST /financeiro/cobranca/cartao já pronto (Onda 4d.6 — PR #1140 merged 2026-05-19).

Frontend SheetNovaCobranca step 3 quando tipo=card precisa:
- script tag `<script src="https://www.asaas.com/checkout/asaasTokenization.js">` (sandbox + production URLs via env)
- Form Asaas tokeniza PAN → retorna card_token opaco
- Submit form com `{card_token, card_brand, card_last4, card_holder_name, card_exp_month, card_exp_year}` pra POST /financeiro/cobranca/cartao

**Acceptance:**
- [ ] Script tag carregado dinâmico só quando tipo=card no wizard
- [ ] Tokenização Asaas funciona em sandbox (sandbox.asaas.com)
- [ ] Backend recebe token + cobra cartão com sucesso
- [ ] Pest GUARD: endpoint nunca recebe PAN bruto
- [ ] PCI-DSS SAQ-A compliance documentado no charter

Refs: ADR 0144 + ADR 0170 + PR #1140

### US-FIN-023 · Onda 4d.6.2 — SheetNovaCobranca UI tipo=card (campos cartão)

> owner: wagner · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: US-FIN-022

SheetNovaCobranca (`Pages/Financeiro/Cobranca/_components/SheetNovaCobranca.tsx`) hoje aceita tipo=card no step 1 mas step 3 (Valores) NÃO tem campos cartão. Estender UI.

**Mudanças:**
- Step 3 quando tipo=card mostra campos: holder name + número mask 4-4-4-4 + exp month + exp year + CVV
- Validation client-side básica (Luhn check) antes de tokenizar
- Brand auto-detect via prefix (visa/mastercard/amex/elo/hipercard/diners)
- Submit handler invoca widget Asaas (depende Onda 4d.6.1) → token → POST /financeiro/cobranca/cartao
- UI consistente (Field component, bundle CSS pg-shell-scope)

**Acceptance:**
- [ ] Step 3 mostra campos cartão SOMENTE quando tipo=card
- [ ] Mask número cartão (4-4-4-4) + Luhn validation client
- [ ] Brand auto-detect via prefix
- [ ] Submit handler invoca tokenização Asaas
- [ ] Pest GUARD: tela só renderiza campos cartão pra tipo=card

Refs: ADR 0170 + PR #1140

### US-FIN-024 · Onda 5 — Dogfooding Superadmin (Plan SaaS Oimpresso Premium biz=1)

> owner: wagner · priority: p2 · estimate: 24h · status: todo · type: story
> blocked_by: —

ADR 0170 §Roadmap Onda 5 — Wagner cobra tenants do oimpresso usando o próprio ERP. Dogfooding stack canônica.

**Escopo:**
- Plan "SaaS Oimpresso Premium" criado em Modules/RecurringBilling em biz=1
- Tenants oimpresso (Modules/Superadmin) viram Contact em biz=1
- Superadmin::Subscription vira projection de Modules/RecurringBilling Subscription
- PesaPal driver deprecated (legacy UPOS pra cartão internacional → migrar pra Asaas BR nativo)
- Cobrança PIX Automático BCB pra mensalidade SaaS recorrente

**Acceptance:**
- [ ] Plan "SaaS Oimpresso Premium" seedado em biz=1 RB
- [ ] Cada tenant ativo do oimpresso vira Contact biz=1 (migration backfill)
- [ ] Subscription Superadmin sync com RB Subscription via projection (event listener)
- [ ] Cobrança recorrente PIX Aut. BCB ativa pra 1 tenant piloto (sandbox)
- [ ] PesaPal Controller redirect 301 → docs deprecated
- [ ] LGPD: Contact biz=1 tenants têm opt-in email/whatsapp configurável
- [ ] Pest cross-tenant: biz=99 não vê Contact biz=1 (Tier 0 ADR 0093)
- [ ] Audit append-only via Spatie ActivityLog em superadmin.subscription event

**Riscos:** Médio — afeta cobrança SaaS Wagner ↔ tenants reais. Mitigação: feature flag canary 7d + manter Subscription source-of-truth via fallback.

**Estimate:** ~2500 linhas · ~2-3 dias IA-pair · 24h

Refs: ADR 0170 §Roadmap Onda 5 + ADR 0093 + ADR 0144

### US-FIN-025 · Onda 6 — Cleanup colunas legacy + remover redirects 301

> owner: wagner · priority: p3 · estimate: 8h · status: todo · type: story
> blocked_by: US-FIN-024

ADR 0170 §Roadmap Onda 6 — Cleanup final pós ondas 2-5 estáveis 90d em prod.

**Colunas legacy DDL:**
- Remover `accounts.rb_gateway_credential_id` (substituída por payment_gateway_credentials.conta_bancaria_id)
- Remover `accounts.gateway_*` (gateway_key, gateway_token — cobertos pela tabela nova)

**Controllers/rotas legacy:**
- Remover `Modules/RecurringBilling/Http/Controllers/PesaPalController` (deprecated Onda 5)
- Remover redirects 301 antigos após 90d:
  - /financeiro/boletos → /financeiro/cobranca (criado PR #1142)
  - /webhooks/inter → /paymentgateway/webhooks/inter (Onda 3)
  - /webhooks/c6 → /paymentgateway/webhooks/c6
  - /webhooks/asaas → /paymentgateway/webhooks/asaas

**Pré-requisitos:** Ondas 2-5 estáveis 90d em prod (monitorar Sentry/OTel zero erros) + BoletoRemessa legacy migrado pra Cobranca via cron backfill + PesaPal subscriptions ativas migradas (Onda 5).

**Acceptance:**
- [ ] Migration drop_legacy_columns_accounts testada em sandbox
- [ ] PesaPalController removido + Pest test deletado
- [ ] 4 redirects 301 removidos (rotas 404 retornam corretamente)
- [ ] Smoke prod biz=1 + biz=4 sem regressão (canary 7d)
- [ ] Documentação SCOPE.md PaymentGateway atualizada (sem refs legacy)

**Riscos:** Baixo se ondas 2-5 estáveis 90d. DDL apenas forward — backup DB pré-migrate obrigatório.

**Estimate:** ~500 linhas · ~1 dia IA-pair · 8h

Refs: ADR 0170 §Roadmap Onda 6

---

## Ondas 22-30 — backlog pós-Capterra-inventario 2026-05-19

> Origem: skill `/comparativo Financeiro` rodada 2026-05-19. Estado pós-Ondas 12-21: score 74/100, 87% cobertura funcional, 9.5/10 paridade canon. Wagner aprovou batch P0+P1+P2 (11 US) em sessão de aprovação. Detalhes em [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md).

### US-FIN-026 · UI lista anexos GET no drawer Unificado + thumbnail PDF + delete

> owner: — · sprint: Onda 22 · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: —

Fecha workflow Anexos NF da Onda 20 (atualmente só POST upload — GET lista + thumbnail + delete faltam).

**Acceptance criteria:**
- [ ] `GET /financeiro/unificado/{id}/anexos` retorna lista paginada (id, filename, mime, size, hash_sha256, uploaded_by, uploaded_at)
- [ ] `DELETE /financeiro/unificado/{id}/anexos/{anexoId}` soft delete + audit
- [ ] UI drawer mostra lista com thumbnail PDF (pdf-thumbnail-js OU primeira pagina via Spatie pdf-to-image) ou fallback ícone Lucide FileText
- [ ] Download via signed URL 5min TTL
- [ ] Pest cobre: business_id scope, soft delete, signed URL expiry, permission `financeiro.titulo.anexos.view`/`delete`
- [ ] biz=1 Pest (não biz=4 cliente — ADR 0101)

**Refs:** AUDIT-FUNCOES-2026-05-19.md pendência H · CAPTERRA-INVENTARIO bucket 🟡 P1
**LOC estimado:** ~80

### US-FIN-027 · Pill aprovacao_status na tabela Unificado + filtro workflow

> owner: — · sprint: Onda 22 · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: —

Pendência I do AUDIT-FUNCOES — coluna aprovacao_status existe em fin_titulos (Onda 21 ALTER) e drawer mostra, mas tabela Unificado linha não tem indicador visível.

**Acceptance criteria:**
- [ ] Coluna pill em `Pages/Financeiro/Unificado/Index.tsx` mostrando estado (`pendente` amarelo / `aprovado` verde / `rejeitado` vermelho / NULL invisível back-compat)
- [ ] Filtro novo "Aprovação" no header com chips multi-select (Todas / Pendentes / Aprovadas / Rejeitadas)
- [ ] UnificadoController.parseFilters aceita `aprovacao_status[]`
- [ ] Pest filtro retorna apenas títulos do status pedido + business_id scope
- [ ] biz=1 cross-tenant

**Refs:** AUDIT-FUNCOES-2026-05-19.md pendência I · CAPTERRA-INVENTARIO bucket 🟡 P2
**LOC estimado:** ~50

### US-FIN-028 · Spatie permission financeiro.titulo.aprovar + gate UI

> owner: — · sprint: Onda 22 · priority: p0 · estimate: 1.5h · status: todo · type: story
> blocked_by: —

Endpoint POST /unificado/{id}/aprovar existe (Onda 21) mas sem permission Spatie registrada — qualquer user do biz aprova hoje. Pendência J do AUDIT.

**Acceptance criteria:**
- [ ] Permission `financeiro.titulo.aprovar` registrada em PermissionsSeeder ou DataController.user_permissions()
- [ ] Endpoint aprovar/rejeitar usa `$this->authorize('financeiro.titulo.aprovar')` ou Spatie middleware
- [ ] UI drawer botão Aprovar/Rejeitar usa `can('financeiro.titulo.aprovar')` — esconde se Eliana, mostra se Wagner
- [ ] Pest cobre: 403 sem permission, 200 com permission, solicitação NÃO precisa permission especial (qualquer user solicita)
- [ ] biz=1 cross-tenant

**Refs:** AUDIT-FUNCOES-2026-05-19.md pendência J · CAPTERRA-INVENTARIO bucket 🟡 P3 · Skill multi-tenant-patterns
**LOC estimado:** ~30

### US-FIN-029 · OCR boleto upload — OpenAI Vision API extrai linha digitável + valor + vencimento

> owner: — · sprint: Onda 23 · priority: p1 · estimate: 9h · status: todo · type: story
> blocked_by: —

**KILLER feature** Conta Azul — Eliana cola foto/PDF do boleto recebido → sistema extrai linha digitável (44 dígitos) + valor + vencimento + beneficiário CNPJ automaticamente, pré-preenche form Novo Título.

Mata #1 feedback Capterra Conta Azul ("OCR salva muito tempo"). Fecha gap mercado P0. +3pp no score.

**Acceptance criteria:**
- [ ] Endpoint POST `/financeiro/unificado/ocr-boleto` recebe upload PDF/JPG/PNG max 5MB
- [ ] Service `BoletoOcrService::extract(file)` chama OpenAI Vision API (gpt-4o) com prompt estruturado (`linha_digitavel`, `valor`, `vencimento`, `beneficiario_nome`, `beneficiario_cnpj`, `pagador_nome`) — retorna JSON
- [ ] Fallback AWS Textract se OpenAI quota exceeded (config switch)
- [ ] Idempotência: hash SHA-256 do arquivo evita re-processar mesmo boleto
- [ ] Validação linha digitável (módulo 11 dígito verificador) — rejeita lixo OCR
- [ ] UI Sheet "Importar boleto" em Unificado/Novo: upload + spinner OCR + preview campos extraídos editáveis + 1-click confirma cria titulo_pagar
- [ ] Custo audit: log `ai_usage_log` com cost_usd por chamada (ADR Jana token tracking)
- [ ] Pest cobre: extract sucesso fixture, falha rate-limit OpenAI fallback Textract, linha digitável inválida 422, business_id scope
- [ ] biz=1

**Refs:** CAPTERRA-INVENTARIO bucket ❌ A1 (KILLER) · COMPARATIVO_CONCORRENCIA matriz "OCR boleto upload P0"
**LOC estimado:** ~300

### US-FIN-030 · Aging buckets <30/30-60/60-90/90+ no header Unificado + filtro

> owner: — · sprint: Onda 24 · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

Hoje só `FinPillFrescor` per-linha (6 estados visuais por título). Falta agregação header KPI somando R$ por bucket + filtro pra clicar e drill-down.

Charter v5 Non-Goal F1 — mover pra Goal pós-Ondas 12-21 (Eliana precisa pra cobrança priorizada).

**Acceptance criteria:**
- [ ] Service `AgingBucketService::compute(business_id, filters)` retorna `{'<30': R$, '30-60': R$, '60-90': R$, '90-180': R$, '>180': R$}`
- [ ] KPI cards no header Unificado mostram 4 buckets (a receber atrasado) com R$ + count badge
- [ ] Click no card filtra tabela por bucket
- [ ] UnificadoController.parseFilters aceita `aging_bucket` enum
- [ ] DRE Aging report endpoint `GET /relatorios/aging-detalhado` por contraparte (CSV export)
- [ ] Pest cobre buckets corretos com fixtures (5 títulos em datas controladas), business_id scope
- [ ] biz=1

**Refs:** CAPTERRA-INVENTARIO bucket 🟡 P4 · SPEC.md US-FIN-012 (já listada) · COMPARATIVO matriz "Aging visual P1"
**LOC estimado:** ~50

### US-FIN-031 · Bulk actions tabela Unificado — checkbox + select-all + ações em lote

> owner: — · sprint: Onda 25 · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

Crítico fechamento mês com 200+ títulos. Hoje precisa 1-clique por linha = 200 cliques. Conta Azul/Tiny/Bling têm bulk.

**Acceptance criteria:**
- [ ] Coluna checkbox primeira na tabela Unificado + checkbox header select-all (página atual ou TODOS via filtro)
- [ ] Footer sticky aparece com N selecionados quando >0
- [ ] Ações disponíveis: Baixar selecionadas / Alterar categoria em lote / Alterar plano de contas em lote / Cancelar lote / Exportar CSV seleção
- [ ] Modal confirma ação destructive (cancelar) com texto "Você está cancelando N títulos totalizando R$ X"
- [ ] Endpoint `POST /unificado/bulk` recebe `{action, ids: [], payload: {}}` valida ownership business_id de TODOS ids
- [ ] Limite 500 por chamada (UX warning se >500 — sugere refinar filtro)
- [ ] Audit trail registra ação bulk com user + ids + timestamp
- [ ] Pest cobre cross-tenant (200 do biz=1 + 1 do biz=99 → bulk rejeita 422), todas 5 ações
- [ ] biz=1

**Refs:** CAPTERRA-INVENTARIO bucket ❌ A3 · COMPARATIVO matriz "Bulk actions P1"
**LOC estimado:** ~200

### US-FIN-032 · Inter API webhook PIX recebido → titulo auto-pago (auto-conciliação)

> owner: — · sprint: Onda 26 · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

Fecha ciclo PaymentGateway Onda 5 dogfooding. InterDriver mTLS cert upload funciona, falta receber webhook quando cliente paga PIX → matar título automático.

**Acceptance criteria:**
- [ ] Endpoint POST `/webhooks/inter/{credentialId}` autentica via HMAC signature header `x-inter-signature` + Vaultwarden secret
- [ ] Job `ProcessarWebhookPixInterJob` (queue) recebe payload `{txid, valor, dataPagamento, pagador.cpfCnpj}` → busca PixCobranca por txid → marca status `pago` + cria `fin_titulo_baixa` automática
- [ ] FSM transition titulo `aberto→quitado` via ExecuteStageActionService (NÃO update direto — ADR 0143 trait GuardsFsmTransitions)
- [ ] Idempotência: txid unique constraint
- [ ] Notifica WhatsApp/email cliente "Recebemos seu pagamento R$ X" (opt-in via Contact.canReceive*Notification)
- [ ] Pest cobre: signature inválida 401, txid duplicado idempotente, business_id correto pelo credentialId
- [ ] Health-check entry `webhook_inter_lag_24h` (alerta se >5min latência)
- [ ] biz=1

**Refs:** CAPTERRA-INVENTARIO bucket 🟡 P7 · ADR 0143 FSM Pipeline · ADR 0170 PaymentGateway
**LOC estimado:** ~80

### US-FIN-033 · Notificações vencimento próximo (e-mail + WhatsApp X dias antes)

> owner: — · sprint: Onda 27 · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —

Schedule diário avisa cliente X dias antes vencimento (cobrar antes de atrasar) + lembra Eliana de pagar boletos PJ próprios.

**Acceptance criteria:**
- [ ] Comando artisan `financeiro:notificar-vencimentos` em Console/Kernel daily 08:00 BRT
- [ ] Config per-business `fin_settings.dias_aviso_antecipado` (default 3, configurável 1-30)
- [ ] Envia email pra Contact.email (LGPD opt-in via canReceiveEmailNotification — NULL=permite back-compat, FALSE=skip)
- [ ] Envia WhatsApp via Modules/Whatsapp Meta Cloud API direto (ADR 0096 emenda 4) com template "boleto-vencimento-proximo" aprovado WABA
- [ ] Template variáveis: {cliente_nome}, {valor}, {vencimento_dia}, {linha_digitavel_opcional}
- [ ] Idempotência: tabela `fin_notificacoes_enviadas` UNIQUE (titulo_id, tipo, data_envio) evita spam dupla
- [ ] UI config em /financeiro (gear icon) — toggles per-tipo (email/WA) + dias antecipados
- [ ] Pest cobre: notifica títulos venc=hoje+3, NÃO notifica quitados, respect opt-out, business_id scope
- [ ] biz=1

**Refs:** CAPTERRA-INVENTARIO bucket ❌ A4 · COMPARATIVO matriz "Notificações P1"
**LOC estimado:** ~100

### US-FIN-034 · Importação massiva CSV/Excel — mapping wizard + dry-run + commit

> owner: — · sprint: Onda 28 · priority: p2 · estimate: 5h · status: todo · type: story
> blocked_by: —

Onboarding novo cliente: migra 500+ títulos da planilha legacy num upload. Hoje só digitação manual = friction adoption mortal pra clientes médios.

**Acceptance criteria:**
- [ ] Sheet "Importar CSV" no Unificado/Novo: upload .csv/.xlsx max 5MB
- [ ] Parser usa `maatwebsite/excel` (já no composer? checar) ou `league/csv` lightweight
- [ ] Wizard 3 steps: Upload → Mapping (preview 5 linhas, dropdown coluna CSV → campo título: cliente_nome, valor, vencimento, descricao, categoria_id opcional, plano_conta_id opcional) → Dry-run (mostra N válidos / N erros com linha+motivo) → Commit
- [ ] Erros não bloqueiam: import parcial commit válidos + retorna CSV erros download
- [ ] Idempotência: hash do arquivo + business_id evita re-import acidental (warning "Arquivo já importado em DD/MM HH:MM, deseja substituir?")
- [ ] Limite 10k linhas/upload (acima fragmenta)
- [ ] Pest cobre: CSV válido 100 linhas, header mapping, valor negativo rejeita, business_id scope, idempotência
- [ ] biz=1

**Refs:** CAPTERRA-INVENTARIO bucket ❌ A5 · COMPARATIVO matriz "Importação massiva P2"
**LOC estimado:** ~150

### US-FIN-035 · Repetir lançamento próximo mês + Combobox autocomplete contraparte

> owner: — · sprint: Onda 29 · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —

2 UX boosters pareados — recorrentes manuais e autocomplete contraparte.

**Acceptance criteria — Repetir:**
- [ ] Botão "Duplicar para próximo mês" no drawer Unificado (titulo existente) + dropdown "próximas 12 ocorrências mensais"
- [ ] Cria N títulos com vencimento + 1 mês, 2 meses... preserva valor/categoria/plano_conta/descricao
- [ ] Marker `titulo_pai_id` linka pra original
- [ ] Permite editar/cancelar individual sem afetar outros (não-FSM, soft delete cascade=false)
- [ ] Pest cobre duplicação 3x, edit isolado, business_id

**Acceptance criteria — Combobox:**
- [ ] Refatorar input "Cliente/Contraparte" em Novo/Edit Sheet pra usar `<Combobox>` shadcn com search async (debounce 250ms)
- [ ] Endpoint `GET /unificado/contrapartes/search?q=Ban` retorna top 20 matches por similaridade (Contact + ContaBancaria + Vendor) com type icon
- [ ] Suporta "criar novo" inline se nenhum match
- [ ] Charter v5 Non-Goal F1 → mover Goal
- [ ] Pest cobre fuzzy match, business_id scope, criar inline

**Refs:** CAPTERRA-INVENTARIO bucket ❌ A6+A7 · COMPARATIVO matriz "Repetir/Combobox P2"
**LOC estimado:** ~120 (60 + 60)

### US-FIN-036 · PWA básico Financeiro — manifest + service worker + offline cache + install prompt

> owner: — · sprint: Onda 30 · priority: p2 · estimate: 5h · status: todo · type: story
> blocked_by: —

Mata gap mobile (score 4/10 vs Conta Azul 9/10) sem custo de app nativo. Wagner/Eliana fora do escritório acessa do celular via PWA install.

**Acceptance criteria:**
- [ ] `public/manifest.webmanifest` com name/icons (192+512+maskable)/theme_color #006/start_url /financeiro
- [ ] Service worker `public/sw-financeiro.js` registra GET cache pra rotas read (dashboard, unificado index defer skeletons funcionam offline)
- [ ] Strategy: stale-while-revalidate pra index, network-first pra detail (não cachear baixas/edits)
- [ ] Install prompt UI: banner no /financeiro top "Instalar app no celular" (suprime se já standalone)
- [ ] Funciona iOS (Add to Home Screen) + Android (Chrome install)
- [ ] Sem dados sensíveis em cache (sanitizar antes do put, ADR LGPD)
- [ ] Lighthouse PWA score ≥90
- [ ] Smoke real prod: instalar no celular Larissa biz=4 + abrir offline / online toggle
- [ ] Pest cobre manifest válido + sw registration

**Refs:** CAPTERRA-INVENTARIO bucket ❌ A2 · COMPARATIVO matriz "App mobile P2-P3"
**LOC estimado:** ~150

### US-FIN-037 · Customer service network — Portal Advisor Contadores parceiros (referral + acesso compartilhado)

> owner: — · sprint: Onda 31 · priority: p2 · estimate: 14h · status: todo · type: story
> blocked_by: —

Diferencial Conta Azul KILLER que ataca direto seu moat de mercado: network de contadores parceiros que recomendam o oimpresso ao cliente PJ. Wagner aprovou DC2 em 2026-05-19 — "pode fazer".

**Modelo conceitual:**
- Contador cadastra-se grátis no portal `/advisors/login` (separado do biz)
- Cliente PJ adiciona seu contador no Financeiro → grant read-only access a /relatorios + /unificado (zero credenciais compartilhadas)
- Contador vê N clientes seus num dashboard único (multi-business view limitado)
- oimpresso paga 15% recurring commission ao contador por cliente PJ ativo referenciado (campanha lançamento)
- Sistema referral code (contador.cnpj) que cliente cola no checkout signup

**Acceptance criteria — Fase 1 (MVP, este card):**
- [ ] Tabela `advisors` (cnpj_contador, nome, email, telefone, referral_code, criado_at, ativo)
- [ ] Tabela `advisor_business_access` (advisor_id, business_id, granted_at, revoked_at, scope JSON readonly_view)
- [ ] Tela `/financeiro/configuracoes/contador` no biz: admin grant acesso ao seu contador via email/cnpj
- [ ] Portal advisor `/advisor` separado com login isolado (não-business user) — dashboard com cards "Meus clientes" linkados a /relatorios?advisor_view=1&business_id=X
- [ ] Middleware `AdvisorViewScope` força readonly (POST/PUT/DELETE 403)
- [ ] Pest cobre: advisor login isolado, grant/revoke acesso, readonly enforce, business_id correto por grant
- [ ] LGPD: advisor consente termos antes de ver dados; biz pode revogar a qualquer momento
- [ ] biz=1 cross-tenant + isolamento entre advisors (advisor A não vê clientes do advisor B)

**Fase 2 (futuro, US separada):**
- Sistema referral code + comissão 15% recurring tracking
- Marketplace de contadores certificados (pesquisa por cidade/especialidade)
- White-label leve (contador adiciona logo no portal advisor)

**Refs:** Wagner aprovou DC2 em sessão 2026-05-19 · CAPTERRA-INVENTARIO bucket ❌ Customer service network · COMPARATIVO_CONCORRENCIA Pros líder (Conta Azul) #1 "Network de contadores é diferencial"
**LOC estimado Fase 1:** ~400 · estratégico, alto impacto comercial

---

## Follow-ups pos-sessao 2026-05-20 (Larissa biz=4 bridge recovery)

### US-FIN-038 · UI label 'Conta indefinida' + CTA 'vincular conta' nas linhas com conta_bancaria_id NULL

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

Pos-ADR 0175 (Observer permite baixa sem fin_contas_bancarias), drawer/list Financeiro/ContasReceber + Financeiro/Unificado + Financeiro/Cobranca devem mostrar pill cinza 'Conta indefinida' nas linhas onde `fin_titulo_baixas.conta_bancaria_id IS NULL`, com CTA pra modal de cadastro fin_contas_bancarias.

Acceptance:
- Tooltip: 'Pagamento registrado sem vinculação bancária. Cadastre conta pra organizar caixa.'
- Pill cinza-amarelado (warning leve, não vermelho de erro)
- Click no CTA abre `/financeiro/contas-bancarias/create` em modal ou nova rota
- Pos-cadastro, oferecer artisan-equivalente UI: 'Vincular automaticamente N baixas órfãs?' (executa `financeiro:vincular-baixas-sem-conta {biz} {conta}` via fila/job)
- Pest GUARD: titulo com baixa conta_bancaria_id NULL renderiza pill 'indefinida'
- Smoke browser Brave biz=4 Larissa apos PR mergeada: deve aparecer pill nos 15.412 baixas backfill SQL ja existentes + nos NOVAS pos-pagamento

Refs ADR 0175, sessao 2026-05-20-financeiro-bridge-larissa-backfill-recovery.md.

### US-FIN-039 · Artisan command financeiro:vincular-baixas-sem-conta - reconciliacao posterior

> owner: — · priority: p2 · estimate: 1.5h · status: todo · type: story
> blocked_by: —

Comando Laravel que recebe `{biz_id}` + `{conta_bancaria_id}` e faz UPDATE em massa de `fin_titulo_baixas` + `fin_caixa_movimentos` onde `conta_bancaria_id IS NULL AND business_id = ?` setando o `conta_bancaria_id` fornecido.

Pareado com ADR 0175 - quando cliente cadastrar `fin_contas_bancarias` apos ja ter baixas com NULL, executa o vinculo retroativo.

Uso `php artisan financeiro:vincular-baixas-sem-conta 4 20`. Output esperado `15.412 fin_titulo_baixas + 15.412 fin_caixa_movimentos atualizados (biz=4 -> conta_bancaria_id=20)`.

Acceptance:
- Signature `financeiro:vincular-baixas-sem-conta {biz : Business ID} {conta_id : fin_contas_bancarias.id}`
- Pre-check: confirma `conta_id` pertence ao `biz_id` (Tier 0 ADR 0093)
- UPDATE idempotente: só atualiza onde conta_bancaria_id IS NULL
- Output: count antes + depois
- Pest GUARD: cria conta, gera 3 baixas sem conta, roda comando, asserta 3 baixas vinculadas
- Tag `metadata.reconciled_at` em cada row atualizado (auditoria)

Refs ADR 0175, RUNBOOK bridge-sells-titulos-backfill.md.

### US-FIN-040 · Artisan command financeiro:health-check cron daily 06:00 BRT - detecta gaps bridge

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —

Health-check que roda 5 queries canonicas detectando os 3 gaps documentados em RUNBOOK-bridge-sells-titulos-backfill.md cross-business. Alerta Slack/log quando gap > threshold.

Signature `financeiro:health-check [--biz=*] [--alert] [--json]`.

Gaps detectados:
- Gap A: business com `fin_titulo_baixas` mas zero `fin_contas_bancarias` (no-op residual pre-ADR 0175)
- Gap B: `transactions(type IN (sell,purchase), status=final)` sem `fin_titulos` correspondente (Observer nao retroativo)
- Gap C: `fin_titulos.cliente_descricao IS NULL` em > 5% titulos (campo desnormalizado nao populado)

Acceptance:
- Output formato tabela ou JSON com 5 metricas por biz
- Exit code 0 = zero gaps, 1 = pelo menos 1 gap encontrado (CI/cron usa)
- Flag `--alert` envia Slack webhook quando gap > 0 (env `SLACK_FIN_HEALTH_WEBHOOK_URL`)
- Schedule daily 06:00 BRT (espelha pattern `sells:smoke-daily`)
- Pest GUARD: cria biz com gap, roda command, asserta exit 1 + Slack message
- Tier 0 ADR 0093 respeitado em todas queries

Refs ADR 0175, agent financeiro-bridge-auditor.md.

### US-FIN-041 · Onda 6 Accounting DROP TABLE - 6 vazias + ARCHIVE 2 seed + DELETE permissions

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —

Onda 6 final da deprecacao Accounting (ADR 0172) - executar apos canary 30d (destrava 2026-06-19).

Pre-cond:
- 30d desde merge PR #1258 (drop Modules/Accounting) sem incidente
- Smoke prod confirma zero log error apontando `/accounting/*`
- Wagner aprovacao final

Acao SQL (ARCHIVE mysqldump LGPD 5y primeiro, depois DROP TABLE 8 + DELETE permissions accounting.* + UPDATE packages.custom_permissions JSON_REMOVE accounting_module).

Acceptance:
- 8 tabelas DROPed (`chart_of_accounts`, `journal_entries`, `budgets`, `transfers`, `payment_details`, `branch_capital`, `account_subtypes`, `account_detail_types`)
- 13 permissions accounting.* removidas
- mysqldump archive em governance/archive/accounting-YYYY-MM-DD.sql.gz (LGPD 5y)
- Pest GUARD existe: `Pest test schema accounting tables removidas`
- Smoke `gh pr view 1258 mergedAt + 30d >= today` validado

Refs ADR 0172/0174, DEPRECATION-PLAN.md Onda 6.

### US-FIN-042 · Backfill cliente_descricao biz=1 - 52 fin_titulos pre-Onda-Edit NULL

> owner: — · priority: p3 · estimate: 0.25h · status: todo · type: story
> blocked_by: —

Aplica o mesmo backfill `cliente_descricao` do biz=4 (sessao 2026-05-20) em biz=1 Wagner WR2. Auditoria daquela sessao apontou 0% NULL em biz=1, mas tem casos isolados se aparecer drift futuro.

UPDATE idempotente (mesma logica do Observer TituloAutoService linha 19 Onda Edit 2026-05-18) — JOIN contacts c ON c.id = ft.cliente_id, SET cliente_descricao = CONCAT(nome, ' . #', V/PC, '-', origem_id), WHERE business_id=1 AND cliente_descricao IS NULL.

Acceptance:
- Pre-check conta `count(*) where cliente_descricao IS NULL business_id=1` (esperado: 0 ou poucos)
- Pos-check 100% titulos biz=1 tem cliente_descricao populado
- Tier 0 ADR 0093: WHERE business_id=1 explicito
- Idempotente (NOT NULL guard)

Pode ser executado sob demanda quando Wagner pedir, OU agendado via `financeiro:health-check` apos detectar Gap C.

Refs RUNBOOK-bridge-sells-titulos-backfill.md fase 2d.
