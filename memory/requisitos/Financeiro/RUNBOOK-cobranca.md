---
slug: runbook-cobranca
title: "RUNBOOK — /financeiro/cobranca (Tela 1 PaymentGateway UI F3)"
type: runbook
authority: canonical
lifecycle: ativo
related_adrs: [0093, 0094, 0104, 0114, 0144, 0170]
related_us: [US-PG-F3-COBRANCA]
parent_module: Financeiro
sister_module: PaymentGateway
persona: Eliana[E] (financeiro escritório) + Larissa[Cliente Piloto]
session_date: '2026-05-19'
---

# RUNBOOK · `/financeiro/cobranca` — F3 PaymentGateway UI Tela 1

> Substitui `/financeiro/boletos`. Persona Eliana[E]. Origem: Cowork F1 + F1.5 (score 96/100) aprovado [W] 2026-05-19 (Chat Cowork live). Handoff canon: [`COWORK_HANDOFF.paymentgateway-ui.md`](../../../COWORK_HANDOFF.paymentgateway-ui.md).

---

## Mission (1 frase)

Eliana acompanha **toda a cobrança gerada (boleto · pix · pix automático · cartão) em uma view única** com funil 5 etapas + 4 KPIs (3 fixos + 1 contextual) + tabela rica chip composto gateway+tipo + drawer detalhe + wizard nova cobrança, pra responder em <30s "quem pagou hoje?" "quais vencem amanhã?" "tenho mandato PIX Automático ativo?".

## Escopo expandido vs Boletos

| Aspecto | /financeiro/boletos (legacy) | /financeiro/cobranca (F3) |
|---|---|---|
| Tipos | boleto apenas | boleto + pix_cob + pix_cobv + pix_recv + card |
| Gateways | Inter + C6 (via CNAB) | Inter + C6 + Asaas + BCB PIX Aut. + PesaPal (deprecated) |
| Origens | título (Conta a Receber) | sale · invoice · subscription_license |
| Funil | 5 etapas | 5 etapas + chip lateral "mandato cancelado" |
| KPIs | 3 fixos | 3 fixos + 1 contextual |
| Filtros | tabs + select conta | tabs + chips tipo + dropdowns + chips origem + busca |
| Atalhos | — | `/` `J/K/↓↑` `Enter` `Esc` `?` (KB-9.75) |
| AI panel | — | ✦ Resumir mês (estilo Vendas/Index PR #1064) |
| Cheat sheet | — | overlay `?` |

## Models reais

- `Modules\PaymentGateway\Models\Cobranca` (HasBusinessScope) — tabela `cobrancas` (Onda 2 ADR 0170)
- `Modules\PaymentGateway\Models\PaymentGatewayCredential` (HasBusinessScope) — credenciais drivers
- `Modules\Financeiro\Models\ContaBancaria` — conta destino FK
- `App\Account` (UPOS core) — pivot via ContaBancaria

## Backend canônico

### Controller
- `Modules\Financeiro\Http\Controllers\CobrancaController`
  - `__construct` middleware `auth` + `can:financeiro.dashboard.view`
  - `index(CobrancaQuery $query)` retorna `Inertia::render('Financeiro/Cobranca/Index', $shape)`
  - Inertia::defer em props pesadas (`cobrancas`, `kpis`, `funil`)
  - Shape via helper privado `shapeCobranca()` (T-AP-5)

### Service/Repository
- `Modules\PaymentGateway\Repositories\CobrancaQuery`
  - `paginar(int $bizId, array $filtros): LengthAwarePaginator`
  - filtros: status, tipo, gateway, account_id, origem_type, busca
  - eager load `credential.contaBancaria.account` pra evitar N+1
  - `whereNull('deleted_at')` (T-AP-11)

### Rota
- `Route::get('/cobranca', [CobrancaController::class, 'index'])->name('cobranca.index')` em `Modules/Financeiro/Routes/web.php`
- Redirect 301 `/financeiro/boletos → /financeiro/cobranca` (60d preservar bookmarks)

## Frontend canônico

### Arquivos
- `resources/js/Pages/Financeiro/Cobranca/Index.tsx` (root)
- `resources/js/Pages/Financeiro/Cobranca/Index.charter.md` (charter live)
- `resources/js/Pages/Financeiro/Cobranca/_lib/cobranca-shared.ts` (types + helpers + tokens DRIVERS/TIPOS/STATUS/ORIGENS)
- `resources/js/Pages/Financeiro/Cobranca/_components/atoms.tsx` (StatusBadge, GatewayTipoChip, OrigemChip, KpiCard, Btn)
- `resources/js/Pages/Financeiro/Cobranca/_components/FunnelStrip.tsx`
- `resources/js/Pages/Financeiro/Cobranca/_components/DrawerCobranca.tsx`
- `resources/js/Pages/Financeiro/Cobranca/_components/SheetNovaCobranca.tsx`
- `resources/js/Pages/Financeiro/Cobranca/_components/SheetRemessaRetorno.tsx`
- `resources/js/Pages/Financeiro/Cobranca/_components/CheatSheet.tsx`
- `resources/js/Pages/Financeiro/Cobranca/_components/AiResumoMes.tsx`
- `resources/css/cowork-payment-gateway-bundle.css` (port pg-styles.css 480 linhas; wrapper `.pg-shell-scope`)

### Layout
Layout via `<Component>.layout = page => <AppShellV2>{page}</AppShellV2>` wrappeado em `<div className="pg-shell-scope fin-cowork">` (preserva tokens shell).

## Tier 0 Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- `Cobranca::query()` filtrada via `HasBusinessScope` global scope (já no Model)
- `PaymentGatewayCredential::query()` idem
- Cross-tenant test biz=1 vs biz=99 (Pest GUARD #6)

## Pest GUARDs obrigatórios (8)

1. `renderiza Inertia component Financeiro/Cobranca/Index`
2. `expõe Props no shape esperado (cobrancas, kpis, funil, contas, gateways, filtros)`
3. `expõe 3 KPIs fixos (pago_mes, vencido, aberto) + KPI contextual condicional`
4. `expõe funil 5 etapas (aberto, lembrete, cobranca_ativa, vencido_5d, protesto)`
5. `filtra por status/tipo/gateway/account/origem via querystring`
6. `Tier 0 IRREVOGÁVEL: Cobranca respeita business_id global scope (biz=1 não vê biz=99)`
7. `redirect 301 /financeiro/boletos → /financeiro/cobranca`
8. `não dispara mutação em GET /cobranca (read-only puro)`

## Cutover

- Mantém `/financeiro/boletos` view canon redirecionando 60 dias
- Atualizar sidebar (`DataController::user_permissions`) → "Cobrança" no lugar de "Boletos"
- Comunicar Eliana (mesmo persona) — UI familiar mas expandida

## Cores semânticas

Mantém ADR 0094 Constituição v2 §5 SoC visual:
- emerald (paga/ok) · rose (vencida/down) · amber (alerta) · sky (pending) · violet (PIX Aut./mandato) · stone (default)
- Bundle CSS `cowork-payment-gateway-bundle.css` aplica `.pg-shell-scope` wrapper preservando tokens shell

## Acessibilidade WCAG 2.1 AA (F3.5)

- ESC fecha drawer/sheet/cheat
- Focus trap em drawer + sheet
- Scroll lock em body quando drawer aberto
- Atalhos teclado registrados no cheat sheet (`?`)
- aria-labels em botões de ação somente-ícone
- Contraste text/bg verificado via design:accessibility-review

## Refs

- ADR 0144 PaymentGateway extração ([decisions/0144](../../decisions/0144-paymentgateway-extracao-camada-cobranca.md))
- ADR 0170 PaymentGateway Onda 4 ([decisions/0170](../../decisions/0170-paymentgateway-module-extraction.md))
- Handoff F2 Cowork ([COWORK_HANDOFF.paymentgateway-ui.md](../../../COWORK_HANDOFF.paymentgateway-ui.md))
- F1.5 critique REPORT.md ([critiques/REPORT.md](../../../prototipo-ui/prototipos/payment-gateway-ui/critiques/REPORT.md))
- LICOES F3 Financeiro rejeitado ([LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md))
