---
slug: 0183-caixa-fisico-bridge-financeiro-canon
number: 183
title: "Caixa físico (cash_registers) ↔ Financeiro (fin_titulos) — ponte canon multi-caixa com conta-mãe + metadata JSON"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: financeiro
quarter: 2026-Q2
tags: [arquitetura, financeiro, cash-register, multi-caixa, observer, multi-tenant-tier-0, ADR-0093-tier0, ADR-0094-constituicao]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0170-paymentgateway-extracao-camada-cobranca"
  - "0172-deprecar-modulo-accounting-fundir-financeiro"
pii: false
review_triggers:
  - "Larissa @ ROTA LIVRE biz=4 fechar caixa e fin_titulo NÃO ser gerado em ≤30s → investigar Observer (race / exception silenciada / queue stuck)"
  - "Conta-mãe `tipo_conta='caixa'` criada mais que 1 vez por business (firstOrCreate race) → adicionar UNIQUE constraint composta"
  - "Eliana (contábil) reclamar que breakdown cash/card/cheque sumiu do relatório → reincorporar drill-down via metadata JSON"
  - "Estornos em cash_register_transactions causarem desbalance de fin_titulo (lançamento original ≠ valor real após estorno) → refinar lógica de soma"
  - "Multi-business multi-location (Vargas, Martinho, Fixar etc) → revisitar regra detect location se primeiro transactions não bater"
  - "Caixa aberto noite vira dia seguinte (closed_at > created_at + 24h) → revalidar data do fin_titulo"
  - "Backfill manual de caixas antigos pré-Observer começar a gerar duplicatas (idempotência falhar) → checar índice cash_register_id"
---

# ADR 0183 — Caixa físico ↔ Financeiro: ponte canon multi-caixa

## Contexto

Wagner 2026-05-21 review smoke prod de `/financeiro/caixa` (Modules/Financeiro Fase 6 Soft wrapper) revelou 2 bugs SQL (`cr.location_id` + `parent_id` inexistentes — corrigidos em PRs #1373/#1374) E levantou questão arquitetural mais profunda:

*"vai ter que estar conectado com financeiro? vai transferir no final do dia? como isso tem que funcionar?"*
*"tem pessoas que tem vários caixas, humm e como vai aparecer no Financeiro se conta? resolva isso."*

Estado atual:
- `cash_registers` (core UltimatePOS, migration 2018) registra **caixa físico do operador** — lifecycle abrir/fechar turno com totais cash/card/cheque/closing_amount
- `fin_titulos` (Modules/Financeiro canon) registra **títulos financeiros** — a pagar/a receber com conta bancária + plano de contas + conciliação OFX
- **NÃO há ponte**: quando Larissa fecha caixa, ZERO lançamento aparece em `/financeiro/fluxo` ou `/financeiro/contas-receber`. Eliana (contábil) não consegue conciliar caixa físico com extrato banco

Cenário real PME suporta **multi-caixa simultâneo**:
- Multi-operador (Larissa + funcionário João abrem caixas paralelos)
- Multi-localização (Matriz + Filial)
- Multi-turno (manhã/tarde sequencial)
- Multi-canal (POS balcão + POS móvel)

Cada `cash_registers` row = 1 caixa físico. Pode ter 5+ simultâneos no mesmo dia.

## Decisão

**Adotar Opção C — Conta-mãe consolidada + metadata JSON por título** como ponte canon:

```
┌──────────────────────────────────────────────────────────────┐
│ CAMADA OPERACIONAL (caixa físico do dia, core UPOS)          │
│ cash_registers + cash_register_transactions                  │
│                                                              │
│ Lifecycle: open → trans cash/card/cheque → close (operator)  │
│ Owner: operador (user_id) + business (business_id)           │
└────────────────────────┬─────────────────────────────────────┘
                         │
                         │ Observer CashRegisterClosedFinanceiroListener
                         │ (event: CashRegister.updated status=close)
                         │ Idempotent: skip se já gerou fin_titulo
                         ▼
┌──────────────────────────────────────────────────────────────┐
│ CAMADA FINANCEIRA (canon Modules/Financeiro)                 │
│ fin_titulos (conta_id = "Caixa Loja" tipo_conta=caixa)       │
│                                                              │
│ 1 fin_titulo por fechamento de caixa, total agregado.        │
│ metadata JSON preserva breakdown:                            │
│   {                                                          │
│     user_id, user_name, location_id, location_name,          │
│     caixa_id, breakdown: {cash, card, cheque, other}         │
│   }                                                          │
│                                                              │
│ Aparece em:                                                  │
│  - /financeiro/fluxo (consolidado por conta-mãe)             │
│  - /financeiro/fluxo?group=operador OU location OU caixa     │
│  - /financeiro/conciliacao (match com depósito OFX)          │
│  - /financeiro/caixa (drill-down: caixa ↔ fin_titulo)        │
└──────────────────────────────────────────────────────────────┘
```

### 6 decisões consolidadas

| # | Dimensão | Decisão | Por quê |
|---|---|---|---|
| **D1** | Conta destino | **Opção C híbrida**: 1 conta-mãe `tipo_conta='caixa'` por business + metadata JSON em cada `fin_titulo` | Saldo único + breakdown total via filtros (operador/local/caixa). Funcionário sai/entra preserva histórico. |
| **D2** | Nome conta-mãe | **"Caixa Loja"** (auto-criada `firstOrCreate` no primeiro fechamento) | Termo PME-BR universal. Larissa entende. |
| **D3** | Granularidade | **1 fin_titulo total por fechamento** (sem split por método) | Simples pra contador. Detalhes cash/card/cheque ficam no `metadata.breakdown` JSON pra drill-down. |
| **D4** | Detecção de location | Pegar do **PRIMEIRO `transactions` linked** ao cash_register via `transactions.cash_register_id` | cash_registers não tem location_id. Fallback: `business_locations` default do business (se houver). |
| **D5** | Backfill antigos | **Só novos via Observer automático** + botão manual `/financeiro/caixa` "Lançar agora" pra caixas pré-Observer | LGPD/auditoria mais segura. Backfill em massa precisa ADR separada. |
| **D6** | Idempotência | `FinTitulo::where('cash_register_id', $cr->id)->exists()` antes de criar + UNIQUE constraint em `(business_id, cash_register_id)` no schema | Observer pode disparar 2x (race condition). UNIQUE garante zero duplicata mesmo em race. |

### Schema diff

```sql
-- 1. fin_contas_bancarias ganha tipo_conta
ALTER TABLE fin_contas_bancarias
  ADD COLUMN tipo_conta VARCHAR(50) NOT NULL DEFAULT 'banco' AFTER label;
-- valores: 'banco' | 'caixa' | 'gateway' | 'aplicacao'
-- Conta-mãe do caixa = tipo_conta='caixa'

-- 2. fin_titulos ganha link + metadata
ALTER TABLE fin_titulos
  ADD COLUMN cash_register_id INT UNSIGNED NULL AFTER conta_id,
  ADD COLUMN metadata JSON NULL AFTER descricao,
  ADD INDEX fin_titulos_cash_register_idx (cash_register_id),
  ADD UNIQUE INDEX fin_titulos_cash_register_uniq (business_id, cash_register_id);
-- UNIQUE garante 1 fin_titulo por caixa por business
```

### Observer canon (esqueleto)

```php
// app/Listeners/CashRegisterClosedFinanceiroListener.php
final class CashRegisterClosedFinanceiroListener
{
    public function __construct(
        private FinTituloFromCaixaService $service,
    ) {}

    public function handle(CashRegisterClosed $event): void
    {
        $cr = $event->cashRegister;

        // Pegadinha #1 — idempotência (Observer pode disparar 2x em race)
        if (FinTitulo::where('cash_register_id', $cr->id)->exists()) {
            return;
        }

        // Pegadinha #2 — business_id zerado (legacy data)
        if (! $cr->business_id) {
            report(new \LogicException("cash_register {$cr->id} sem business_id"));
            return;
        }

        // Pegadinha #3 — caixa fechado com R$ 0 (sem movimentação)
        $totals = $this->service->computeTotals($cr);
        if ($totals->total <= 0.001) {
            return; // skip silencioso — caixa vazio não vira título
        }

        $this->service->createFinTitulo($cr, $totals);
    }
}
```

## Justificativa

**Por que Opção C (híbrido) e não A (consolidada) ou B (1:N):**

- **A consolidada** perde rastreabilidade — Eliana não vê quem gerou o dinheiro
- **B 1:N** (1 conta por caixa físico) explode count em rotatividade alta + funcionário sai = conta órfã
- **C híbrida** une o melhor: saldo único contábil (1 conta = simplicidade) + metadata JSON (todas filtros UX)

**Por que metadata JSON e não tabela pivot:**

- Filtros frequentes (operador/local/caixa) = JSON path query MySQL 8 nativa
- Tabela pivot triplicaria joins em `/financeiro/fluxo` (já complex)
- Metadata é immutable (snapshot do momento do fechamento) — não muda com rename de funcionário

**Por que `cash_register_id` em fin_titulos:**

- Drill-down direto (clica no título → vai pro caixa)
- UNIQUE constraint previne duplicação em race
- FK NULL aceito (títulos manuais não vêm de caixa)

**Por que Observer + Event Bus e não polling:**

- Reatividade real-time (Larissa fecha caixa → vê título no Financeiro em <1s)
- Polling daily 06:30 BRT seria gap de 12h de auditoria
- Observer já é pattern canon do projeto (TransactionObserver, etc)

**Quando reabrir esta ADR:**

- Multi-currency (hoje BRL único) — `metadata.currency` futuro
- Caixa físico móvel sincronização offline (POS sem net) — buffer de fin_titulos pendentes
- Power-user pedir lançamento MULTI por método (cash/card/cheque cada um → 1 fin_titulo) — re-discutir D3
- Larissa pedir "fechar caixa parcial" (closing intermediário sem fechar) — schema cash_registers não suporta hoje

## Regras canon (uniformes pra futura implementação)

### R1 — Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- `business_id` propaga `cash_register → fin_titulo` SEM exceção
- `FinContaBancaria::firstOrCreate(['business_id' => ..., 'tipo_conta' => 'caixa'], ...)` scoped por tenant
- Observer só dispara em `CashRegister` do mesmo business (global scope Eloquent)
- Cross-business é proibido — sub-agent que tente acionar event de business=N pra criar título em business=M deve FALHAR (audit log + report exception)

### R2 — Idempotência forte

- Antes de criar: `FinTitulo::where('cash_register_id', $cr->id)->exists()` (check em PHP)
- Schema UNIQUE `(business_id, cash_register_id)` (failsafe em DB)
- Observer roda em queue assíncrona (jobs) → idempotência cobre retry

### R3 — Conta-mãe firstOrCreate

```php
FinContaBancaria::firstOrCreate(
    ['business_id' => $businessId, 'tipo_conta' => 'caixa'],
    [
        'label' => 'Caixa Loja',
        'is_default' => true,
        'saldo_inicial' => 0,
    ],
);
```

UNIQUE constraint `(business_id, tipo_conta)` no schema previne race (2 fechamentos simultâneos).

### R4 — Detecção de location (fallback chain)

1. `transactions.location_id` do PRIMEIRO `transactions` linked ao caixa via `transactions.cash_register_id`
2. Se não houver: `business_locations.default_id` do business
3. Se ainda null: `metadata.location_id = null` (Eliana revisa manualmente)

### R5 — Data do fin_titulo = `cash_register.closed_at`

- NÃO usar `created_at` (caixa aberto manhã, fechado tarde — `created_at` = manhã, errado)
- NÃO usar `NOW()` (delay de queue causaria drift)
- Sempre `closed_at` — momento contábil real

### R6 — Skip silencioso em casos especiais

| Caso | Ação |
|---|---|
| `closing_amount = 0` E `transactions = []` (caixa vazio) | Skip — não gera fin_titulo |
| `business_id = 0` (legacy data) | Skip + `report(LogicException)` pra log |
| `cash_register.status != 'close'` (Observer disparou em update sem fechar) | Skip |
| `FinTitulo::where(cash_register_id)->exists()` (já lançado) | Skip — idempotência |

### R7 — Permission gates

- Observer roda como **SYSTEM** (sem user authenticated) — sem `can()` check
- Tela `/financeiro/caixa` continua com `view_cash_register` gate
- Botão "Lançar agora" (backfill manual) requer `financeiro.lancamentos.create`
- Drill-down `/financeiro/fluxo` → clica título → ver caixa requer `view_cash_register`

### R8 — Format de `metadata.breakdown`

Sempre 5 keys (mesmo zero):
```json
{
  "user_id": 3,
  "user_name": "Larissa Cardoso",
  "location_id": 2,
  "location_name": "Matriz",
  "caixa_id": 42,
  "breakdown": {
    "cash":   1380.00,
    "card":   2500.00,
    "cheque":    0.00,
    "other":     0.00,
    "total":  3880.00
  }
}
```

UI sempre lê todas keys (zero é zero, não null).

## Pegadinhas Tier 0 (catalogadas — evitar repetir)

| # | Pegadinha | Causa | Mitigação |
|---|---|---|---|
| **P1** | Observer dispara 2x (race condition) | Eloquent `updated` event + queue retry | Idempotência via `FinTitulo::where(cash_register_id)->exists()` + UNIQUE constraint DB |
| **P2** | `cash_registers.business_id = 0` (legacy data) | UPOS importou caixas antes de Tier 0 estar ativo | Observer skip silencioso + `report(LogicException)` pra audit log |
| **P3** | `cash_register_transactions.parent_id` NÃO existe | Bug catalogado #1374 — schema 2018 não tem | Não usar `whereNull('parent_id')`. Estornos viram type=debit nova linha. |
| **P4** | `cash_registers.location_id` NÃO existe | Bug catalogado #1373 — schema 2018 não tem | Detectar via `transactions.location_id` linked OU fallback `business_locations.default` |
| **P5** | Caixa noturno (closed_at > created_at + 24h) | Operação 22h-02h vira dia seguinte | Data do fin_titulo = `closed_at` (R5), não `created_at` |
| **P6** | Caixa vazio (R$ 0 total) | Larissa abre e fecha sem vender (teste) | Skip silencioso (R6) — não polui Financeiro |
| **P7** | `user_id = NULL` (admin/system fechou caixa) | UPOS aceita sem user em casos especiais | `metadata.user_name = "Sistema"` fallback |
| **P8** | `firstOrCreate` race em multi-thread (2 fechamentos simultâneos primeira vez) | Sem UNIQUE constraint, cria 2 contas-mãe "Caixa Loja" | UNIQUE `(business_id, tipo_conta='caixa')` no schema |
| **P9** | Estorno via venda cancelada cria `cash_register_transactions` com type invertido | UPOS pattern (não usa parent_id) | Soma simples + nota "overcount minimal" — refinar via US futura |
| **P10** | Eliana edita `fin_titulo` gerado pelo Observer | Erro humano: muda valor, descrição | `fin_titulos` triggers `AuditLog` (append-only) — diff visível em `/financeiro/conciliacao` |
| **P11** | Caixa fechado HÁ MESES é re-aberto/re-fechado manual (correção contábil retroativa) | Cenário raro mas possível | Idempotência (P1) garante NÃO duplicar; se valor mudou, criar `fin_titulo_baixas` complementar (NOT update) |
| **P12** | Permission gate `view_cash_register` mas user tem `financeiro.access` (cross-perm) | Larissa tem ambos; funcionário pode ter só 1 | Observer roda SYSTEM (sem check); telas têm seus próprios gates (R7) |
| **P13** | Multi-business (Vargas, Martinho, Fixar) — sub-agent cria fin_titulo em business errado | Race entre sessões paralelas | Tier 0 global scope + assert `$cr->business_id === auth()->user()?->business_id` no Observer |
| **P14** | Inertia render falha quando `fin_titulo.metadata` retorna `null` em PHP | Frontend espera object, não null | `metadata` default `{}` (cast `array`) no Eloquent model |
| **P15** | Conta "Caixa Loja" deletada por engano por admin | UPOS permite delete livre | `tipo_conta='caixa'` flag → bloquear delete no FinContaBancariaController (validação) |

## Consequências

**Positivas:**

- **Larissa vê dinheiro no Financeiro** em ≤1s após fechar caixa (real-time)
- **Eliana concilia** caixa físico vs depósito bancário em `/financeiro/conciliacao`
- **Multi-caixa transparente**: 5 caixas simultâneos = 5 títulos + 1 conta-mãe (saldo único)
- **Auditoria forte**: `cash_register_id` FK + metadata JSON preserva histórico imutável
- **Reversibilidade**: deletar Observer + migration revert = volta ao estado anterior
- **Drill-down**: `/financeiro/fluxo` → clica → `/financeiro/caixa#42` → vê transactions completas
- **Backfill seguro**: botão manual `/financeiro/caixa` "Lançar agora" pra caixas antigos

**Negativas / Trade-offs:**

- **3 PRs sequenciais** (não paralelizáveis — B depende A, C depende B)
- **Observer adiciona latência** ~50-200ms no `close_register` POS (queue async mitiga)
- **`metadata` JSON** requer MySQL 5.7+ (oimpresso já tem 8.x)
- **Backfill manual** pode gerar pico de jobs se Larissa clicar "Lançar todos" em 200 caixas antigos (rate-limit via queue worker)
- **Eliana precisa aprender**: caixa físico não é mais "fim em si" — agora vira ENTRADA no Financeiro

**Riscos mitigados:**

- **Larissa decora fluxos** (feedback canon) — UI `/financeiro/caixa` mantém visual familiar, só adiciona seção "✅ Lançado no Financeiro"
- **Conta-mãe deletada** — `tipo_conta='caixa'` bloqueia delete (P15)
- **Race condition** — UNIQUE constraint + check exists (R2 + P1 + P8)

## Plano de execução (3 PRs sequenciais — ordem importa)

| PR | Conteúdo | Esforço | Bloqueia? |
|---|---|---|---|
| **A — Migration + Schema** | Adicionar `tipo_conta` em `fin_contas_bancarias` (com seed `Caixa Loja` por business) + `cash_register_id` + `metadata` em `fin_titulos` + UNIQUEs. Pest test da migration. | 1-2h | F0 (este ADR) |
| **B — Observer + Service** | `CashRegisterClosedFinanceiroListener` + `FinTituloFromCaixaService::computeTotals/createFinTitulo` + Event `CashRegisterClosed` + registrar em `EventServiceProvider`. Pest test 6 cenários (happy path + P1+P2+P3+P6+P7). | 3-4h | A |
| **C — Tela /financeiro/caixa refatorada** | Mostra status integração + breakdown JSON + botão "Lançar agora" pra backfill + link drill-down pro `fin_titulo` gerado. Pest browser MCP smoke biz=1 (Larissa fecha caixa → vê título imediato). | 2-3h | B |

**Total:** 6-9h dev sequencial (1 dev) OU 4-5h com Pest paralelizado.

## Validação POST-implementação (browser MCP — obrigatório)

Skill `pageheader-canon` Fase 5 protocol aplica. Adicional pra este caso:

1. **Smoke biz=1** — Larissa abre caixa POS → vende → fecha → vai pra `/financeiro/fluxo` → vê título "Fechamento caixa #X" em ≤2s
2. **Idempotência** — re-disparar event 2x → `fin_titulos.count()` deve ser 1 (não 2)
3. **Multi-caixa simultâneo** — Larissa + João fecham caixas paralelos → 2 títulos separados na mesma conta-mãe
4. **Metadata JSON** — query `fin_titulos.metadata->>'$.user_name' = 'Larissa'` retorna apenas títulos dela
5. **Backfill manual** — botão "Lançar agora" em caixa antigo → fin_titulo aparece + idempotência se clicar 2x

## Métricas de sucesso (loop fechado — [Constituição v2 princípio 4](0094-constituicao-v2-7-camadas-8-principios.md))

| Métrica | Baseline (hoje) | Meta pós-ADR |
|---|---|---|
| Caixas fechados que viram fin_titulo | 0% | 100% (Observer auto + manual backfill) |
| Tempo médio close_caixa → fin_titulo visível | ∞ (não acontece) | ≤2s (queue async) |
| Conciliação OFX precisão | ~70% (manual Eliana) | ≥95% (auto-match via cash_register_id) |
| Tickets Eliana "onde está dinheiro do caixa?" | medir 30d baseline | -80% em 60d |
| Duplicatas de fin_titulo por race | medir baseline | 0 (UNIQUE + idempotência) |

## Refs

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0170](0170-paymentgateway-extracao-camada-cobranca.md) — Camada cobrança extraída
- [ADR 0172](0172-deprecar-modulo-accounting-fundir-financeiro.md) — Accounting fundido em Financeiro
- PR #1373 + #1374 — Bugs SQL `cr.location_id` + `parent_id` (root cause que originou este ADR)
- Wagner reviews 2026-05-21 (smoke prod /financeiro/caixa + perguntas arquitetura multi-caixa)
- `Modules/Financeiro/Http/Controllers/CaixaController.php` (entry-point afetado)
- `app/Http/Controllers/CashRegisterController.php` (core UPOS — close_register handler que dispara Observer)
