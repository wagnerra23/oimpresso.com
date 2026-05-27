# Como integrar — Microsoft Clarity (session replay + heatmaps + smart alerts)

**Data:** 2026-05-25
**Agent:** `como-integrar` (introspectivo — só memory/ + código, zero web)
**Pedido Wagner:** mapear plug-points pra instalar Clarity no oimpresso/UltimatePOS — captura comportamental real dos 200 clientes (rage clicks, dead clicks, session replays, heatmaps). Custo zero, IA built-in.
**Veredito (TL;DR):** **AUSENTE** — projeto NÃO tem nenhum analytics/heatmap/session-replay instalado. Plug-points limpos. Mas há **3 pegadinhas Tier 0** que travam adoção ingênua (multi-tenant, LGPD, dual runtime). Recomendação: opção C (1 projeto Clarity global + custom tag `business_id`).

---

## 1. INVENTÁRIO — o que já existe (não duplicar)

### 1.1 Analytics / heatmap / session replay JÁ instalado?

| Procurei | Onde achei | Status |
|---|---|---|
| `clarity`, `clarity.ms`, `data-clarity-*` | nenhum match real (só menções em markdown de sessions/research) | **AUSENTE** |
| `hotjar`, `mixpanel`, `posthog`, `amplitude`, `fullstory`, `smartlook`, `vwo` | só false-positives em docs e libs minified (datatables, fontawesome) | **AUSENTE** |
| `googletagmanager`, `google-analytics.com`, `gtag(`, `GTM-`, `G-XXXX` | zero matches em código real (matches `paymentGatewayId` falsos) | **AUSENTE** |
| `Pusher.com` external script | `layouts/partials/javascripts.blade.php` carrega via env `PUSHER_APP_KEY` | usado em legacy Blade (substituído por Centrifugo no novo — ADR 0058) |
| `recaptcha` | `auth2.blade.php:24` carrega `google.com/recaptcha/api.js` | **único 3rd-party JS em prod hoje** (login page) |
| Service Worker (PWA) | `resources/js/app.tsx:48-62` registra `sw-financeiro.js` só em `/financeiro/*` | escopado por rota — bom precedente de pattern "carregar só onde precisa" |

**Conclusão Fase 1:** terreno limpo. Nenhuma duplicação a evitar. Nenhuma feature parcialmente implementada pra estender. PARAR? **Não** — Wagner pediu ferramenta nova, mas há trabalho de mapeamento real pelos 3 layers (Blade legacy + Inertia + AppShellV2). Seguir Fase 2.

### 1.2 Onde vivem `<head>` tags hoje?

| Layout | Arquivo | Usado por |
|---|---|---|
| **Inertia root** (Vite + React 19) | `resources/views/layouts/inertia.blade.php` (58 linhas) | TODA tela Inertia nova (Sells/Index, Cliente/Show, Jana/Cockpit, etc) |
| **Blade legacy UltimatePOS** | `resources/views/layouts/app.blade.php` (sidebar AdminLTE) | Telas legacy não-migradas (~70% do ERP ainda) — Compras, Estoque, Reports, Settings, Roles, Users, Permission |
| **Auth/login** | `resources/views/layouts/auth2.blade.php` (carrega recaptcha) | Login + repair status público |
| **Home/landing** | `resources/views/layouts/home.blade.php` | Página `/` pública, `consulta-os`, `repair-status` |
| **Restaurant** | `resources/views/layouts/restaurant.blade.php` | Módulo restaurante (cliente piloto ROTA LIVRE não usa) |
| **POS modo lockscreen** | `app.blade.php` flag `$pos_layout` | `/pos/create`, `/pos/X/edit`, `/pos/X/payment` |

**Pattern existente pra share global Inertia:** [`app/Http/Middleware/HandleInertiaRequests.php`](../../app/Http/Middleware/HandleInertiaRequests.php) já compartilha 9 chaves (`auth`, `business`, `ai`, `flash`, `shell`, `sells`, `locale`, `csrf_token`, `publicRoutes`). **PERFEITO ponto de plugue** pra `clarity.project_id` + `clarity.enabled` + `clarity.tag_business_id`.

**Pattern existente pra config externa em Blade legacy:** `layouts/partials/javascripts.blade.php:5-9` injeta `APP.PUSHER_APP_KEY` via `config('broadcasting.connections.pusher.key')`. Mesmo pattern serve pra Clarity: `APP.CLARITY_PROJECT_ID = '{{ config('services.clarity.project_id') }}'`.

### 1.3 Env / config

| Local | Achei? |
|---|---|
| `.env.example` no repo | **NÃO existe** (`.env` único em `D:\oimpresso.com\.env`, gitignored) — não há template versionado |
| `config/services.php` | tem `mailgun`, `postmark`, `ses`, `google`, `microsoft` (OAuth!), `asaas`, `inter`, `slack` — **`microsoft.client_id` JÁ EXISTE** pra OAuth Azure, mas é independente do Clarity (Clarity não usa Azure AD client_id, usa project_id próprio) |
| `config/` outros | nenhum `analytics.php`, `tracking.php`, `clarity.php` |

### 1.4 Tasks MCP / ADRs / SPECs sobre o tema

| Procurei | Onde | Status |
|---|---|---|
| ADR sobre analytics/session-replay/heatmap | `memory/decisions/*.md` | **AUSENTE** — nenhuma ADR sobre observability comportamental client-side. Há ADR 0127 (activity_log server-side audit) — domínio diferente. |
| SPEC com `US-OBS-*`, `US-ANALYTICS-*` | `memory/requisitos/**/SPEC.md` | nenhum match |
| Skills relevantes | `.claude/skills/` | nenhuma skill `analytics-integration` / `clarity` / `session-replay` |
| Memory sessions menção Clarity como decisão | `memory/sessions/` | só menção tangencial em [`2026-05-13-design-arte-sells-create.md`](2026-05-13-design-arte-sells-create.md) e [`2026-05-21-arte-sidebar-navegacao-comparativo.md`](2026-05-21-arte-sidebar-navegacao-comparativo.md) — Clarity citado como ferramenta possível, nunca implementado |
| Cookie consent / LGPD banner existente | grep `cookie|consent|LGPD` em `resources/` | matches só de strings em forms (`DadosFiscaisBRSection.tsx`, etc) e palavras "consentimento" em copy do Cliente — **NENHUM banner cookie consent implementado** |
| `PiiRedactor` ou mask helpers PII client-side | grep `PiiRedactor|maskPii|data-private` | só matches server-side (LLM logs em `ClienteResumoAgent`, `ClienteIaController`) — **NENHUM helper client-side pra mascarar DOM** |

**Conclusão Fase 1:** ferramenta inédita no oimpresso. Próxima decisão arquitetural válida → **ADR canon nova** ("Clarity como ferramenta canon de observability comportamental client-side").

---

## 2. PEGADINHAS APLICÁVEIS (Tier 0 + LGPD + UX)

### Pegadinha 1 — Multi-tenant ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) — DECISÃO ARQUITETURAL OBRIGATÓRIA

Clarity é "1 conta = 1 project ID = 1 site". 3 opções:

| Opção | Granularidade | Custo operacional | Recomendação |
|---|---|---|---|
| **(A) 1 projeto global oimpresso** | dados 200 clientes misturados; Wagner não distingue qual biz tem problema | trivial — 1 env var | ❌ **NÃO** — perde poder analítico do dashboard |
| **(B) 1 projeto Clarity por business_id** | granular total — dashboard por cliente | inviável — Wagner cria 200 projetos manualmente em `clarity.microsoft.com`, gerencia 200 project_ids no DB, comissiona script dinâmico | ❌ **NÃO** — não escala |
| **(C) 1 projeto global + `clarity('set', 'business_id', bizId)` custom tag** | dashboard tem **filtro nativo** por custom tag → mesma visão de (B) com infra de (A) | trivial — 1 env var + 1 linha de JS | ✅ **SIM** — recomendado |

Custom tags Clarity também aceitar: `user_type` (admin/operador/superadmin), `business_legacy_origin` (officeimpresso/novo) — permite Wagner filtrar "rage clicks só em bizs migradas do Delphi".

**Tier 0 check:** `business_id` deve vir de `auth.user.business_id` (server-side já em sessão — ver `HandleInertiaRequests:51`), **NUNCA** de query string ou input client. ADR 0093 §princípio 1.

### Pegadinha 2 — LGPD (Lei 13.709 art. 7º/8º + [feedback-nunca-publicar-credenciais.md](../reference/feedback-nunca-publicar-credenciais.md))

**Risco grave:** Clarity por default **grava session replay incluindo TUDO digitado em forms HTML**. Isso significa que CPF/CNPJ/telefone/email/endereço do cliente final do ROTA LIVRE (paciente, comprador, leitor) vão parar no dashboard **Microsoft Azure**. Sem opt-in explícito = violação LGPD art. 7º (base legal) + art. 8º (consentimento).

**Defesas Clarity nativas:**

1. **Mask all by default** — Clarity tem setting "Mask all text" no dashboard que mascarariza TODO conteúdo de texto/input por default; opt-out por elemento via `data-clarity-unmask="True"`. **Recomendado começar com "mask all"** e ir desmascarando elementos seguros.
2. **Mask seletivo** — `data-clarity-mask="True"` em inputs/divs sensíveis. Forms PII identificados:
   - `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx` — CPF/CNPJ, RG, nome
   - `resources/js/Pages/Cliente/_drawer/ContatoTab.tsx` — email/telefone/whatsapp
   - `resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx` — CEP, logradouro
   - `resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx` — inscrição estadual, IE
   - `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx` — busca por CPF/nome
   - `resources/js/Pages/Sells/_components/PaymentRow.tsx` — pode ter campo cartão (verificar)
   - `resources/js/Pages/Sells/_components/FiscalSection.tsx` — dados fiscais NFe
   - Blade legacy: `resources/views/contact/create.blade.php`, `resources/views/sell/create.blade.php` (existem? confirmar) — formulários UltimatePOS originais
3. **API `clarity('consent')`** — só ativa session recording após user clicar "aceito cookies".
4. **`clarity('identify', user_id)`** — **NÃO USAR** (Wagner pode achar atrativo) — passa user_id pra Microsoft. Cruzando com IP → permite re-identificação. Manter sessões pseudoanônimas.

**Cookie consent banner não existe no oimpresso.** Sem ele, Clarity é compliance-bomb. Trabalho pré-Clarity obrigatório: adicionar banner LGPD (mesmo pattern de site público — checkbox "Aceito cookies de análise comportamental" persistido em localStorage + cookie httponly).

### Pegadinha 3 — Dual runtime Blade + Inertia ([CLAUDE.md "Stack e estrutura"](../what-oimpresso.md))

oimpresso tem AMBOS:
- **Inertia React 19** (telas modernas) → root view `layouts/inertia.blade.php`
- **Blade legacy UltimatePOS** (~70% das telas ainda) → `layouts/app.blade.php`

Se Clarity entra só no Inertia, **rage clicks na tela Compras/Index (Blade) não são capturados**. Se entra só no Blade, telas Sells/Index (Inertia) não capturam.

**Decisão:** snippet Clarity precisa estar nos **2 layouts root** (`inertia.blade.php` E `app.blade.php`) + `auth2.blade.php` (login) + `home.blade.php` (público) + `restaurant.blade.php` se ROTA LIVRE migrar restaurante depois.

**Atalho:** criar partial Blade `layouts/partials/clarity.blade.php` (1 só arquivo) e dar `@include('layouts.partials.clarity')` nos 5 layouts. Mantém DRY + 1 ponto de update.

### Pegadinha 4 — Superadmin Wagner não pode contaminar dataset ([HandleInertiaRequests:190](../../app/Http/Middleware/HandleInertiaRequests.php))

Wagner é `user_type='superadmin'` ou `'user_oimpresso'`. Sessões de debug Wagner (que abre 20 telas em 30s, clica em N coisas pra testar) **vão poluir as métricas comportamentais reais dos 200 clientes**.

**Defesa:** condicional no snippet — não inicializar Clarity se `auth()->user()?->user_type` ∈ `['superadmin', 'user_oimpresso']`. Implementação: passar boolean `clarity.shouldLoad` via share Inertia + via `APP.CLARITY_ENABLED` em Blade.

Alternativa secundária: usar Clarity "IP blocklist" no dashboard pra IP fixo do escritório, mas IP de Wagner varia (mobile, casa) → menos confiável.

### Pegadinha 5 — Performance e Inertia::defer ([skill `inertia-defer-default`](../../.claude/skills/inertia-defer-default/SKILL.md))

Clarity script é ~30kb minified, carrega async com `<script>` tag injetada. **Não bloqueia render** (já é async/defer no snippet oficial). Confirmar 2 pontos:

- Snippet Clarity vai DEPOIS de `@inertiaHead` (não dentro) pra evitar conflito com Inertia client.
- Não dispara nada antes de `DOMContentLoaded` que interfira com hydration React 19 / Service Worker `/financeiro/sw-financeiro.js`.

Não há interferência conhecida com Centrifugo ([ADR 0058](../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) — Centrifugo é WebSocket pra notificações, runtime CT 100, lado servidor; Clarity é só client-side observability.

### Pegadinha 6 — Hostinger ≠ CT 100 ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md))

Confirmado: **Clarity é 100% client-side puro (JS no browser, dados POST pra clarity.microsoft.com)**. **Não afeta runtime nenhum**. Não precisa nada novo no CT 100. Sem impacto em `laravel/octane` (ADR 0062 proíbe no Hostinger anyway).

### Pegadinha 7 — Versionamento de `.env.example`

`.env.example` **NÃO existe no repo** (verificado via Read). Existe só `.env` na máquina (gitignored). Isso significa que documentar `CLARITY_PROJECT_ID` em `.env.example` requer criar o arquivo **pela primeira vez**, ou documentar em outro lugar (`README.md`, `config/services.php` comentário, ou ADR canon nova).

**Recomendação:** criar `.env.example` agora (pelo menos com `CLARITY_PROJECT_ID=` + comentário explicativo), mas isso é fora do escopo Clarity — escalar como TODO separado.

### Pegadinhas que NÃO se aplicam (transparência)

- FSM Pipeline (ADR 0143) — Clarity não toca state machine
- format_date +3h shift (ADR 0066) — Clarity não formata data
- NFe sequencial (ADR 0143) — sem relação
- Roles Spatie suffix `#{biz}` — Clarity não usa Spatie
- Junction NTFS Windows — sem impacto (sem worktree mexendo em vendor)
- MWART F1-F5 (ADR 0104) — Clarity não é Page Inertia nova, é snippet em layout
- Identifiers MySQL ≤64 chars — sem migration nova

---

## 3. PONTO DE PLUGUE (onde tocar, em ordem)

| Peça | Arquivo + linha aprox | Ação |
|---|---|---|
| **Config Laravel** | `config/services.php` (após bloco `slack`, linha ~117) | Adicionar bloco `clarity` com `project_id`, `enabled`, `block_superadmin`, comentário explicando multi-tenant via custom tag |
| **Env doc** | `.env.example` ⚠️ **arquivo não existe — criar** OU adicionar comentário em `config/services.php` | Documentar `CLARITY_PROJECT_ID=`, `CLARITY_ENABLED=false` |
| **Share Inertia** | `app/Http/Middleware/HandleInertiaRequests.php:64-155` (dentro do `array_merge`) | Adicionar chave `'clarity' => [...]` com `project_id`, `enabled` (calculado: env + !superadmin + !local), `business_id` (do session), `business_legacy_origin` (lazy via closure) |
| **Snippet Blade partial** ⚠️ **arquivo a criar** | `resources/views/layouts/partials/clarity.blade.php` | Novo partial: `@if(...)` envolvendo script oficial Clarity + `clarity('set', 'business_id', ...)` + skip se superadmin/local |
| **Include Inertia layout** | `resources/views/layouts/inertia.blade.php:53` (antes de `@inertiaHead` ou logo depois `@routes`) | `@include('layouts.partials.clarity')` |
| **Include Blade legacy layout** | `resources/views/layouts/app.blade.php:41` (antes `</head>`) | `@include('layouts.partials.clarity')` |
| **Include auth/login** | `resources/views/layouts/auth2.blade.php:25` (antes `</head>`) | `@include('layouts.partials.clarity')` — mas talvez **NÃO carregar em login** (sem business_id ainda + sem consent) — decidir |
| **Include home público** | `resources/views/layouts/home.blade.php` | mesmo dilema do login — sem business_id → fica num "bucket público" no dashboard |
| **Mask PII (Cliente)** | `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx` (CPF/CNPJ/nome) | adicionar `data-clarity-mask="True"` nos `<input>` |
| **Mask PII (Cliente)** | `resources/js/Pages/Cliente/_drawer/ContatoTab.tsx` (email/telefone/whatsapp) | `data-clarity-mask="True"` |
| **Mask PII (Cliente)** | `resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx` (CEP/endereço) | `data-clarity-mask="True"` |
| **Mask PII (Cliente)** | `resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx` (IE/IM) | `data-clarity-mask="True"` |
| **Mask PII (Sells)** | `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx` (input busca por CPF/nome) | `data-clarity-mask="True"` |
| **Mask PII (Sells)** | `resources/js/Pages/Sells/_components/FiscalSection.tsx` | `data-clarity-mask="True"` |
| **Mask PII (Sells)** | `resources/js/Pages/Sells/_components/PaymentRow.tsx` (campo cartão se existir) | `data-clarity-mask="True"` |
| **Mask PII Blade legacy** ⚠️ **listar arquivos** | `resources/views/contact/create.blade.php`, `resources/views/sell/*.blade.php`, `resources/views/user/*.blade.php` | grep `name="(tax_number|mobile|email)"` antes de Edit — listar inputs |
| **Cookie consent banner** ⚠️ **NÃO EXISTE — criar** | sugestão: `resources/js/Components/CookieConsent.tsx` (compartilhado AppShellV2) + Blade equivalente pra layouts legacy | banner LGPD com botões "aceitar análise" / "rejeitar". Persistir em `localStorage.setItem('oi.consent.analytics', 'true')` + cookie httponly opcional. **Clarity só inicializa após consent=true.** |
| **Pest test** ⚠️ **a criar** | `tests/Feature/Analytics/ClarityEnvGatedTest.php` | testar: (a) `CLARITY_ENABLED=false` → share Inertia retorna `clarity.enabled === false`; (b) user superadmin → `clarity.enabled === false`; (c) user comum + env true → `clarity.enabled === true` + `business_id` correto |
| **ADR canon nova** | `memory/decisions/0XXX-clarity-observability-comportamental-canon.md` (próximo número livre) | decisão "Clarity é ferramenta canon de session replay + heatmaps. 1 projeto global + custom tag `business_id`. Mask all default + opt-in LGPD." Referencia ADR 0093 (multi-tenant) + ADR 0127 (activity_log — domínio complementar server-side) |
| **Charter Settings** (S4+) | se houver `Settings` tela pra Wagner ligar/desligar Clarity per biz | adiar — começar global on/off via env |

**Peças que NÃO EXISTEM e precisam ser criadas (antes do snippet)** ⚠️:

1. `.env.example` (arquivo template do repo)
2. `resources/views/layouts/partials/clarity.blade.php` (partial Blade do snippet)
3. `resources/js/Components/CookieConsent.tsx` (banner LGPD)
4. Equivalente Blade `resources/views/layouts/partials/cookie-consent.blade.php`
5. Pest test env-gated
6. ADR canon nova

---

## 4. CHECKLIST PRÉ-CÓDIGO — Wagner aprovar

```markdown
## Pré-código checklist — Microsoft Clarity

### Decisões arquiteturais (Wagner aprova antes Claude codar)
- [ ] **Opção C confirmada:** 1 projeto Clarity global + custom tag `business_id` (não 200 projetos)
- [ ] **Cookie consent banner é pré-requisito:** Clarity NÃO entra sem banner LGPD opt-in (mesmo PR ou PR separado anterior?)
- [ ] **ADR canon nova:** vamos formalizar via `0XXX-clarity-observability-comportamental-canon.md`? (recomendado pelo agente — feature inédita Tier 0)
- [ ] **Estratégia mask:** começar com "Mask all" default no dashboard Clarity + desmascarar elementos seguros via `data-clarity-unmask` (mais seguro) OU começar com "mask none" + mascarar PII via `data-clarity-mask` (mais ágil dev)?
- [ ] **Login/home público:** carregar Clarity ou não? (sem business_id, dados vão pra bucket "anônimo" no dashboard — útil pra debug landing? ou poluição?)

### Antes de Edit/Write (Wagner já criou projeto em clarity.microsoft.com)
- [ ] Wagner pega `CLARITY_PROJECT_ID` (10 chars alfanuméricos) em https://clarity.microsoft.com
- [ ] Wagner adiciona no `.env` Hostinger via SSH (não commitar real)
- [ ] Confirmar feature flag necessária? **SIM** — `CLARITY_ENABLED=false` default + `CLARITY_ENABLED=true` só após smoke
- [ ] Schema migration necessária? **NÃO** (zero DB changes)
- [ ] ADR nova necessária? **SIM** — recomendado (decisão arquitetural Tier 0 client-side observability)

### Pegadinhas a respeitar (filtradas pra este caso)
- [ ] **Pegadinha 1 — Multi-tenant:** custom tag `business_id` setada via Inertia share, NUNCA via input client
- [ ] **Pegadinha 2 — LGPD:** mask all default + lista de inputs PII identificada (12+ arquivos React/Blade)
- [ ] **Pegadinha 3 — Dual runtime:** snippet em 2 layouts mínimo (inertia.blade.php + app.blade.php) via partial DRY
- [ ] **Pegadinha 4 — Superadmin não polui:** `clarity.enabled === false` se `user_type ∈ ['superadmin', 'user_oimpresso']`
- [ ] **Pegadinha 7 — `.env.example` ausente:** criar agora ou documentar em `config/services.php` comentário

### Pontos de plugue (em ordem)
- [ ] **Step 1 — Config:** `config/services.php` adicionar bloco `clarity` (~15 linhas)
- [ ] **Step 2 — Env:** criar/atualizar `.env.example` com `CLARITY_PROJECT_ID=` + `CLARITY_ENABLED=false`
- [ ] **Step 3 — Share Inertia:** `HandleInertiaRequests.php:64-155` adicionar chave `'clarity' => [...]` (lazy closure, multi-tenant safe)
- [ ] **Step 4 — Partial Blade:** criar `resources/views/layouts/partials/clarity.blade.php` (snippet oficial + condicionais)
- [ ] **Step 5 — Cookie consent banner:** criar `CookieConsent.tsx` + equivalente Blade + persistência localStorage
- [ ] **Step 6 — Includes:** `@include('layouts.partials.clarity')` em 2-5 layouts (inertia/app/auth2/home/restaurant — decidir login/home)
- [ ] **Step 7 — Mask PII:** adicionar `data-clarity-mask="True"` em 7+ arquivos React listados + N arquivos Blade legacy (grep prévio)
- [ ] **Step 8 — Pest test:** `tests/Feature/Analytics/ClarityEnvGatedTest.php` cobrindo 3 cenários
- [ ] **Step 9 — ADR canon:** escrever `memory/decisions/0XXX-clarity-observability-comportamental-canon.md`

### Smoke pós-deploy
- [ ] **biz=1 (Pest):** test passa — `CLARITY_ENABLED=false` em test config → share retorna `clarity.enabled === false`
- [ ] **biz=4 ROTA LIVRE prod:** Wagner abre `/sells/create` como user não-admin → script Clarity carrega → após 30s aparece sessão no dashboard com tag `business_id=4`
- [ ] **biz=4 superadmin (Wagner):** abre mesma tela → script NÃO carrega (verificar via DevTools Network)
- [ ] **PII smoke:** Wagner abre `/cliente/create` → preenche CPF fake → grava session replay → abre dashboard Clarity → confirma campo CPF aparece como `****` mascarado

### Estimativa total (IA-pair, ADR 0106 fator 10x + 2x margem)
- Config + share Inertia + partial Blade: **20min**
- Cookie consent banner (componente + Blade + persistência): **60min** (gargalo — feature nova de escopo amplo)
- Mask PII em 7+ arquivos React + grep Blade legacy: **40min**
- Pest test 3 cenários: **20min**
- ADR canon: **30min**
- Smoke prod biz=4 (relógio do mundo real — humano-limitado, não recalibrado): **30min**
- **Total: ~3h** (sem o cookie consent banner cai pra ~1h30min — banner é prerequisito LGPD e dobra escopo)
```

---

## Notas finais

### Por que recomendo cookie consent banner SEPARADO antes

Adicionar Clarity sem banner viola LGPD. Mas banner é feature de escopo próprio (decisão UX/copy + persistência + reuso em telas públicas). Misturar tudo num PR único = PR de 600+ linhas + 2 features = quebra `commit-discipline` Tier A. **Sugestão:** 2 PRs.

1. **PR 1:** Cookie consent banner (LGPD compliance independente do Clarity).
2. **PR 2:** Clarity integration consumindo o banner (`if (consent === 'true') { initClarity(); }`).

### Por que ADR canon é importante aqui

Clarity é **primeira ferramenta client-side observability** do oimpresso. Decisão Tier 0 não documentada → próximo dev (Felipe/Maiara/Eliana/Luiz quando entrar) replica padrão sem entender. ADR formaliza:

- Por que Clarity e não Hotjar/PostHog/Mixpanel (custo + IA built-in + LGPD-friendly)
- Por que 1 projeto global + custom tag (e não 200)
- Por que mask all default
- Por que superadmin excluído
- Quando reavaliar (200 → 2000 clientes? Custos $ aparecem?)

### Risco residual

Mesmo com tudo isso, **Microsoft Clarity envia dados pros servidores Microsoft (Azure US/EU)** — transferência internacional de dados pessoais. ANPD considera isso "operação de tratamento" sujeita a LGPD Cap. V (transferência internacional). Mitigação:

- Microsoft tem [Clarity Data Processing Addendum](https://clarity.microsoft.com/terms) (DPA) — Wagner pode anexar ao registro de operações.
- Recomendação adicional (fora escopo Claude): atualizar política de privacidade do oimpresso pra mencionar Clarity como sub-processador.

---

**Fim do dossier.** ~270 linhas, dentro do limite "200-600".
