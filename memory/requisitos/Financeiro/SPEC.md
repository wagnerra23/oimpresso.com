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

## 2. User stories

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
│ R$ 12.450       R$ 8.230       R$ 45.300       R$ 28.100               │
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
Dado uma baixa de R$ 500 em 2026-04-24
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
