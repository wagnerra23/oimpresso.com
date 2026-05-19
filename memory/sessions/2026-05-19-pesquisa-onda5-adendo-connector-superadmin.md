---
data: 2026-05-19
tipo: pesquisa-adendo
modulo: PaymentGateway / Superadmin / Connector / Officeimpresso
adr_origem: 0170 (Onda 5)
status: pesquisa-apenas-nao-codificar
relacionado: 2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md
---

# Onda 5 PaymentGateway — adendo crítico: sistema canônico já tem 90% pronto

> **Wagner apontou:** "connector tem API do officeimpresso, e o módulo superadmin gerencia as permissões — pode ver as memórias".
>
> Pesquisa anterior (sessão 2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md) seguiu fielmente o ADR 0170 §C que propõe re-modelar dogfooding via `Plan` em RB biz=1 + tenants vira Contact + `Subscription` vira projection + listener novo `SuperadminLicenseObserver`. **Esse desenho é overkill** — o sistema canônico Connector + Officeimpresso + Superadmin já tem TODA a infraestrutura. ADR 0170 foi proposto antes de catalogar o que existe.

## 1. Sistema canônico ATUAL — inventário (pesquisado 2026-05-19)

### Connector — API SaaS externa (Modules/Connector/)

[Modules/Connector/Routes/api.php](../../Modules/Connector/Routes/api.php) expõe sob `/connector/api/*` (Passport `auth:api` + `throttle:120,1`):

| Endpoint | Função | Status |
|---|---|---|
| `GET active-subscription` | [SuperadminController::getActiveSubscription](../../Modules/Connector/Http/Controllers/Api/SuperadminController.php#L65) — resolve `Superadmin::Subscription::active_subscription($business_id)` + `getResourceCount` (location_count/user_count/product_count/invoice_count usados) | ✅ Live prod |
| `GET packages` | [SuperadminController::getPackages](../../Modules/Connector/Http/Controllers/Api/SuperadminController.php#L214) — lista todos `Superadmin::Package` com `custom_permissions` por módulo | ✅ Live prod |
| `POST oimpresso/registrar` | [OImpressoRegistroController::registrar](../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php) — Delphi WR Comercial CNPJ+serial_hd handshake → cria/atualiza `licenca_computador` + retorna `{autorizado, dias_restantes, data_expiracao}` | ✅ Live prod |
| `POST processa-dados-cliente` | Gen 1 Delphi (3.7 restaurado ADR 0021) — JSON EMPRESA+LICENCIAMENTO, resposta `S;msg` / `N;motivo` | ✅ Live prod |
| `POST salvar-cliente` + `salvar-equipamento/{id}` | Gen 1 Delphi — salva business + máquina | ✅ Live prod |
| `POST check-update` | Verifica versão Delphi (`business.versao_disponivel` / `versao_obrigatoria`) | ✅ Live prod |
| `POST contactapi-payment` | Pagamento via contact (WR2 / Delphi) | ✅ Live prod |

**Auth/identidade:** OAuth2 Passport. **Master user shared** (Delphi compartilha 1 user entre clientes) — identidade REAL vem do BODY (CNPJ + serial_hd), nunca do `user_id`. Pattern catalogado em [project-officeimpresso-modulo.md §"Armadilha CRÍTICA"](../reference/project-officeimpresso-modulo.md).

### Officeimpresso — gestão de licença desktop (Modules/Officeimpresso/)

[memory/reference/project-officeimpresso-modulo.md](../reference/project-officeimpresso-modulo.md) é canônico. Já existe em prod desde 3.7→6.7 (ADR 0017).

| Capacidade | Onde | Estado |
|---|---|---|
| Tabela `licenca_computador` (chave única hd + business_id + user_win) | DB prod | ✅ Live (dados históricos preservados na migração) |
| Bloqueio por MÁQUINA (`licenca_computador.bloqueado`) | Bit por máquina | ✅ Live |
| Bloqueio por EMPRESA (`business.officeimpresso_bloqueado`) | Bit + middleware [User::validateForPassportPasswordGrant](../../app/User.php) rejeita `/oauth/token` quando true | ✅ Live (clients 39, 107) |
| Validade `licenca_computador.dt_validade` | Date column → calc `dias_restantes` em [OImpressoRegistroController:127](../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php#L127) | ✅ Live |
| Auditoria `licenca_log` (event_listener + middleware, ADR 0018 v2) | Tabela + UI `/officeimpresso/licenca_log` machine-centric | ✅ Live |
| Log estruturado contexto biz (D9.b) — CNPJ hashed | [OImpressoRegistroController:58](../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php#L58) | ✅ Live |
| OTel span `connector.delphi.oimpresso.registrar` | OtelHelper wrap | ✅ Live (Wave 16) |
| Multi-CNPJ por business (matriz/filial) | `business_locations.cnpj` precedence — ADR 0020 grupo econômico | ✅ Live |
| `business.versao_disponivel` + `versao_obrigatoria` | Superadmin manage | ✅ Live (forçar update Delphi) |

### Superadmin — gestão de tenants SaaS (Modules/Superadmin/)

| Capacidade | Onde | Estado |
|---|---|---|
| `Subscription` model — source-of-truth de mensalidade tenant | [Modules/Superadmin/Entities/Subscription.php](../../Modules/Superadmin/Entities/Subscription.php) | ✅ Live |
| `Subscription::active_subscription($business_id)` + `upcoming_subscriptions` + `waiting_approval` | métodos estáticos | ✅ Live |
| `Subscription` scopes `approved` / `waiting` / `declined` | Eloquent scopes | ✅ Live |
| `Subscription` Spatie LogsActivity (LGPD D7.b CC Art. 206 — append-only obrigatório) | `useLogName('superadmin.subscription')` | ✅ Live |
| `Subscription.payment_transaction_id` + `paid_via` (string) | Coluna + lookup nos handlers | ✅ Live |
| **5 payment gateways implementados** em [SubscriptionController.php](../../Modules/Superadmin/Http/Controllers/SubscriptionController.php) — `payForPackage()` pattern | Razorpay, Stripe, PayPal, Paystack, Pesapal | ✅ Live |
| Pesapal callback handler [PesaPalController::pesaPalPaymentConfirmation](../../Modules/Superadmin/Http/Controllers/PesaPalController.php) — `status='COMPLETED'` → marca `Subscription.status='approved'` + set dates | 1 método 39 LOC | ✅ Live |
| `Package` model + `custom_permissions` (`{module}_module: 1` por pacote) | Define quais Modules cada pacote habilita | ✅ Live |
| `Subscription.package_details` (JSON snapshot do Package no momento da venda) | Coluna JSON | ✅ Live |
| `getResourceCount($business_id, $subscription)` — locations_created/users_created/products_created/invoices_created | ModuleUtil method, retorna delta atual vs limite do Package | ✅ Live |
| Notification `SendSubscriptionExpiryAlert` (alerta dias restantes) + `SuperadminCommunicator` | [DataController::parse_notification](../../Modules/Superadmin/Http/Controllers/DataController.php) | ✅ Live |
| UI `/subscription/index` — active + upcoming + waiting + pay form | [Modules/Superadmin/Resources/views/subscription/](../../Modules/Superadmin/Resources/views/subscription/) | ✅ Live (Blade legacy) |
| Permission `superadmin.access_package_subscriptions` (Spatie) | Controla acesso UI | ✅ Live |

### Permissions Spatie no oimpresso

Pattern catalogado em [whatsapp-permissions-spatie.md](../reference/whatsapp-permissions-spatie.md):

- `permissions` table **GLOBAL** (sem business_id) — Spatie cria on-demand
- `roles` table **per-business** (`Admin#1`, `Cashier#1`, etc — business_id)
- 3 source-of-truth: `model_has_permissions` (direto), `model_has_roles` (via role), `role_has_permissions`
- Comando canônico `php artisan whatsapp:register-permissions` — pattern reusável pra qualquer módulo
- Bug recorrente: permission listada em DataController nunca chega na tabela em prod até alguém atribuir via UI → módulo PaymentGateway PRECISA de comando equivalente `php artisan paymentgateway:register-permissions` (ainda não existe)

## 2. Como Onda 5 ADR 0170 COLIDE com sistema canônico

| O que ADR 0170 §C propõe | Sistema canônico já tem | Custo de implementar como ADR 0170 propôs | Risco |
|---|---|---|---|
| `Plan "SaaS Oimpresso Premium R$ 99,90/mês"` em RB biz=1 | `Superadmin::Package` (Starter / Regular / Unlimited) — controla módulos via `custom_permissions` | Duplicar source-of-truth: Plan RB vs Package Superadmin | **Alto** — drift entre duas tabelas; qual ganha? Refator caro |
| Tenants viram **Contact em biz=1** | Tenants já são `Business` independentes (Tier 0 ADR 0093); `Subscription.business_id` faz link direto | Backfill ~150 LOC + manter consistência | **Alto** — Tier 0 cross-tenant: tenant é simultaneamente Business + Contact? Confusão semântica + risco vazamento PII (Contact em biz=1 carrega dados do tenant) |
| `Superadmin::Subscription` vira **view materializada (projection)** | É SoT com Spatie LogsActivity ativo desde Wave 11 (LGPD D7.b CC Art. 206 — 10 anos prescrição) | Refator listener-driven + manter LogsActivity em projection | **Crítico** — projection que descende de evento perde garantias de strong consistency; auditor LGPD pode questionar |
| `SuperadminLicenseObserver` (handler novo escuta `CobrancaPaga`) atualiza `business.subscription_end_date += 1 mês` | `Subscription.end_date` é a fonte; pattern existente: [`PesaPalController::pesaPalPaymentConfirmation`](../../Modules/Superadmin/Http/Controllers/PesaPalController.php) faz EXATAMENTE isso quando Pesapal retorna `COMPLETED` | Listener novo ~80 LOC | **Médio** — pattern já existe, mas no path errado (`business.subscription_end_date` ≠ `Subscription.end_date`) |
| `PesaPalDriver` movido pra PaymentGateway | Pesapal vive em Superadmin há anos como gateway de Subscription | Mover quebra `paid_via='pesapal'` lookup histórico | **Alto** — Subscriptions antigas referenciam `paid_via='pesapal'` |
| Migration histórica `Superadmin::Subscription` → `RecurringBilling::Subscription` | Subscription tem Spatie LogsActivity append-only — migrar quebra trail LGPD | ~100 LOC + smoke 30d | **Crítico** — viola append-only |

**Diagnóstico:** ADR 0170 §C foi escrito em F0 brief (status "🟡 Proposto") antes de catalogar Connector + Officeimpresso + Superadmin existentes. **6 dos 7 itens da Onda 5 original duplicam ou conflitam com sistema canônico.**

## 3. Re-desenho proposto — Onda 5 SIMPLIFICADA

### Princípio: PaymentGateway entra como 6º payment gateway no Superadmin::Subscription, sem refatorar nada

```
ADR 0170 Onda 5 original  →  Onda 5 SIMPLIFICADA
─────────────────────────     ────────────────────────────────────
Plan RB biz=1 + Package   →  Apenas Package (atual)
Tenant vira Contact       →  Tenant continua só Business
Subscription = projection →  Subscription = SoT (atual)
LicenseObserver novo      →  Listener no MESMO arquivo Modules/Superadmin
business.end_date         →  Subscription.end_date (atual)
Backfill 150 LOC          →  Zero backfill
PesaPalDriver moved       →  PesaPal stays (deprecated marker)
~680 LOC                  →  ~150 LOC
```

### Escopo Onda 5 SIMPLIFICADA — 5 itens

| # | Item | Onde | Pattern imitado | LOC |
|---|---|---|---|---|
| 1 | Adicionar `paymentgateway_pix_automatico` na lista de gateways em [`SubscriptionController::payForPackage()`](../../Modules/Superadmin/Http/Controllers/SubscriptionController.php) | mesmo arquivo | Pesapal/Stripe pattern | ~30 |
| 2 | Form view "Pagar via PIX Automático BCB" | [Modules/Superadmin/Resources/views/subscription/pay.blade.php](../../Modules/Superadmin/Resources/views/subscription/pay.blade.php) (ou novo `pay_paymentgateway.blade.php`) | Pesapal pay view | ~50 |
| 3 | Listener `OnCobrancaPagaUpdateSubscription` em Modules/Superadmin/Listeners/ que escuta `CobrancaPaga` + filtra `origem_type='subscription_license'` + marca `Subscription.status='approved' + set dates` | Pattern [PesaPalController::pesaPalPaymentConfirmation](../../Modules/Superadmin/Http/Controllers/PesaPalController.php) — `if status='COMPLETED' { update dates }` | imitar 1:1 | ~40 |
| 4 | Comando `php artisan paymentgateway:register-permissions` | Pattern [whatsapp:register-permissions](../reference/whatsapp-permissions-spatie.md) | imitar 1:1 | ~50 |
| 5 | Pest `OnCobrancaPagaUpdateSubscriptionListenerTest` | Tests/Feature | imitar PesapalCallbackTest se existir | ~80 |
| **Total** | | | | **~250 LOC** |

### O que NÃO precisa fazer (vs ADR 0170 original)

- ❌ NÃO criar Plan em RB biz=1 — `Superadmin::Package` já existe
- ❌ NÃO fazer tenant virar Contact em biz=1 — `Subscription.business_id` resolve diretamente
- ❌ NÃO refatorar `Superadmin::Subscription` pra projection — continua SoT (preserva LGPD D7.b)
- ❌ NÃO criar backfill command — zero migração de dados
- ❌ NÃO mover `PesaPalDriver` pra PaymentGateway — Pesapal continua em Superadmin como gateway existente (deprecated marker em UI mas funcional)
- ❌ NÃO criar `business.subscription_end_date` ou similar — usar `Subscription.end_date` existente

### Diagrama do fluxo simplificado

```
Wagner cadastra Package "Premium R$ 99,90" em /superadmin/packages (já existe UI)
    │
    ▼
Tenant escolhe Package em /subscription/{package_id} → clica "Pagar via PIX Automático BCB"
    │
    ▼
SubscriptionController.payForPackage(paid_via='paymentgateway_pix_automatico')
    │ — chama app(PaymentGatewayContract::class)
    │      ->for($wagner_business_account)
    │      ->emitirPixAutomatico(EmitirCobrancaInput{
    │            origem_type='subscription_license',
    │            origem_id=<subscription.id pendente>,
    │            payer=<tenant.owner contact>,
    │            target_business_id=<tenant.id>,
    │            valor=999.00,
    │        })
    │ — cria Subscription.status='waiting' (igual Pesapal pattern atual)
    ▼
Tenant autoriza mandato BCB no app banco
    │
    ▼
Webhook BCB → /paymentgateway/webhooks/bcb-pix/{businessId=1} (Wagner)
    │ — BcbPixWebhookController processa
    │ — dispatch event CobrancaPaga(Cobranca{origem_type='subscription_license'})
    ▼
Listener OnCobrancaPagaUpdateSubscription.handle(CobrancaPaga $event)
    │ — if $event->cobranca->origem_type !== 'subscription_license': return
    │ — $sub = Subscription::find($event->cobranca->origem_id)
    │ — $sub->status = 'approved'
    │ — $sub->start_date / end_date / trial_end_date = calc dates
    │ — $sub->save() — Spatie LogsActivity registra (D7.b LGPD)
    ▼
Tenant Delphi WR Comercial faz POST /connector/api/oimpresso/registrar
    │ — OImpressoRegistroController.resolveBusiness($cnpj) → business_id=<tenant>
    │ — User::validateForPassportPasswordGrant verifica business.officeimpresso_bloqueado=false
    │ — Subscription::active_subscription(<tenant>) retorna a vigente
    │ — autorizado='S', dias_restantes=N
    │
    ▼
Fim — sem código novo neste path
```

**Reuso massivo:** ✅ 100% das peças já existem em prod exceto items 1-5 acima (250 LOC).

## 4. Riscos Tier 0 mitigados pelo re-desenho

| R original (ADR 0170) | Mitigação no re-desenho |
|---|---|
| **R1 cross-tenant** — handler precisa de `target_business_id` no payload | Mantém `Cobranca.business_id = 1` (Wagner) + `Cobranca.metadata.target_business_id = tenant_id`. Listener resolve via `Subscription.business_id` (que aponta pro tenant) — pattern Pesapal já faz isso há anos |
| **R2 distinguir SaaS de cobrança normal** | `Cobranca.origem_type = 'subscription_license'` (ADR 0170 já prevê esse enum value) — listener filtra |
| **R3 append-only auditoria LGPD D7.b** | `Subscription` continua SoT, Spatie LogsActivity intacta. ✅ Zero risk |
| **R4 tenants existentes Pesapal** | Pesapal continua funcionando como gateway existente. Zero migração de dados. PaymentGateway é OPÇÃO adicional |
| **R5 PesaPal vestigial** | NÃO desativa Pesapal. Wagner pode oferecer "PIX Automático (recomendado)" + "Cartão via Pesapal (legado)" lado a lado |

## 5. Pré-condições Onda 5 SIMPLIFICADA (vs versão original)

| # | Pré-condição | Estado | Onda 5 SIMPLIFICADA |
|---|---|---|---|
| 1 | Onda 1-4 PaymentGateway prontas | ✅ PRs #1125-#1136 | Mantém |
| 2 | Onda 2 migration `payment_gateway_credentials` em prod | ⚠️ Verificar | Mantém |
| 3 | Onda 3 webhooks cutover real | ⚠️ Verificar | Mantém |
| 4 | `RecurringBilling::Subscription` renomeada pra `Assinatura` | ❌ não confirmado | **REMOVIDO** — não há conflito porque Plan RB não entra |
| 5 | Credencial BCB Pix Automático cadastrada em biz=1 | ❌ Wagner manual | Mantém |
| 6 | Package "Premium" cadastrado em biz=1 em `/superadmin/packages` | ❌ Wagner ação manual (~5 min UI) | Mantém |
| 7 | Tenants→Contact biz=1 backfill | — | **REMOVIDO** |
| 8 | Plan "SaaS Premium" no RB biz=1 | — | **REMOVIDO** |

## 6. Esforço re-dimensionado

| Fase | ADR 0170 original | Onda 5 SIMPLIFICADA |
|---|---|---|
| Código novo | ~680 LOC | **~250 LOC** |
| Migrations | 2-3 (backfill + projection schema) | **0** |
| Comandos CLI | 2 (backfill + projection sync) | **1** (register-permissions) |
| Wave 0 prep | Rename Subscription→Assinatura | **Skip** |
| Wagner manual | Cadastrar Plan + credencial BCB + identificar tenants pra backfill | **Cadastrar credencial BCB (5min) + Package "Premium" se ainda não existe (5min)** |
| Smoke shadow | 14 dias | **7 dias** (menos surface area) |
| Cutover | 7 dias biz=1 → universal | **7 dias** |
| **Total dev** | 5 dias (12h com IA-pair) | **~3 dias (6h com IA-pair)** |
| **Total observação** | 21 dias | **14 dias** |

## 7. Decisão estratégica pra Wagner

3 opções:

**A) Onda 5 ADR 0170 original** — re-modela tudo, ~680 LOC, alto risco refatoração SoT
**B) Onda 5 SIMPLIFICADA (recomendada)** — PaymentGateway entra como 6º gateway, ~250 LOC, zero risco refator, máximo reuso, Pesapal coexiste durante deprecation
**C) Híbrido em fases:**
- Fase 5.A = SIMPLIFICADA agora (ROI imediato, dogfooding começa)
- Fase 5.B (futura, condicional a sinal cliente) = re-modelar pra projection mode SE houver sinal real de necessidade

## 8. Sinais que JUSTIFICARIAM versão original (não SIMPLIFICADA)

Per ADR 0105 (cliente como sinal qualificado), versão original ADR 0170 só faz sentido se:

| Sinal | Pergunta a Wagner |
|---|---|
| Múltiplos produtos SaaS no roadmap | Wagner planeja vender Plans diferentes via RB pra cliente final (não só mensalidade Oimpresso)? Se sim, Plan RB faz sentido em biz=1 |
| Cobranças avulsas SaaS além de mensalidade | Wagner vai cobrar consultoria/setup/treinamento/horas separadas via RB também? Se sim, Cobrança avulsa via PaymentGateway entra em Sells, não em Subscription |
| Audit cross-tenant pesado | Wagner precisa de view materializada Subscription pra Power BI / dashboards externos consumirem? Se sim, projection mode tem valor |
| Conformidade LGPD pede separação fisica de SoT | Auditor externo exigiu separar "licença SaaS" (Superadmin) de "cobrança bancária" (PaymentGateway) em modelo de dados? Se sim, refator se justifica |

Se nenhum ✅, **versão SIMPLIFICADA cobre 100% do caso atual**.

## 9. Próximos passos sugeridos

1. Wagner responde 4 perguntas da §8
2. Se SIMPLIFICADA: criar **ADR filho 0170-onda5-simplificada** documentando re-desenho + checklist de 5 itens + smoke plan 7d shadow + 7d cutover
3. Se ORIGINAL: manter ADR 0170 §C — partir pra blueprint executável de ~680 LOC com riscos catalogados na pesquisa anterior
4. ADR 0170 status passa de "🟡 Proposto" pra "✅ Aceito" depois de Wagner decidir A/B/C

## 10. Refs

- [ADR 0017](../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) — Officeimpresso restaurado (sistema canônico de licença desktop)
- [ADR 0018](../decisions/0018-log-acesso-desktop-fase-1-passivo.md) — Log acesso desktop event listener+middleware
- [ADR 0019](../decisions/0019-passport-v10-v13-auth-delphi.md) — Passport v13 auth Delphi
- [ADR 0020](../decisions/0020-grupo-economico-matriz-filial.md) — Grupo econômico
- [ADR 0021](../decisions/0021-contrato-real-api-delphi-3-geracoes.md) — Contrato API Delphi 3 gerações
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0170](../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — Extração PaymentGateway (Onda 5 atual proposta)
- [memory/reference/project-officeimpresso-modulo.md](../reference/project-officeimpresso-modulo.md) — sistema canônico Officeimpresso/Connector/Superadmin
- [memory/reference/whatsapp-permissions-spatie.md](../reference/whatsapp-permissions-spatie.md) — pattern register-permissions canônico
- [memory/sessions/2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md](2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md) — pesquisa anterior (versão ADR 0170 fiel)

---

**Conclusão pesquisa:** Wagner estava certo — **Connector + Officeimpresso + Superadmin já formam o sistema** que ADR 0170 §C propôs construir do zero. Re-desenho SIMPLIFICADO comprime esforço 3-4x e elimina riscos críticos (projection mode + backfill cross-tenant + refator append-only).

**Nada foi codificado.** Wagner aprova caminho A/B/C? Recomendo B (SIMPLIFICADA).
