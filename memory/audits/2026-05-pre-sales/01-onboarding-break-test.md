---
id: audits-2026-05-pre-sales-01-onboarding-break-test
---

# Auditoria pré-sales — Onboarding break-test

> Análise estática (sem rodar app). Foco: signup → criar business → seed roles → primeiro login → primeira venda.
> Restrição: tenancy global scope NÃO modificado (feedback Wagner 2026-05-09).
> Worktree: `amazing-williamson-0c8854` · Data: 2026-05-09

---

## 1. Sequência mapeada (estado real do código)

### Caminho A — Signup tradicional (`/business/register`)

| # | Passo | Arquivo | Linha | Observação |
|---|---|---|---|---|
| 1 | GET form | `app/Http/Controllers/BusinessController.php::getRegister` | 87 | Renderiza Blade `business.register` — **NÃO** Inertia. Inconsistência com login Inertia (PR3) |
| 2 | POST cria | `BusinessController::postRegister` | 122 | 13 campos required (`name`, `currency_id`, `country`, `state`, `city`, `zip_code`, `landmark`, `time_zone`, `first_name`, `username`, `password`, `fy_start_month`, `accounting_method`) |
| 3 | Cria User | `User::create_user($owner_details)` | 175 | password min:4 — **fraco** (gov 2026 recomenda ≥8) |
| 4 | Cria Business | `BusinessUtil::createNewBusiness` | 199 | Hardcoded: `sell_price_tax='includes'`, `default_profit_percent=25`, `enable_inline_tax=0` |
| 5 | Default resources | `BusinessUtil::newBusinessDefaultResources` | 205 | Cria role `Admin#{biz_id}` + `Cashier#{biz_id}`, Walk-In Customer, InvoiceScheme, InvoiceLayout, Unit, NotificationTemplate |
| 6 | addLocation | linha 206 | — | Cria `BusinessLocation` + `Permission::create('location.{id}')` |
| 7 | Hook módulos | `moduleUtil->getModuleData('after_business_created', ['business' => $business])` | 215 | **APENAS Superadmin escuta** (1 hit em todo `Modules/`) |
| 8 | Redirect | `redirect('login')` | 233 | Não auto-loga; usuário precisa fazer login manual com username (não email) |

### Caminho B — Login social (Google/Microsoft) — `SocialAuthController::callback`

| # | Passo | Arquivo:linha | Observação |
|---|---|---|---|
| 1 | OAuth redirect | `SocialAuthController.php:45` | Whitelist hardcoded `['google', 'microsoft']` |
| 2 | Callback | linha 75 | Se email existe → linka `provider_id` ao usuário existente |
| 3 | Cria User+Business | `createUserAndBusiness` (linha 134) | **Defaults silenciosos:** moeda BRL, timezone São Paulo, fy_start_month=1, fifo, business name = email, location name = email, country=Brasil, state=SP, city=São Paulo, zip=00000-000, landmark='-' |
| 4 | Auth::login | linha 124 | Auto-loga e redireciona pra `/home` |
| 5 | NÃO chama hook módulos | — | **CRÍTICO:** o `after_business_created` do Caminho A não é invocado aqui |

### Pós-login (qualquer caminho) — `LoginController::authenticated`

- Linha 109: bloqueia se `business->is_active = 0`
- Linha 117: bloqueia se `user->status != 'active'`
- Linha 125: bloqueia se `!user->allow_login`
- Linha 133: bloqueia user_customer sem CRM module subscription
- Linha 144 (`redirectTo`): **se `!can('dashboard.data')` && `can('sell.create')` → /pos/create**, senão `/home`

### Primeira venda (cliente fluxo `/sells/create`)

- Inertia Page já migrada (`resources/js/Pages/Sells/Create.tsx`)
- Depende de: `defaultLocation`, `walkInCustomer`, `paymentTypes`, `invoiceSchemes`, `taxes`, `priceGroups`, `posSettings` etc — todos provisionados no Caminho A.
- **Caminho B (social) NÃO cria taxes** — `TaxRate::forBusinessDropdown` retorna vazio. UI funciona, mas sem opção fiscal default.

---

## 2. Pontos de quebra ordenados por severidade

### P0 — bloqueador de venda em demo

#### P0-1. Login social cria business sem hook `after_business_created`
- **Onde:** `app/Http/Controllers/Auth/SocialAuthController.php:170-189` — `createUserAndBusiness`
- **Quebra:** Caminho A (linha 215 do `BusinessController`) chama `getModuleData('after_business_created', …)`. Caminho B não. Resultado: módulos que dependem desse hook (Superadmin, e qualquer módulo futuro que escute) ficam dessincronizados pra business criado via Google/Microsoft. Em demo isso significa que um prospect que clica "Continuar com Google" entra no app sem subscription/package vinculado.
- **Correção (5 min):** após `assignRole($roleName)` (linha 187), adicionar:
  ```php
  if (config('app.env') !== 'demo') {
      $this->moduleUtil->getModuleData('after_business_created', ['business' => $business]);
  }
  ```

#### P0-2. Default location social vem com endereço bobo (`zip=00000-000`, `landmark='-'`)
- **Onde:** `SocialAuthController.php:178-183`
- **Quebra:** o BusinessLocation criado via Google tem `zip_code='00000-000'` e `landmark='-'`. Algumas validações fiscais (NFCe — `Modules/NfeBrasil/Services/NfeService`) exigem CEP válido. Prospect tenta emitir nota → falha silenciosa de SEFAZ.
- **Correção (15 min):** após callback social, redirect pra wizard "complete seu cadastro" antes de liberar `/home`. Stub: `if (!$user->business->location->zip_valid) return redirect('/onboarding/complete')`.

#### P0-3. password min:4 no signup tradicional
- **Onde:** `BusinessController.php:143` — `'password' => 'required|min:4|max:255'`
- **Quebra:** signup aceita senha de 4 caracteres. LGPD Art. 46 + boas práticas 2026 = ≥8 com complexidade. Prospect que valida segurança em demo (compliance officer) reprova.
- **Correção (2 min):** trocar pra `min:8|regex:/[A-Z]/|regex:/[0-9]/`. RegisterController.php (Inertia) já usa `min:8` (linha 72) — inconsistência entre rotas.

#### P0-4. `composer.json` inclui `laravel/octane` e `laravel/mcp` — viola Tier 0 IRREVOGÁVEL
- **Onde:** `composer.json:26-27`
- **Quebra:** ADR 0062 + `memory/proibicoes.md` — esses pacotes NUNCA podem ir pro Hostinger. Se prospect roda `composer install` em produção shared (Hostinger), violação imediata da arquitetura. Skill `runtime-rules-hostinger-ct100` diz isso explicitamente.
- **Correção (10 min):** mover pra `composer.json` exclusivo do CT 100 ou `require-dev`, ou usar suggested. Validar no CI mwart-gate.

#### P0-5. `getRegister` ainda renderiza Blade (não Inertia) enquanto `/login` é Inertia
- **Onde:** `BusinessController.php:107` — `return view('business.register', ...)`
- **Quebra:** prospect entra em `/login` (UX nova Inertia), clica "Criar conta" → `/register` (Inertia novo simplificado), mas se ele cair em `/business/register` (link de email/legacy), vê tela Blade UltimatePOS antiga com 13 campos e estética 2018. Quebra de credibilidade.
- **Correção (4h):** migrar `business.register` → Inertia Page (MWART process — 5 fases ADR 0104). Não trivial.

### P1 — fricção forte mas não bloqueia demo

#### P1-1. Caminho A não auto-loga
- **Onde:** `BusinessController.php:233` — `redirect('login')->with('status', $output)`
- Usuário cadastra business + entra com username manual. Caminho B (social) auto-loga. Inconsistência confunde prospect.
- **Correção:** `Auth::login($user)` antes do redirect, usar `redirect('/home')`.

#### P1-2. Username é obrigatório no Caminho A, mas Caminho B gera automático
- **Onde:** `BusinessController.php:142` (required) vs `SocialAuthController.php:143` (auto)
- Caminho A força usuário a inventar username único (validação `unique:users`). Friction high.

#### P1-3. Nenhum módulo além de Superadmin escuta `after_business_created`
- **Onde:** Grep `after_business_created` retorna 1 hit em `Modules/` (Superadmin)
- 30 módulos ativos — Jana, Repair, NfeBrasil, Financeiro, Ponto etc — não auto-provisionam config default por business. Resultado: prospect cria business novo, abre `/repair` → tela vazia/quebrada porque `repair_statuses` não foi seedado.
- **Evidência:** `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` mostra `data_source='mock'` quando "Business sem repair_statuses ou job_sheets" (Index.tsx linha 173).

#### P1-4. Install routes existem em 30 módulos mas nenhuma é chamada automaticamente
- **Onde:** Glob `**/InstallController.php` retorna 31 controllers, mas nenhum invocado no fluxo de signup
- O `RUNBOOK-criar-modulo.md` documenta 3 rotas Install obrigatórias (admin manualmente clica "Install"). Em demo, prospect não sabe que tem que clicar em Modules → Install pra cada um.
- **Correção:** auto-install módulos default na criação do business (ou wizard pós-cadastro).

#### P1-5. SocialAuthController salva avatar de URL externa sem validação
- **Onde:** `SocialAuthController.php:154` — `$user->avatar_url = $socialUser->getAvatar()`
- URL salva direto. SSRF? Se URL é exibida em `<img>` ok, mas se for fetched server-side em algum job → SSRF.

### P2 — gap de UX, não quebra fluxo

- **P2-1.** `BusinessController::postRegister` linha 197: `enabled_modules` hardcoded a 6 itens (`purchases`, `add_sale`, `pos_sale`, `stock_transfers`, `stock_adjustment`, `expenses`). Nenhum módulo gráfico (NfeBrasil, Repair, Ponto) habilitado por default.
- **P2-2.** `getRegister` linha 89 — se `!config('constants.allow_registration')` redireciona pra `/`. Sem mensagem ao prospect.
- **P2-3.** `showRegistrationForm` (RegisterController Inertia, linha 50) só pede `name+email+password` — não cria business. Quem usa essa rota cria User órfão (`business_id=null` → bloqueado no login pelo `business->is_active` check).
- **P2-4.** `theme_colors` (linhas 56-70 BusinessController) lista 7 cores hardcoded — não respeita brand do prospect.
- **P2-5.** `BusinessUtil::allTimeZones` (linha 159) usa zona EDT (`new \DateTimeZone('EDT')`) — código antigo UltimatePOS. Lista pode estar errada.

---

## 3. Top 10 P0/P1 consolidados

| # | ID | Severidade | Esforço | Bloqueia demo? |
|---|---|---|---|---|
| 1 | P0-1 hook social | P0 | 5 min | Sim (módulo Superadmin não conhece o business) |
| 2 | P0-2 endereço fake social | P0 | 15 min | Sim (NFCe falha) |
| 3 | P0-3 password min:4 | P0 | 2 min | Sim em audit segurança |
| 4 | P0-4 octane/mcp em composer | P0 | 10 min | Sim em audit arquitetura |
| 5 | P0-5 register Blade vs Inertia | P0 | 4h | Sim (estética) |
| 6 | P1-1 não auto-loga A | P1 | 5 min | Não, mas fricção |
| 7 | P1-2 username obrigatório A | P1 | 10 min | Não |
| 8 | P1-3 hook só em Superadmin | P1 | 8h (touch 30 módulos) | Sim (Repair vazio em demo) |
| 9 | P1-4 install não automático | P1 | 4h | Sim (módulos não habilitados) |
| 10 | P1-5 avatar SSRF risk | P1 | 30 min | Não, mas paranoid prospect questiona |

**Recomendação:** consertar P0-1, P0-2, P0-3, P0-4, P1-1 antes de qualquer demo agendada (≈40 min total). P0-5 e P1-3/P1-4 são iniciativas de sprint.
