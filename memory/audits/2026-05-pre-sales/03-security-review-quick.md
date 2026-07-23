---
id: audits-2026-05-pre-sales-03-security-review-quick
---

# Security review estático — pré-sales

> Análise estática (sem rodar exploits). Worktree: `amazing-williamson-0c8854`. Branch: `claude/amazing-williamson-0c8854`.
> Data: 2026-05-09. Restrição Wagner: tenancy scope **não** modificado nesta auditoria.

Severidade: **Critical** (RCE/wipe), **High** (privilege escalation/data exposure), **Medium** (info disclosure/DoS), **Low** (code smell).

---

## 1. Auth bypass / privilege escalation

| ID | Severidade | Arquivo:linha | Issue (1 frase) | Fix (1 frase) |
|---|---|---|---|---|
| **A-1** | **Critical** | `routes/install_r.php:19` + `app/Http/Controllers/Install/InstallController.php:265-286` | `POST /install/install-alternate` é público (sem middleware) e roda `migrate:fresh --force` + `db:seed --force` se `.env` existir → **wipe total da DB de produção por requisição não-autenticada** | Adicionar `middleware(['auth', 'superadmin'])` no `install_r.php` ou checar `isInstalled()` (que aborta 404 se .env existe) também em `installAlternate` |
| **A-2** | High | `routes/install_r.php:15-23` | Todas rotas `/install/*` são públicas — só `index/checkServer/details/postDetails` chamam `isInstalled()` (404 se .env existe). `update`/`updateConfirmation` também rodam Artisan | Wrap todo `routes/install_r.php` em `Route::middleware(['auth', 'superadmin'])` exceto `/install-start` no fluxo zero-state |
| **A-3** | Medium | `routes/web.php:124` `/sign-in-as-user/{id}` | Rota só tem `auth` middleware; controller checa `auth()->user()->can('superadmin')` (`ManageUserController.php:452`) — defesa funcional mas single-layer. Se algum dia alguém remover o check controller-side, qualquer user vira qualquer outro | Adicionar `middleware('superadmin')` no Route::get pra defesa em profundidade |
| **A-4** | Medium | `routes/web.php:127-129` `/showcase/components` | Já tem middleware `superadmin` ✅ — referência boa | — |
| **A-5** | Low | `app/Http/Controllers/Auth/SocialAuthController.php:170-189` | `createUserAndBusiness` cria User+Business em **uma transação** — se atacante triggera callback social repetidamente com email novo, cria businesses ilimitados (DoS via DB bloat) | Rate limit `RateLimiter::for('social-callback', …)` |
| **A-6** | Low | Vários | `protected $guarded = ['id']` em 20+ Models (`app/Account.php`, `Brands.php`, `Category.php`, `BusinessLocation.php`, etc) — torna `business_id` mass-assignable | Migrar pra `$fillable` explícito por Model em sprint dedicado (Wagner restrição: tenancy só com Pest local) |

---

## 2. SQL injection

| ID | Severidade | Arquivo:linha | Issue (1 frase) | Fix (1 frase) |
|---|---|---|---|---|
| **S-1** | Medium | `Modules/Superadmin/Http/Controllers/BusinessController.php:194-212` | `whereRaw` com `'$today'`/`'$yesterday'`/`$operator` interpolados — **valores controlados internamente** (Carbon + literal `'>'`/`'='`), mas se algum dia `request()->input` chegar até ali sem whitelist, vira SQLi | Migrar pra binding `?` + array params; consolidar em named query builder |
| **S-2** | Low | `app/Http/Controllers/HomeController.php:340, 417` | `whereRaw("DATEDIFF(…, '$today')…")` interpola `$today` (Carbon::now() format) — **safe hoje** mas code smell | Trocar pra `?` binding |
| **S-3** | Medium | `app/Utils/InstallUtil.php:138`, `app/Utils/ProductUtil.php:96, 147, 251, 1559` | `whereRaw('LOWER(name) = "'.strtolower($var).'"')` — `$variation_template_name`, `$variation_value_name`, `$v['value']`, `$product->brand_id` interpolados sem bind. `$v['value']` pode vir de array request controlado pelo usuário (import CSV) | Trocar todos pra `whereRaw('LOWER(name) = ?', [strtolower($var)])` |
| **S-4** | High | `app/Http/Controllers/ReportController.php:3205` | `whereRaw("IF(P.type='variable', CONCAT(…)) LIKE '%{$keyword}%'")` — `$keyword` da request interpolado direto na string SQL | Trocar pra `LIKE ?` com `["%{$keyword}%"]` (já é o padrão usado em outras linhas do mesmo controller) |
| **S-5** | Low | `app/Utils/TransactionUtil.php:3835, 3839` | `whereRaw("date(transaction_date) <= '$date'")` — `$date` parece interno mas merece audit | Trocar pra binding |
| **S-6** | Low | `Modules/Crm/Console/CreateRecursiveFollowup.php:123` | `whereRaw("DATEDIFF('$current_date', DATE(transaction_date)) = $days_diff")` — valores são internos (Carbon + int) | Code smell; bind por defesa |

**Total raw SQL injection candidates:** 21 usos `whereRaw\(.*\$` em `app/`, 10+ em `Modules/`. Maioria interpola Carbon dates ou keys de array internas — risco real apenas onde `request()` chega até ali.

---

## 3. XSS

| ID | Severidade | Arquivo:linha | Issue (1 frase) | Fix (1 frase) |
|---|---|---|---|---|
| **X-1** | Low | 12 ocorrências `{!! $var !!}` em `resources/views/` (`user/show_details.blade.php:1`, `taxonomy/edit.blade.php:1`, `documents_and_notes/show.blade.php:2`, etc) | Blade unescaped output — geralmente HTML "intencional" (formulários `Form::` shim, layouts), mas merece verificar se algum recebe input usuário | Auditar caso a caso; trocar pra `{{ $var }}` onde possível ou usar `e($var)` |
| **X-2** | Low | 21 arquivos React com `dangerouslySetInnerHTML` (`Site/Page.tsx`, `Site/BlogPost.tsx`, `kb/Index.tsx`, `Essentials/Knowledge/Show.tsx`, etc) | Renderiza markdown/HTML server-rendered. Se conteúdo vem de DB/CMS sem sanitização, XSS persistente | Verificar que conteúdo passa por sanitizer (DOMPurify-like) antes de salvar; ideal: usar lib markdown segura (já tem `remark-gfm` no package.json) |
| **X-3** | Medium | `resources/views/home/index.blade.php:6` | Concatenação `tw-from-{{session('business.theme_color')}}-800` em classe CSS — atacante que consiga setar `theme_color` malicioso quebra layout (não é XSS clássico mas CSS injection) | Whitelist em backend `BusinessController:56-70` ✅ já existe mas não enforça `update` |

---

## 4. Mass assignment

| ID | Severidade | Arquivo:linha | Issue | Fix |
|---|---|---|---|---|
| **M-1** | Medium | 20 Models em `app/` com `$guarded = ['id']` | `business_id` mass-assignable. Atacante autenticado em business=4 que controla request body pode setar `business_id=1` em Model novo, criando linhas em outro tenant | **Não modificar nesta auditoria** (Wagner 2026-05-09). Sprint dedicado: migrar pra `$fillable` ou validar `business_id` em Form Request |
| **M-2** | Low | `app/Http/Controllers/Auth/SocialAuthController.php:145-155` | `User::create([...])` aceita `password`, `language`, `provider_id`, `avatar_url` — campos OK mas não declara `$fillable` no User Model — verificar | Auditar `app/User.php` |

---

## 5. File upload

| ID | Severidade | Arquivo:linha | Issue | Fix |
|---|---|---|---|---|
| **F-1** | High | `app/Utils/Util.php:742` (`uploadFile`) | `$new_file_name = time().'_'.$request->$file_name->getClientOriginalName();` — **não sanitiza nome do arquivo**; aceita qualquer caractere (path traversal `../../../`, null bytes em PHP < 8 não, mas filename com `.htaccess`/`.php` perigoso se servido) | Usar `Str::slug()` no nome ou hash randômico; rejeitar nomes com extensões executáveis |
| **F-2** | High | `app/Utils/Util.php:730, 736` | Validação MIME usa `getClientMimeType()` — **header controlado pelo cliente**, trivialmente forjável. Browser pode enviar `image/png` em arquivo `.php` | Trocar pra `getMimeType()` (server-detected via finfo) ou usar `mimes:jpg,png` Laravel rule |
| **F-3** | High | `app/Http/Controllers/DevolucaoController.php:166` | `mkdir(public_path('xml_entrada/'.$cnpj), 0777, true)` — escreve em `public/` com **0777** (world-writable). XML aceito sem validação de schema | Mover storage pra `storage/app/private/`, perms 0750, validar XML contra XSD NF-e |
| **F-4** | High | `app/Http/Controllers/PurchaseXmlController.php:170` | Mesma issue F-3 — escreve `public_path('xml_entrada/'.$cnpj)` 0777 | Mesma fix |
| **F-5** | Medium | `app/Http/Controllers/Install/ModulesController.php:251-267` | Upload de módulo (zip) checa `getMimeType() != 'application/zip'` ✅ (server-side) e cria `../Modules` com mkdir 0777. Após upload extrai zip — **zip slip** se não validado | Verificar extração contra path traversal (lib `ZipArchive` permite arquivos com `../`); usar `realpath()` check. Confinar a superadmin only ✅ (já é) |
| **F-6** | Low | `app/Utils/Util.php:741` | `getSize() <= config('constants.document_size_limit')` — **silenciosamente ignora arquivos grandes** sem feedback | Lançar exception ou retornar erro |

---

## 6. Multi-tenant scope leaks (suspeitas — NÃO modificar)

> Wagner 2026-05-09: mudanças tenancy só com Pest local. Aqui só **listo suspeitas** pra próxima sprint.

| ID | Severidade | Arquivo:linha | Suspeita |
|---|---|---|---|
| **T-1** | High (suspeita) | `Modules/NFSe/Services/NfseEmissaoService.php:29, 132` | `withoutGlobalScopes()` SEM comentário `// SUPERADMIN: <razão>` — viola convenção `multi-tenant-patterns` skill |
| **T-2** | High (suspeita) | `Modules/NFSe/Jobs/EmitirNfseJob.php:34` | `NfseEmissao::withoutGlobalScopes()` em job assíncrono — risco real pois job não tem session() context |
| **T-3** | Medium | `Modules/Whatsapp/Entities/WhatsappBusinessPhone.php:144` | `withoutGlobalScopes()` mas vinculado a `where('business_id', $businessId)` explícito ✅ — semântica OK, falta comentário |
| **T-4** | Medium | `app/Http/Controllers/HomeController.php:340-345` | Query usa `business_id = $business_id` (vindo de session) ✅ + `permitted_locations` — OK |
| **T-5** | Low | `Modules/Superadmin/Http/Controllers/BusinessController.php:194` | `whereRaw("(SELECT … FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) = '$today')")` — usa `business.id` correlacionado, sem leak entre tenants ✅ |

---

## 7. Secrets em código

| ID | Severidade | Arquivo:linha | Issue | Fix |
|---|---|---|---|---|
| **K-1** | ✅ | — | Nenhum hit em Grep `(api_key\|secret\|token\|password)\s*=\s*['"][a-zA-Z0-9]{20,}` em `app/` ou `Modules/` | — |
| **K-2** | ✅ | — | `.env` não commitado (Glob retorna só `.env.example` e `.env - Copia.example`) | — |
| **K-3** | Low | `composer.json:53` | `stripe/stripe-php: "^7.122"` — versão major 7 (atual estável é 14+). Patches de segurança Stripe 2024-2025 perdidos | `composer require stripe/stripe-php:^14.0` em sprint dedicado |
| **K-4** | Low | `composer.json:13` | `automattic/woocommerce: "^3.0"` — versão major 3 (atual 4+) | Atualizar |
| **K-5** | Low | `composer.json:18` | `giggsey/libphonenumber-for-php: "^8.12"` — versão major 8 (atual 9+) | Atualizar |

---

## 8. Outdated dependencies (composer.json/package.json)

### Composer (PHP)

| Pacote | Atual repo | Stable atual (~2026-05) | Severidade |
|---|---|---|---|
| `aloha/twilio` | `^4.0` | 4.x ✅ | — |
| `automattic/woocommerce` | `^3.0` | 4.x | Low (1 major atrás) |
| `barryvdh/laravel-dompdf` | `^3.0` | 3.x ✅ | — |
| `consoletvs/charts` | `^6.5` | 6.x (descontinuado, abandoned em 2024) | **Medium** — pacote abandoned |
| `eduardokum/laravel-boleto` | `^0.11.0` | 0.x ✅ | — |
| `giggsey/libphonenumber-for-php` | `^8.12` | 9.x | Low |
| `laravel/framework` | `^13.0` | 13.x ✅ | — |
| `laravel/octane` | `^2.15` | **VIOLA Tier 0 IRREVOGÁVEL no Hostinger** (ADR 0062) | **Critical (governança)** |
| `laravel/mcp` | `^0.7.0` | **VIOLA Tier 0 IRREVOGÁVEL no Hostinger** | **Critical (governança)** |
| `mpdf/mpdf` | `^8.1` | 8.x (CVEs 2024 patched só em 8.2.5+) — verificar lock | Medium |
| `nwidart/laravel-modules` | `^10.0` | 12.x | Low |
| `razorpay/razorpay` | `2.*` | 2.x ✅ | — |
| `stripe/stripe-php` | `^7.122` | 14+ | Medium |

### NPM (JS)

| Pacote | Atual | Latest | Severidade |
|---|---|---|---|
| `@inertiajs/react` | `^3.0.3` | 3.x ✅ | — |
| `react`, `react-dom` | `^19.0.0` | 19.x ✅ | — |
| `@tanstack/react-table` | `^8.21.3` | 8.x ✅ | — |
| `lucide-react` | `^0.460.0` | 0.500+ | Low |
| `framer-motion` | `^11.18.0` | 12.x | Low |
| `tailwindcss` | `^4.0.0` | 4.x ✅ | — |
| `zod` | `^3.23.0` | 4.x | Low |

---

## Top 10 Critical/High consolidados (todos blocos)

| # | ID | Severidade | Arquivo:linha | Esforço fix |
|---|---|---|---|---|
| 1 | **A-1** wipe DB via `/install/install-alternate` | **Critical** | `routes/install_r.php:19` | 5 min (middleware) |
| 2 | **A-2** install routes públicas | **High** | `routes/install_r.php:15-23` | 5 min |
| 3 | **F-2** uploadFile MIME client-trusted | **High** | `app/Utils/Util.php:730` | 30 min |
| 4 | **F-1** filename não sanitizado | **High** | `app/Utils/Util.php:742` | 15 min |
| 5 | **F-3/F-4** mkdir 0777 em public/xml_entrada | **High** | `DevolucaoController.php:166`, `PurchaseXmlController.php:170` | 1h (mover pra storage/) |
| 6 | **K-1/K-2 (governança)** octane+mcp em composer.json prod | **Critical (governança)** | `composer.json:26-27` | 10 min |
| 7 | **S-4** ReportController $keyword interpolado | **High** | `ReportController.php:3205` | 5 min |
| 8 | **S-3** ProductUtil whereRaw interpolado | **Medium** | `ProductUtil.php:96, 147, 251, 1559` | 30 min |
| 9 | **A-3** sign-in-as-user single-layer | **Medium** | `routes/web.php:124` | 5 min |
| 10 | **T-1/T-2** NFSe withoutGlobalScopes sem comentário | **High (suspeita)** | `NFSe/Services/NfseEmissaoService.php:29, 132` + `EmitirNfseJob.php:34` | NÃO mexer (Pest local) |

---

## Prospect impact (matar venda)

- **#1 (A-1):** se prospect tem CISO/security review, descobre install-alternate público em 5 min com `dirb` ou Burp → venda morta.
- **#3, #4, #5 (F-1/F-2/F-3):** auditor LGPD vê upload sem MIME real + chmod 0777 → considera "incompetência básica em segurança" → venda morta.
- **#6 (octane+mcp):** prospect que conhece arquitetura Laravel pergunta "vocês rodam Octane no Hostinger shared?" — resposta correta é "não" mas composer.json diz que sim → governança quebrada → venda morta.
- **#10 (NFSe scope leak suspeitas):** prospect multi-tenant audita scope → vê `withoutGlobalScopes` em job sem proteção → venda morta. Mas só mexer com Pest local (Wagner).

**Recomendação imediata pré-demo:** consertar #1, #2, #6 (≈20 min total) antes de qualquer apresentação a prospect com auditoria técnica/CISO. #3-#5 em sprint de 1 dia. #10 em PR dedicado com Pest verde.
