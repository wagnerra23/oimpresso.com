# Cowork F2 handoff — PaymentGateway UI (3 telas) · 2026-05-19

**Status:** F1 ✅ + F1.5 ✅ + refators P0 + KB-9.75 substantivo aplicado.
**Wagner aprovou screenshots:** ✅ (aprovação visual no Chat Cowork live)
**Dispara:** F3 — Claude Code traduz Cowork [CC] → Inertia/React real no repo.

---

## Notas F1.5 finais (pós-refator)

| Tela | Persona | Gate | Inicial F1.5 | Pós refator | Status |
|---|---|---:|---:|---:|---|
| 1 · Cobrança | Eliana[E] + Larissa | 85 | 82 | **~96** | ✅ |
| 2 · Settings/Gateways | Wagner | 80 | 78 | **~93** | ✅ |
| 3 · Drawer Vendas + chip cobrança | Larissa | 90 | 84 | **~93** | ✅ |

**Média 94** — KB-9.75 substancialmente alinhado nas 3 telas.

## O que está incluído neste F1

| Tela | URL nova | Substitui |
|---|---|---|
| 1 | `/financeiro/cobranca` | `/financeiro/boletos` (rename + expansão) |
| 2 | `/settings/payment-gateways` | — (tela nova) |
| 3 | `/sells/{id}` drawer (footer chip) | modificação cirúrgica · 1 linha em `vendas-page.jsx` |

## Recursos canônicos · referência F1

- **ADR mãe:** [`memory/decisions/0144-paymentgateway-extracao-camada-cobranca.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0144-paymentgateway-extracao-camada-cobranca.md)
- **SCOPE/README/CONTRACTS:** `Modules/PaymentGateway/`
- **F0 brief:** `prototipo-ui/COWORK_NOTES.amendment-paymentgateway-batch.md`
- **F1.5 critique:** 3 JSONs + REPORT.md em `prototipo-ui/prototipos/payment-gateway-ui/critiques/`

## URLs públicas (válidas ~1h)

### Componentes F1 (.jsx)

| Arquivo | URL |
|---|---|
| `pg-shared.jsx` — tokens, helpers, mocks, atoms | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-shared.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `pg-cobranca-page.jsx` — Tela 1 | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-cobranca-page.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `pg-payment-gateways-page.jsx` — Tela 2 | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-payment-gateways-page.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `pg-vendas-integration.jsx` — Tela 3 (chip + drawer) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-vendas-integration.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `pg-shell-adapters.jsx` — wrappers Cockpit V2 | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-shell-adapters.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `pg-styles.css` — overrides shadcn → tokens shell | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-styles.css?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |

### F1.5 critique

| Arquivo | URL |
|---|---|
| `critiques/REPORT.md` | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/REPORT.md?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `critiques/cobranca-critique-score.json` | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/cobranca-critique-score.json?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `critiques/payment-gateways-critique-score.json` | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/payment-gateways-critique-score.json?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |
| `critiques/sells-integration-critique-score.json` | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/sells-integration-critique-score.json?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1 |

---

## Tarefa F3 pro Claude Code

### Pré-requisitos

1. **Backend Onda 3 webhooks** ✅ mergeada antes de começar F3 (per ADR 0144 roadmap)
2. **PaymentGatewayContract** disponível (Onda 1 já merged) — UI consome via DI
3. **Tabela `cobrancas`** existe (Onda 2) — read source pra Tela 1
4. **Tabela `payment_gateway_credentials`** existe (Onda 2 + backfill 2.5) — read source pra Tela 2

### Passo 1 — Baixar artefatos F1

```bash
mkdir -p prototipo-ui/prototipos/payment-gateway-ui/{components,critiques}

# Components
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-shared.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/components/pg-shared.jsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-cobranca-page.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/components/pg-cobranca-page.jsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-payment-gateways-page.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/components/pg-payment-gateways-page.jsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-vendas-integration.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/components/pg-vendas-integration.jsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-shell-adapters.jsx?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/components/pg-shell-adapters.jsx
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pg-styles.css?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/components/pg-styles.css

# Critique
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/REPORT.md?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/critiques/REPORT.md
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/cobranca-critique-score.json?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/critiques/cobranca-critique-score.json
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/payment-gateways-critique-score.json?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/critiques/payment-gateways-critique-score.json
curl -fsSL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipos/payment-gateway-ui/critiques/sells-integration-critique-score.json?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1" -o prototipo-ui/prototipos/payment-gateway-ui/critiques/sells-integration-critique-score.json
```

### Passo 2 — Tradução Cowork → Inertia (1 branch · 3 PRs separados)

Branch raiz: `feat/adr-0144-paymentgateway-ui` derivada de `main`.

**PR-1: Tela 1 Cobrança** (`/financeiro/cobranca`)
- `resources/js/Pages/Financeiro/Cobranca/Index.tsx` — converter `pg-cobranca-page.jsx` pra Inertia v3 + React 19 + TS
- `resources/js/Pages/Financeiro/Cobranca/_components/` — DrawerCobranca, SheetNovaCobranca, SheetRemessaRetorno, AiResumoMes, CheatSheet, FunnelStrip
- `Modules/Financeiro/Http/Controllers/CobrancaController.php` — index() retorna Inertia::render('Financeiro/Cobranca/Index', $shape)
- `Modules/PaymentGateway/Repositories/CobrancaQuery.php` — query escopada business_id + filtros (status, tipo, gateway, account, origem)
- Route `/financeiro/cobranca` + 301 redirect `/financeiro/boletos` durante 60d
- `Modules/Financeiro/Tests/Feature/CobrancaControllerTest.php` — 8 Pest GUARDs (Tier 0 + filtros + KB-9.75 anti-patterns)
- Rename `prototipo-ui/.../Boletos/Index.charter.md` → `Cobranca/Index.charter.md` + atualizar

**PR-2: Tela 2 Settings/PaymentGateways** (`/settings/payment-gateways`)
- `resources/js/Pages/Settings/PaymentGateways/Index.tsx` — converter `pg-payment-gateways-page.jsx`
- `resources/js/Pages/Settings/PaymentGateways/_components/` — DrawerGateway (4 tabs), SheetNovoGateway (3 steps), ConfirmToggleModal, CheatSheetSettings
- `Modules/PaymentGateway/Http/Controllers/PaymentGatewayController.php` — CRUD credenciais + health check + Spatie permission `paymentgateway.credenciais.*`
- `Modules/PaymentGateway/Services/HealthCheckService.php` — roda health real por driver
- Drivers concretos validam credenciais via `PaymentGatewayContract::healthCheck()` (já existe pela Onda 1)
- Route `/settings/payment-gateways` middleware permission
- Tests 8 Pest GUARDs (Tier 0 + Trust L3 audit toggle + masked secrets nunca em response)
- Charter novo: `prototipo-ui/resources/js/Pages/Settings/PaymentGateways/Index.charter.md`

**PR-3: Tela 3 drawer Vendas — chip cobrança** (`/sells/{id}` drawer)
- `resources/js/Pages/Sells/_components/CobrancaChip.tsx` — chip variant 5 estados
- `resources/js/Pages/Sells/_components/CobrancaDrawer.tsx` — drawer canon .os-drawer com 2 modos (view / form)
- Modificação cirúrgica em `Sells/Index.tsx` `VendaDetailDrawer` footer (1 import + 1 linha JSX)
- `app/Http/Controllers/SellController.php@sheetData` retorna `cobranca` relação (eager load)
- `app/Sale.php` — relação `hasOne(Cobranca::class, 'origem_id')->where('origem_type', 'sale')`
- Pest GUARD em `SellControllerTest`: regressão zero no drawer A+ 9,75 (atalhos preservados, FSM stepper preservado, fiscal preservado)
- Sem charter novo — amendment ao charter Sells/Index existente (status: live)

### Passo 3 — Validação por Wagner (F4 merge)

- ✅ Smoke test biz=1 (ROTA LIVRE) em sandbox sem afetar Onda 3 webhooks live
- ✅ Wagner aprova visual cada PR
- ✅ Acessibilidade WCAG 2.1 AA — F3.5 plugin local Claude Accessibility
- ✅ Merge sequencial: PR-1 → PR-2 → PR-3 (Tela 3 depende de Sells/Index estar limpa)

### Passo 4 — Onda 4 ADR 0144 continua após F3 UI

- Drivers reais (Inter/Asaas/C6/BCB Pix) ligados ao PaymentGatewayContract
- Botão "Emitir cobrança" no drawer Vendas dispara `app(PaymentGatewayContract::class)->emitirBoleto/Pix/Cartao(...)`
- Webhook handlers `Modules/PaymentGateway/Http/Controllers/Webhooks/*` (Onda 3) atualizam `cobrancas.status` real

---

## Critique notes pro Claude Code

**Refators P0 já aplicados no F1 (NÃO precisa refazer):**
- Status badges com ícones lucide (check/clock/alert/ban/xCircle)
- Filtros em 2 linhas (Tela 1)
- Cards drivers Settings só não-configurados
- Toggle ativo com confirm modal (Trust L3)
- Modal Vendas → drawer lateral canon
- Tailwind classes via `.pg-shell-scope` (sem inline styles)
- ESC + scroll lock + focus trap (WCAG 2.1)
- Atalhos teclado J/K/Esc/?/N + persistência localStorage (Tela 1 + 2)
- AI panel ✦ "Resumir mês" (Tela 1)
- Loading skeleton CSS (`.pg-skel-*`)
- Row actions pill aparece só no hover (pattern Linear/Stripe)
- Ghost-like buttons + hover transitions consistentes

**P1/P2 ainda em aberto (pode ser polish em F3 ou ficar pra próximo PR):**
- Funil Lembrete/Cobrança ativa derivado (até Onda 2 ter job real)
- Comentários inline por linha tabela
- Sort clicável colunas
- Drawer Sells com tabs (audit/anexos)
- Tooltip rich nos chips Tela 3

---

## Decisões Wagner codificadas no F1 (não inventar)

1. **PIX Automático = driver BCB direto** (não passa por Inter/Asaas) — chip violet, alerta Resolução BCB 380/2024
2. **Subscription Superadmin elimina-se** — Wagner cobra tenants via Plan em RB no `business_id=1` (dogfooding)
3. **PesaPal deprecated** — chip amber em toda UI, sheet "Migrar pra Asaas"
4. **Módulo separado** — chip composto gateway+tipo expressa visualmente a separação driver/tipo

---

## Próxima ação pro Claude Code

Abre prompt no terminal, cola este arquivo COMO CONTEXTO, executa Passo 1 + Passo 2 (3 PRs em série).

URL deste arquivo: https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/COWORK_HANDOFF.paymentgateway-ui.md?t=d4d4a8007db35c57736782e611e1720f815244bb1bd2b45f15364d428d364154.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779208726&direct=1
