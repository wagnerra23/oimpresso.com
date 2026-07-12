---
module: Connector
version: "1.0"
last_updated: "2026-05-16"
status: ativo
owners: [W]
na_justified:
  D4.c: "Connector é REST API externa pra clientes Delphi consumirem (zero UI Inertia/Blade próprias por design). CHARTER-rest-api-external.md documenta o contrato. Penalizar por 0 tsx não faz sentido — é módulo backend-only."
  D6.a: "Connector é REST API JSON-only — Inertia::defer N/A por design."
  D7.a: "PII em payloads REST passa via Passport auth — PiiRedactor aplicado em logs HTTP errors do TrustedDevicesMiddleware."
related_adrs: [0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado]
---

# SPEC — Modules/Connector

> **Módulo:** Connector (REST API externa)
> **Status:** ✅ ATIVO em produção — contrato externo congelado pra clientes Delphi
> **Owner técnico:** [F] (Felipe) + [W] (Wagner)
> **Última atualização:** 2026-05-16
> **Pareado com:** [BRIEFING.md](BRIEFING.md) · [CHARTER-rest-api-external.md](CHARTER-rest-api-external.md)

## Propósito

REST API externa do oimpresso. Exposta sob prefixo `/connector/api/*` com middleware `['log.delphi', 'auth:api', 'timezone']`. Consumidores:

- **Delphi WR Comercial** (legacy desktop, ~6 clientes saudáveis em migração OfficeImpresso → oimpresso)
- **SaaS Woo** (integração e-commerce — futuro)
- **Apps mobile UltimatePOS** (pattern herdado, 30 controllers REST)

## Regras Tier 0

- ⛔ **Não modificar contratos de payload/response** sem ADR (Delphi parsa string literal `S;msg`)
- ⛔ **Não remover middleware `log.delphi` antes de `auth:api`** (captura 401 pra debug)
- ⛔ **business_id global scope** em toda query Eloquent ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ⛔ **Token Passport NUNCA real em testes** — mocks/fakes
- ⛔ **Não rodar `route:cache` sem FQCN `::class`** — rotas em strings legacy quebram ([rule routes.md](../../../.claude/rules/routes.md))

## User Stories

### US-CONN-001 — Auth Passport `auth:api` bloqueia anônimo
**Como** consumidor REST externo
**Quero** receber `401 Unauthenticated` sem Bearer token
**Para** garantir fail-secure em todos os endpoints `/connector/api/*`
**Status:** ✅ implementado (`AuthApiTest`)
**Implementado em:** `Modules/Connector/Routes/api.php` · `Modules/Connector/Tests/Feature/AuthApiTest.php` · verificado@8af585a (2026-07-02) — middleware auth:api nos 3 grupos de rotas connector/api

### US-CONN-002 — Sync Delphi via `/processa-dados-cliente`
**Como** cliente Delphi WR Comercial
**Quero** enviar JSON array com NOME_TABELA=EMPRESA + LICENCIAMENTO
**Para** sincronizar cadastro + heartbeat do equipamento
**Contrato:** request JSON array; response STRING `S;msg` ou `N;motivo`
**Status:** ✅ ativo (G1 legacy ADR 0021)
**Implementado em:** `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php` · `Modules/Connector/Http/Controllers/Api/BusinessController.php` · verificado@8af585a (2026-07-02) — rota connector.delphi.processa-dados-cliente em Routes/api.php; doProcessaDadosCliente delega saveBusiness ao BusinessController e chama saveEquipamento local ($this->)

### US-CONN-003 — Registrar WR Comercial via `/oimpresso/registrar`
**Como** cliente WR Comercial novo
**Quero** registrar via JSON flat (cnpj, serial_hd, hostname, versao_exe)
**Para** receber autorização licença + dias_restantes + data_expiracao
**Contrato:** request JSON flat; response JSON `{autorizado: 'S'|'N', licenca_id, dias_restantes, data_expiracao}`
**Status:** ✅ ativo (G2 ADR 0021)
**Implementado em:** `Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php` · verificado@8af585a (2026-07-02) — rota connector.delphi.oimpresso.registrar em Routes/api.php

### US-CONN-004 — Check-update via `/check-update`
**Como** cliente Delphi
**Quero** enviar `CNPJ;VersaoAtual` em text/plain
**Para** receber `VersaoNova;VersaoMinObrigatoria` ou `N;VersaoMinObrigatoria`
**Status:** ✅ ativo (campos `business.versao_disponivel` + `versao_obrigatoria`)
**Implementado em:** `Modules/Connector/Http/Controllers/Api/CheckUpdateController.php` · verificado@8af585a (2026-07-02) — rota connector.delphi.check-update em Routes/api.php

### US-CONN-005 — REST CRUD `/contactapi`
**Como** app externo
**Quero** index/show/store/update de Contacts (clientes/fornecedores)
**Contrato:** JSON padrão Laravel API Resource; paginação default UltimatePOS
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/ContactController.php` · verificado@8af585a (2026-07-02) — resource contactapi (index/show/store/update) + contactapi-payment em Routes/api.php

### US-CONN-006 — REST CRUD `/product`
**Como** app externo
**Quero** index/show de produtos + variations + selling-price-group
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/ProductController.php` · verificado@8af585a (2026-07-02) — resource product + selling-price-group + variation/{id?} em Routes/api.php

### US-CONN-007 — REST CRUD `/sell` (vendas)
**Como** app externo
**Quero** index/store/show/update/destroy de vendas + sell-return + shipping-status
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/SellController.php` · verificado@8af585a (2026-07-02) — resource sell + sell-return + list-sell-return + update-shipping-status em Routes/api.php

### US-CONN-008 — REST `/business-location` (filiais)
**Como** app externo
**Quero** index/show de business_locations da minha empresa
**Multi-tenant:** scope automático por `business_id` do token Passport
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/BusinessLocationController.php` · verificado@8af585a (2026-07-02) — resource business-location (index/show) em Routes/api.php

### US-CONN-009 — REST `/taxonomy` + `/brand`
**Como** app externo
**Quero** index/show de categorias + marcas pra montar cardápio mobile
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/CategoryController.php` · `Modules/Connector/Http/Controllers/Api/BrandController.php` · verificado@8af585a (2026-07-02) — resources taxonomy + brand em Routes/api.php

### US-CONN-010 — REST `/user`
**Como** app externo (gestor)
**Quero** index/show/loggedin + user-registration de usuários da empresa
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/UserController.php` · verificado@8af585a (2026-07-02) — resource user + user/loggedin + user-registration + update-password + forget-password em Routes/api.php

### US-CONN-011 — Sync `salvar-cliente` + `salvar-equipamento/{business_id}`
**Como** cliente Delphi
**Quero** persistir Business + Licenca_Computador via 2 endpoints separados
**Contrato:** request JSON; response STRING legacy `S;msg`/`N;motivo`
**Status:** ✅ ativo
**Implementado em:** `Modules/Connector/Http/Controllers/Api/BusinessController.php` · `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php` · verificado@8af585a (2026-07-02) — rotas connector.delphi.salvar-cliente (saveBusiness) + connector.delphi.salvar-equipamento (saveEquipamento) em Routes/api.php

### US-CONN-012 — CRM API (`crm/follow-ups`, `crm/leads`)
**Como** app externo de vendas
**Quero** sincronizar follow-ups + call-logs do CRM via REST
**Status:** ✅ ativo (sub-grupo `connector/api/crm/*` com mesmo stack `auth:api`)
**Implementado em:** `Modules/Connector/Http/Controllers/Api/Crm/FollowUpController.php` · `Modules/Connector/Http/Controllers/Api/Crm/CallLogsController.php` · verificado@8af585a (2026-07-02) — grupo connector/api/crm (follow-ups, follow-up-resources, leads, call-logs) em Routes/api.php

## Pegadinhas catalogadas

- **3 formatos de body Delphi** (`array_tabelas`, `json_flat`, `pipe`) — todos suportados em `DelphiSyncService::detectBodyFormat()`
- **CNPJ resolution** prioriza `business_locations.cnpj` (filial), fallback `business.cnpj` (matriz)
- **HD compartilhado** entre N businesses (notebook de suporte remoto) — `update all` em `licenca_computador.hd`
- **Response `text/plain` literal** — NÃO mudar pra JSON nos endpoints legacy (Delphi parsa split(';'))

## Próximos passos potenciais

- Documentação OpenAPI 3.0 gerada via `scribe` (pendente — clientes pedem)
- Rate limiting per-business em `/connector/api/*` (Hostinger shared = throttle 60/min default)
- WebHook outbound pra clientes Delphi notificarem mudanças (push em vez de poll)

## ADRs relacionadas

- [ADR 0021](../../decisions/0021-...-connector-delphi-restaurado.md) — Endpoints Delphi restaurados do 3.7 (se existir)
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — Runtime Hostinger ≠ CT 100
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1
