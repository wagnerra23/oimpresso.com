# Plano Detalhado — Módulo Financeiro

> **Última atualização:** 2026-04-25 (sessão maratona — 6 PRs mergeados)
> **Status:** Onda 1 quase fechada — backend ✅ + 4 telas operacionais ✅; falta categorias/plano-contas + integration test
> **Stack confirmada:** Laravel 13.6 + PHP 8.4 + Inertia v3 + React 19 + Tailwind 4 + lib eduardokum/laravel-boleto v0.11.1 (fork local)

---

## 1. Resumo executivo

| Camada | Estado | Detalhe |
|---|---|---|
| **Backend (Onda 1)** | ✅ 100% | Models, migrations, services, observer, strategy CNAB com 21 bancos |
| **UI (Onda 1)** | 🟡 50% | 2 telas operacionais + 1 dashboard pendente |
| **Onda 2 (CNAB real)** | ❌ 0% | Mock-only |
| **Onda 3 (Gateway)** | ❌ 0% | Plano em ADR ARQ-0003 |
| **Onda 4 (Relatórios)** | ❌ 0% | DRE/Aging/Razão pendentes |

**Código atual:** 6 controllers, 7 models, 2 services, 1 observer, 1 strategy, 7 migrations, 21 bancos suportados, 32+ testes Pest verde.

---

## 2. Estado atual em detalhe

### 2.1 Backend (✅ pronto e mergeado)

```
Modules/Financeiro/
├── Database/Migrations/  (7 tabelas)
│   ├── fin_planos_conta
│   ├── fin_categorias
│   ├── fin_contas_bancarias    ← complemento 1-1 com accounts core
│   ├── fin_titulos             ← direito/obrigação
│   ├── fin_titulo_baixas       ← pagamentos parciais
│   ├── fin_caixa_movimentos    ← entradas/saídas
│   └── fin_boleto_remessas     ← boletos emitidos
│
├── Models/
│   ├── Titulo, TituloBaixa
│   ├── ContaBancaria (complemento 1-1 accounts)
│   ├── BoletoRemessa
│   ├── CaixaMovimento, Categoria, PlanoConta
│
├── Contracts/BoletoStrategy.php  (interface)
├── Strategies/CnabDirectStrategy.php  (impl mock + 21 bancos via lib)
│
├── Services/
│   ├── TituloService          (orquestrador emitir/cancelar/status)
│   └── TituloAutoService      (sincroniza Transaction → Titulo)
│
├── Observers/
│   └── TransactionObserver    (venda → titulo auto)
│
├── Http/Controllers/
│   ├── ContaBancariaController     ← tela #6
│   ├── ContaReceberController      ← tela #7
│   ├── DashboardController         (stub — falta implementar)
│   └── (DataController, FinanceiroController, InstallController) UPos plumbing
```

### 2.2 UI (🟡 parcial)

| Tela | Rota | Status |
|---|---|---|
| Dashboard unificado (4 KPIs + tabela) | `/financeiro` | ❌ stub controller, sem React |
| Cadastro de conta bancária + boleto | `/financeiro/contas-bancarias` | ✅ PR #6 mergeado |
| Lista contas a receber + emitir | `/financeiro/contas-receber` | 🟡 PR #7 aberto |
| Lista contas a pagar | `/financeiro/contas-pagar` | redirect 301 → dashboard |
| Caixa (fluxo realizado/projetado) | `/financeiro/caixa` | ❌ |
| Conta bancária — extrato individual | `/financeiro/contas-bancarias/:id/extrato` | ❌ |
| Conciliação OFX (3 colunas) | `/financeiro/conciliacao` | ❌ |
| Boletos emitidos | `/financeiro/boletos` | ❌ |
| Plano de contas | `/financeiro/plano-contas` | ❌ |
| Categorias | `/financeiro/categorias` | ❌ |
| Relatórios (DRE/Aging/Razão) | `/financeiro/relatorios/*` | ❌ |

### 2.3 Tests (32+ verde)

- `CnabDirectStrategyContractTest` — 22 passed (21 bancos + erro)
- `TituloServiceTest` — 3 passed (delega/cancelar/status)
- `TransactionObserverTest` — 4 passed (created/updated wasChanged/deleted)
- `UpsertContaBancariaRequestTest` — 3 passed (rules validação)

**Faltam tests integration end-to-end (com DB), só passam local com Herd.**

---

## 3. Onda 1 — UI restante (próximas 1-2 sessões)

### 3.1 Tela `/financeiro` Dashboard unificado [P0 — pré-requisito do resto]

**Spec:** ADR UI-0002 (já existe).

**Backend:**
- `DashboardController::index` — calcula 4 KPIs + lista paginada de títulos
- Endpoint shape exato no ADR (`kpis: {receber_aberto, pagar_aberto, recebido_mes, pago_mes}`)
- Cache 5min com invalidation por evento (`TituloBaixado/Criado/Cancelado`)

**Frontend:**
- `Pages/Financeiro/Dashboard/Index.tsx`
- 4 cards KPI clicáveis (filtra tabela ao clicar)
- Filtros: tipo, status, período, cliente, aging
- Tabela única com badges de tipo (📥/📤) e status (●/◐/✓)
- Drawer/Sheet pra detalhe do título
- URL state via querystring (bookmarkable)

**Test:**
- `DashboardKpiTest` — 20 títulos misturados → KPIs corretos
- `DashboardFilterTest` — query string filtra por tipo/status/aging
- `DashboardIsolationTest` — biz B não vê dados de biz A (R-FIN-001)

**Esforço:** 1 sessão (~3-4h).

### 3.2 Tela `/financeiro/contas-pagar` [P1]

Mesmo pattern de `contas-receber` mas com:
- Filtro `tipo='pagar'`
- Botão "Pagar" (em vez de "Emitir boleto") — registra `TituloBaixa`
- Sheet pra escolher conta bancária + meio de pagamento (dinheiro/PIX/transferência)

**Esforço:** ~2h (template já existe).

### 3.3 Tela `/financeiro/boletos` [P1]

Lista `fin_boleto_remessas` com:
- Filtros: status (gerado_mock/pago/vencido/cancelado), conta bancária, período
- Action "Baixar PDF" (chama `eduardokum/laravel-boleto` render)
- Action "Cancelar" (TituloService::cancelarBoleto)
- Badge linha digitável + nosso número

**Esforço:** ~2h.

### 3.4 Tela `/financeiro/categorias` + `/plano-contas` [P2]

CRUD básico de:
- `fin_categorias` (tags livres)
- `fin_planos_conta` (estrutura DRE) — seedar 47 contas RF Brasil

**Esforço:** ~3h (2 telas + seeder).

### 3.5 Test integration end-to-end venda→título→boleto [P0]

- Cria venda no UPos via factory (Transaction sell + due)
- Observer dispara → cria fin_titulos
- Configura conta bancária com boleto Sicoob
- Chama TituloService::emitirBoleto
- Valida BoletoRemessa persistida com linha digitável válida

**Esforço:** ~2h.

**Total Onda 1 restante:** ~12h dev sênior (3-4 sessões focadas).

---

## 4. Onda 2 — sair do mock CNAB (2-3 semanas)

### 4.1 Geração CNAB 240 remessa [P0]

- `BoletoRemessaService::gerarArquivoCnab240($remessas)` — usa `eduardokum/laravel-boleto` Remessa
- Persiste arquivo em `fin_boleto_arquivos_cnab` (table no ADR)
- Estado `gerado_mock` → `gerado` quando arquivo gerado

### 4.2 Envio remessa ao banco [P0 Sicoob]

- Sicoob primeiro (cliente ROTA LIVRE)
- SFTP upload OU API REST conforme banco
- Estado `gerado` → `enviado` quando upload OK
- Job `EnviarRemessaCnabJob` em queue `financeiro`

### 4.3 Parser CNAB 240 retorno [P0]

- Job `ProcessarRetornoCnabJob` lê arquivo retorno
- Atualiza `fin_boleto_remessas.status` baseado em ocorrências
- Cria `TituloBaixa` quando boleto pago
- Estado `enviado` → `registrado` → `pago`

### 4.4 Webhook PIX (se Sicoob suportar) [P1]

Algumas integrações Sicoob têm webhook PIX para notificação imediata.

**Esforço Onda 2:** ~80h (2-3 semanas dev sênior).

---

## 5. Onda 3 — Gateway moderno (Asaas/Iugu) (2-3 semanas)

Cobre clientes que querem zero homologação CNAB.

### 5.1 GatewayStrategy [P0]

- HTTP client Laravel + retries
- Asaas primeiro (mais usado em PME BR)
- Iugu/Pagar.me em onda futura

### 5.2 Webhook handler com idempotência [P0]

- Tabela `pg_webhook_events` (compartilhada com RecurringBilling — TECH-0001)
- Listener `AsaasWebhookListener` mapeia status → `BoletoStatus` interno

### 5.3 PIX nativo [P1]

- QR estático: gera 1× e reusa
- QR dinâmico: gera por título com TXID único
- Webhook PIX via Asaas

### 5.4 HybridStrategy [P2]

Roteamento por regra (cliente VIP → CNAB direto, resto → Gateway). Pattern já no ADR ARQ-0003.

**Esforço Onda 3:** ~80h.

---

## 6. Onda 4 — Relatórios + onboarding (2 semanas)

### 6.1 DRE [P0]

- Receita bruta: soma `fin_titulos` quitados tipo='receber' por competência
- Deduções: impostos NF-e (depende NfeBrasil)
- Custos: tipo='pagar' com plano_conta_id em "custos diretos"
- Despesas: tipo='pagar' em "despesas operacionais"
- Resultado: receita - deduções - custos - despesas

### 6.2 Aging [P0]

Buckets `<30 / 30-60 / 60-90 / 90-180 / >180` por tipo + status.

### 6.3 Razão por conta [P1]

Histórico de movimentos por `conta_bancaria` ou `plano_conta`.

### 6.4 Conciliação OFX (3 colunas — UI-0001) [P1]

Importa extrato OFX, faz match com `fin_titulos` ou `fin_caixa_movimentos`, sugere lançamentos.

### 6.5 Plano de contas BR pré-seedado [P0]

47 contas Receita Federal padrão.

### 6.6 Permissões Spatie + menu UPos [P0]

- 12 permissões `financeiro.{area}.{action}`
- Menu admin via `modifyAdminMenu` hook
- Pacote Free/Pro/Enterprise no Superadmin

**Esforço Onda 4:** ~80h.

---

## 7. Pendências transversais

### 7.1 Setup CI completo [P0]

- MySQL service no GitHub Actions
- `migrate --force` + `module:migrate` antes do Pest
- Roda toda suite `tests/Feature/Modules/Financeiro/`

### 7.2 Audit `useForm` Inertia v3 [P1]

20+ páginas precisam revisar timing do reset (caso a caso quando tocar).

### 7.3 Permissões granulares [P0]

Spatie permissions formato `financeiro.{area}.{action}` registradas no provider.

### 7.4 Logs de auditoria [P1]

`spatie/laravel-activitylog` em mutações financeiras (já configurado no Model).

---

## 8. Ordem de execução recomendada

### Sessão N (1-2h)
1. Smoke browser das telas mergeadas (#6 e #7)
2. Implementar Dashboard unificado (`/financeiro`) — entrega valor imediato

### Sessão N+1 (1-2h)
3. Tela `/financeiro/contas-pagar`
4. Tela `/financeiro/boletos`
5. Test integration venda→título

### Sessão N+2 (2h)
6. Plano de contas + categorias (CRUD + seeder)
7. Setup CI completo (MySQL service)

### Marco — Onda 1 fechada (~3 sessões)

ROTA LIVRE consegue:
- Ver dashboard com 4 KPIs
- Cadastrar conta bancária + boleto
- Ver títulos abertos auto-gerados de vendas
- Emitir boleto offline (mock CNAB)
- Cancelar boleto / títulos
- Pagar conta a pagar (registra baixa)

### Sessões seguintes
- Onda 2 (CNAB real Sicoob — Larissa começa a usar em produção)
- Onda 3 (Asaas — abre mercado de PME bancarizada)
- Onda 4 (DRE — diferencia da Conta Azul/Tiny/Bling)

---

## 9. Riscos conhecidos

| Risco | Mitigação |
|---|---|
| CNAB Sicoob homologação demora 2-4 semanas | Mock-first (já feito); produção espera Larissa fazer convênio |
| Lib eduardokum não suporta Cora | `GatewayStrategy` cobre via Asaas |
| `TransactionObserver` cria título errado em venda complexa (descontos, devoluções) | Tests integration com cenários extremos antes de ligar produção |
| Performance dashboard com 5k títulos | Cache 5min + paginação 25/100; meta p95 < 500ms |
| Multi-tenancy leak (biz A vê dados biz B) | Test obrigatório `IsolationTest` em cada controller |

---

## 10. Métricas de sucesso (post-launch)

- Larissa abre `/financeiro` → primeira ação útil em < 10s
- ROTA LIVRE não usa mais planilha externa em 30 dias
- Contador exporta DRE Q1 sem ligar pra Larissa
- Conta Azul churn: clientes ROTA LIVRE-like que pediram demo diminuem 30%

---

## Referências

- ADR ARQ-0001 a 0005 (Financeiro)
- ADR TECH-0001 a 0003 (Financeiro)
- ADR UI-0001 (conciliação) e UI-0002 (dashboard)
- ADR 0024 (padrão Inertia + React + UPos)
- [_Roadmap_Faturamento.md](../_Roadmap_Faturamento.md)
- [auto-memória cliente_rotalivre](../../cliente_rotalivre.md) — sensibilidades Larissa
