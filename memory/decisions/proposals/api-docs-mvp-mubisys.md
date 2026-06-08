# API docs MVP — destravar 1ª venda Mubisys — 2026-05-09

## Contexto

Playbook migração Mubisys (RA fev/2023) identificou **API pública documentada** como selling point #1 contra Mubisys ("engessado, sem integração"). Prospect Mubisys olha `oimpresso.com/api/docs`, vê endpoints REST documentados com exemplos copy-paste e Postman collection — e fecha. Sem isso, conversa morre em "manda PDF".

**Boa notícia descoberta nesta análise:**
- `knuckleswtf/scribe ^5.0` já está em `composer.json` (nunca rodado — `public/docs/` vazio)
- `laravel/passport ^13.0` já em uso (`auth:api` middleware nas rotas Connector)
- **~300 anotações Scribe já escritas** em `Modules/Connector/Http/Controllers/Api/*` (`@group`, `@queryParam`, `@bodyParam`, `@response`, `@authenticated`)
- 12 Resources em `Modules/Connector/Transformers/` prontos pra renderizar JSON

**Logo: estimativa 16-24h Felipe é VIÁVEL, mas pra MVP. Release 2 (auditar + cobrir 30+ endpoints restantes) é outras 30-40h.**

ROI esperado: 1 contrato Mubisys = R$ [redacted Tier 0] (R$ [redacted Tier 0]/m + R$ [redacted Tier 0]k setup). Investimento ~20h × ~R$ [redacted Tier 0]/h = R$ [redacted Tier 0]k. Payback 1-2 meses.

## Inventário endpoints atuais

| Path | Method | Controller | Resource? | FormRequest? | Pronto? |
|---|---|---|---|---|---|
| `/connector/api/sell` | GET/POST/PUT/DEL | `SellController` | ✅ SellResource | ❌ inline `validate()` | ✅ MVP |
| `/connector/api/contactapi` | GET/POST/PUT | `ContactController` | ✅ NewContactResource | ❌ inline | ✅ MVP |
| `/connector/api/product` | GET | `ProductController` | ✅ ProductResource | ❌ inline | ✅ MVP |
| `/connector/api/expense` | GET/POST/PUT | `ExpenseController` | ✅ ExpenseResource | ❌ inline | ✅ MVP |
| `/connector/api/cash-register` | GET/POST/PUT | `CashRegisterController` | ❌ | ❌ inline | 🟡 ok |
| `/connector/api/business-location` | GET | `BusinessLocationController` | ✅ BusinessLocationResource | n/a | ✅ MVP |
| `/connector/api/business-details` | GET | `CommonResourceController` | ✅ CommonResource | n/a | ✅ MVP |
| `/connector/api/payment-methods` | GET | `CommonResourceController` | ❌ array puro | n/a | ✅ MVP |
| `/connector/api/profit-loss-report` | GET | `CommonResourceController` | ❌ | n/a | 🟡 |
| `/connector/api/product-stock-report` | GET | `CommonResourceController` | ❌ | n/a | 🟡 |
| `/connector/api/taxonomy` | GET | `CategoryController` | ❌ | n/a | ✅ MVP |
| `/connector/api/brand` | GET | `BrandController` | ❌ | n/a | ✅ MVP |
| `/connector/api/unit` | GET | `UnitController` | ❌ | n/a | ✅ MVP |
| `/connector/api/tax` | GET | `TaxController` | ❌ | n/a | ✅ MVP |
| `/connector/api/types-of-service` | GET | `TypesOfServiceController` | ✅ TypesOfServiceResource | n/a | ✅ MVP |
| `/connector/api/user` | GET | `UserController` | ❌ | n/a | 🟡 PII — auditar |
| `/connector/api/get-attendance/{id}` | GET | `AttendanceController` | ❌ | n/a | 🟡 |
| `/connector/api/clock-in` `clock-out` | POST | `AttendanceController` | ❌ | n/a | 🟡 |
| `/webhooks/asaas/{businessId}` | POST | `AsaasWebhookController` (RecurringBilling) | n/a (webhook) | n/a | ✅ MVP |
| `/whatsapp/webhook/{meta,zapi,baileys}/{uuid}` | POST | `Whatsapp` 3 controllers | n/a | n/a | 🟢 release 2 |
| `/connector/api/oimpresso/registrar` | POST | `OImpressoRegistroController` | ❌ | n/a | ❌ Delphi-specific |
| `/connector/api/processa-dados-cliente` | POST | `LicencaComputadorController` | ❌ | n/a | ❌ Delphi legacy |
| `/connector/api/check-update` | POST | `CheckUpdateController` | ❌ | n/a | ❌ Delphi |
| `/connector/api/crm/follow-ups` | GET/POST/PUT | `Crm/FollowUpController` | ❌ | n/a | 🟢 release 2 |
| `/connector/api/field-force` | GET/POST | `FieldForce/FieldForceController` | ❌ | n/a | 🟢 release 2 |
| `Modules/NfeBrasil/Routes/api.php` | — | só stub `fn () => $request->user()` | ❌ | n/a | ❌ **CRIAR** |
| `Modules/Financeiro/Routes/api.php` | — | só stub | ❌ | n/a | ❌ **CRIAR** |
| `Modules/Repair/Routes/api.php` | n/a | NÃO existe arquivo | n/a | n/a | ❌ release 2 |

**Auth atual:** `auth:api` (Passport OAuth2) com fluxo `/oauth/token` (password grant + client credentials) — já funcionando pro Delphi WR Comercial.

## MVP — 12 endpoints prioritários (1ª release Swagger pública)

Critério: máximo valor demonstrável pro prospect Mubisys + zero refactor de bug + Resource já existe.

### Tier 1 — leitura (5 endpoints, zero risco)

1. **GET `/connector/api/sell`** — listar OS/vendas com 13 filtros (location, contact, status, payment_status, datas, etc). Já tem `@queryParam` × 13 + `@response` completo (~80 linhas). **Demo killer.**
2. **GET `/connector/api/sell/{id}`** — detalhe da OS com sell_lines + payments + purchase_price (cost mapping). Já tem Resource.
3. **GET `/connector/api/contactapi`** — listar clientes/fornecedores com filtros (type, name, mobile, custom_field).
4. **GET `/connector/api/product`** — listar produtos com filtros (sku, location_id, name, brand_id, category_id, sub_category_id, selling_price_group_id) + paginação.
5. **GET `/connector/api/business-details`** — info da empresa (logo, endereço, regime tributário). "Como o ERP me identifica."

### Tier 2 — escrita (4 endpoints, valor 10×)

6. **POST `/connector/api/contactapi`** — criar cliente (CPF/CNPJ + endereço). Caso de uso: importação ERP legacy → oimpresso.
7. **POST `/connector/api/sell`** — criar venda/OS com sell_lines + payment + types_of_service. **Endpoint #1 do prospect Mubisys** ("nosso e-commerce manda pedido pro ERP").
8. **PUT `/connector/api/sell/{id}`** — atualizar OS (status final/draft/quotation, payment_status, shipping_status).
9. **POST `/connector/api/contactapi-payment`** — registrar pagamento avulso (recebimento de cliente). Pareia com PIX/boleto externo.

### Tier 3 — webhook + auxiliar (3 endpoints, prova de integração bidirecional)

10. **GET `/connector/api/payment-methods`** — listar formas de pagamento ativas (cash/card/bank_transfer/pix). Curto, mas obrigatório antes de POST sell.
11. **GET `/connector/api/business-location`** — listar filiais (multi-location). Obrigatório antes de POST sell.
12. **POST `/webhooks/asaas/{businessId}`** — webhook Asaas (PAYMENT_RECEIVED, PAYMENT_OVERDUE, PAYMENT_REFUNDED). Selling point: "ERP recebe webhook e fecha OS automaticamente." Já tem idempotência testada (`AsaasWebhookIdempotencyTest`).

**Não-MVP (mas valor alto demonstrável depois):** GET `/connector/api/profit-loss-report`, `/connector/api/product-stock-report`, `/connector/api/sell-return`, `/connector/api/expense`.

## Fora-de-MVP (release 2)

- Endpoints CRM (`follow-ups`, `leads`, `call-logs`)
- Field Force (`field-force/*`)
- Endpoints Whatsapp webhooks (Meta/Z-API/Baileys) — alto valor mas explicar 3 drivers cansa o prospect na 1ª release
- Attendance (`clock-in`, `clock-out`, `get-attendance`)
- Cash register (CRUD)
- Stock report, profit-loss report
- User management
- ADS endpoints (`Modules/ADS/Routes/api.php`) — muito interno
- **NfeBrasil API** (`POST /nfe/emitir`, `GET /nfe/{id}/status`) — **PRECISA CRIAR ZERADO** (`Modules/NfeBrasil/Routes/api.php` é stub). Servico interno (`NfeService`, `NfeStatusController`) existe mas só web routes. Estimativa: +6h só pra wrapper API
- **Financeiro API** (`GET /contas-receber`, `GET /contas-pagar`, `POST /baixa-pagamento`) — `Modules/Financeiro/Routes/api.php` é stub. +8h
- Endpoints Delphi (`processa-dados-cliente`, `check-update`, `oimpresso/registrar`) — escopo cliente legacy, NÃO documentar pública (só interna)

## Stack proposto

**Escolha: Scribe ^5.0** — já instalado, ~300 anotações já escritas no Connector, gera HTML interativo + OpenAPI 3 + Postman collection v2.1 num único `php artisan scribe:generate`.

**Por que NÃO L5-Swagger:** PHPDoc Swagger-style (`@OA\Get`) verboso comparado a `@queryParam`. Re-anotar 300 endpoints = 8h jogadas fora.

**Por que NÃO Scramble:** zero-config é elegante, mas (a) lê só FormRequests + Resources, e Connector usa `validate()` inline → cobertura ruim sem refactor. (b) trocar de stack quando outra já está paga é violação ADR 0011.

**Hosting:** `oimpresso.com/docs` (default Scribe — `public/docs/index.html`). Sem subdomain (1× CNAME a menos pra gerenciar). `oimpresso.com/docs.json` = OpenAPI spec. `oimpresso.com/docs.postman_collection.json` = Postman.

**Auth pra docs em si:**
- HTML público (qualquer prospect pode olhar)
- Tentar endpoint via "Try it out" exige Bearer token Passport → prospect cria conta trial → vê valor real

**Auth dos endpoints documentados:**
- Passport OAuth2 já em uso (`POST /oauth/token` password grant) — documentar fluxo na intro
- Rate limit: `throttle:60,1` por token (já default Laravel) — adicionar `throttle:api-public:30,1` em rota pública (futuro)
- CORS: `config/cors.php` já existe — auditar em fase 3 antes de público

**Postman collection auto-gerada:** `php artisan scribe:generate` gera `public/docs/collection.json` com todas as 12+ chamadas + auth helper. Botão "Run in Postman" no topo da página.

## Implementação faseada

### Fase 1 — bootstrap Scribe + 5 endpoints leitura (8h Felipe)

- [ ] `php artisan scribe:install` (já instalado, só publica config)
- [ ] Editar `config/scribe.php`:
  - `title` = "oimpresso ERP — API pública v1"
  - `description` = pitch curto (3 linhas)
  - `auth.default` = `true`, `auth.in` = `bearer`, `auth.location` = `header`
  - `intro_text` markdown com link `/oauth/token` + exemplo curl
  - `routes[0].match.prefixes` = `['connector/api/sell*', 'connector/api/contactapi*', 'connector/api/product*', 'connector/api/business-*', 'connector/api/payment-methods', 'oauth/token']` (filtra MVP)
- [ ] Audit nas anotações `@response` existentes — algumas têm 200+ linhas (SellController), validar JSON parse
- [ ] Adicionar `@authenticated` em endpoints faltando + escolher 2-3 sample requests por endpoint
- [ ] `php artisan scribe:generate` → publica em `public/docs/`
- [ ] Smoke local + commit

### Fase 2 — escrita + auth (8h Felipe)

- [ ] Adicionar `@bodyParam` em `POST /sell` (mais complexo — sell_lines array, payments array)
- [ ] Mesmo pra `POST /contactapi`, `PUT /sell/{id}`, `POST /contactapi-payment`
- [ ] Validar com `php artisan scribe:generate --verbose` que warnings = 0
- [ ] Documentar fluxo Passport completo (`/oauth/clients`, `password_grant`, refresh token)
- [ ] Adicionar exemplos curl + axios + python requests
- [ ] Adicionar página "Como começar" com tutorial 5min (criar app → token → 1ª chamada)

### Fase 3 — webhooks + Postman + landing (4h Felipe)

- [ ] Documentar webhook Asaas (`POST /webhooks/asaas/{businessId}`) — pegada externa, NÃO `auth:api`
- [ ] Validar Postman collection abre + auth helper funciona
- [ ] Adicionar landing `oimpresso.com/api` redirecionando pra `/docs` com hero "API REST pública — diferencial vs Mubisys"
- [ ] Adicionar link na sidebar admin "API & Integrações" → `/copiloto/admin/api-keys` (Passport client management)
- [ ] CI: workflow GitHub `docs-build.yml` rodando `scribe:generate` + commit em `public/docs/` (auto-publish)

**Total MVP: ~20h Felipe [F]** — alinhado com estimativa inicial 16-24h. Não é underestimate pra MVP, MAS:

> ⚠️ **Estimativa inicial 16-24h subestima cobertura completa.** 16-24h = APENAS o que descrevi acima (12 endpoints Connector que já têm Resources). Documentar NfeBrasil + Financeiro + Repair API (zerados, hoje stubs) = +20-30h adicional. Felipe deveria estimar **MVP=20h, "API completa pra venda enterprise"=50h**.

## Risco-mitigação

- **Risco: expor endpoint com bug em prod** → mitigação: rate-limit Passport, `Throttle:30,1` global em `RouteServiceProvider`, `audit_log` table já existe (UltimatePOS) cobrindo escrita
- **Risco: API mudar e quebrar cliente integrado** → mitigação: prefixar todas rotas com `/v1/connector/api/...` (HOJE NÃO TEM `v1/`! release 2 quebra). **Hoje as rotas Connector NÃO são versionadas** — Felipe deveria adicionar redirect `/connector/api/* → /v1/connector/api/*` antes de publicar pública (~2h extra)
- **Risco: SQL injection via API** → mitigação: Eloquent + queries parametrizadas confirmadas em audit do `SellController` (linhas 80-178). FormRequests faltando = risco médio (validation inline em try/catch funciona, mas auditar com ferramenta tipo Larastan)
- **Risco principal técnico que pode atrasar (>20h)**: anotações `@response` Scribe precisam reproduzir uma chamada real — se o controller depende de `Auth::user()` + multi-tenant scope, Scribe roda em modo `dummy_database` e pode falhar nas chamadas que tocam `business_id` global scope (ADR 0093). **Solução:** usar `@responseFile` apontando pra fixture JSON estática ao invés de `@apiResource` dinâmico. Risco real: +4h se 5 endpoints precisarem dessa migração
- **Risco: PII em response example** → mitigação: hard-code `[REDACTED]` em CPFs/CNPJs nos `@response` blocks (já tem CPF=null em vários, mas auditar)
- **Risco: Passport credenciais em screenshot público** → mitigação: gerar token de demo com escopo readonly + biz=999 sandbox

## Checklist pré-launch público

- [ ] Endpoints retornam JSON consistente (Resources + envelope `{status, message, data}` — hoje Connector mistura puro Resource e envelope BaseApiController)
- [ ] FormRequests substituem `validate()` inline em endpoints POST/PUT (release 2)
- [ ] Throttle middleware confirmado em `app/Http/Kernel.php` `api` group
- [ ] CORS limitado a domínios conhecidos OU `*` apenas em GET readonly
- [ ] Audit log de chamadas de escrita (UltimatePOS já tem `audit_log` em `App\Utils\TransactionUtil`)
- [ ] Página de status pública (status.oimpresso.com — Uptimekuma no CT 100? release 3)
- [ ] Termo de uso da API publicado (template Stripe-style + LGPD)
- [ ] Versionamento `/v1/` aplicado (HOJE NÃO TEM)
- [ ] Sandbox env separado (`api-sandbox.oimpresso.com` apontando pra `business_id=999`) — release 3

## Marketing pós-launch

- Post LinkedIn Wagner: "Lançamos API REST pública oimpresso — diferencial vs Mubisys/Zênite/Alfa. Documentação: oimpresso.com/docs"
- Post blog SEO: "Como integrar oimpresso ao seu Mercado Livre/Shopify/ERP herdado em 5 minutos"
- Slide adicional no deck enterprise: print da página `/docs` Tier 1
- Update site marketing com badge "API pública" no header
- Comparativo direto na CAPTERRA-FICHA Mubisys: "API pública documentada: oimpresso ✅ vs Mubisys ❌"

## ROI esperado 90 dias

| Item | Valor |
|---|---|
| Investimento Felipe (20h × R$ [redacted Tier 0]/h estimado) | R$ [redacted Tier 0] |
| 1 contrato Mubisys fechado (R$ [redacted Tier 0]/m + R$ [redacted Tier 0]k setup) | R$ [redacted Tier 0] (mês 1) |
| Anuidade Mubisys 1 cliente | R$ [redacted Tier 0]/ano |
| Payback investimento | 1 cliente / 1 mês |
| Cliente #2 = lucro 100% | mês 2-3 |

**Marco de validação:** 1º contrato fechado citando "vi o /docs" como razão de escolha = MVP comprovado. Se 90d sem fechar 1 com esse motivo, revisitar narrativa marketing (não a API).

---

## Observações finais

1. **MVP é viável em 20h porque 70% do trabalho já foi feito** (Scribe instalado, 300 anotações, 12 Resources, Passport ativo, OAuth2 fluxo pronto)
2. **Caminho menos arriscado:** publicar SOMENTE endpoints Connector (já testados em prod com Delphi WR Comercial cliente real desde 2026-04). Não inventar endpoints novos
3. **Maior gap arquitetural detectado:** rotas `/connector/api/*` NÃO têm prefixo `/v1/`. Antes de publicar público, adicionar grupo redirect `Route::prefix('v1')->group(...)` com link de retrocompatibilidade. Sem isso, breaking change futuro vira incidente
4. **Risco principal:** Scribe `@response` blocks com 200+ linhas (SellController) podem ter JSON malformado escondido — Felipe precisa rodar `scribe:generate --verbose` cedo na Fase 1 e ler todos os warnings
5. **Próximo passo recomendado:** Wagner aprovar este plano + criar US no MCP `tasks-create module:Connector title:"API docs MVP — Scribe + 12 endpoints"` apontando pra este arquivo, owner Felipe [F], cycle ativo

**Refs:** Mubisys playbook (não anexado); ADR 0011 (alinhamento padrão Jana); ADR 0021 (Connector restaurado 3.7); ADR 0045 (Hostinger DNS API canônica); composer.json; `Modules/Connector/Routes/api.php`; `Modules/Connector/Http/Controllers/Api/*`
