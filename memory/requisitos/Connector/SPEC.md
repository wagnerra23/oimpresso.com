---
id: requisitos-connector-spec
module: Connector
version: "1.1"
last_updated: "2026-08-03"
status: ativo
owner: wagner
na_justified:
  D4.c: "Connector é REST API externa pra clientes Delphi consumirem — zero UI Inertia própria (0 tsx). Existe UMA tela Blade de admin (clients/index.blade.php — OAuth clients), cuja migração é a US-CONN-014; enquanto ela não fecha, penalizar por 0 tsx não faz sentido. CHARTER-rest-api-external.md documenta o contrato."
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

### US-CONN-013 · Documentar a API Connector em OpenAPI 3.0 sem expor dados reais

> owner: [F] · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: —
> parent_plan: connector-openapi

**Implementado em:** _pendente_ — a especificação executável foi criada; nenhuma documentação
foi gerada ou publicada e nenhum contrato runtime foi alterado.

**Detalhamento:** [features/openapi-connector/](features/openapi-connector/requirements.md) — trio
`requirements.md`/`plan.md`/`tasks.md`, validado por `feature-lint.mjs`.

**Sinal (ADR 0105):** gap P0 do BRIEFING; Felipe/Maiara não conseguem prestar suporte sem
mergulhar no código, e os clientes em migração dependem do Connector. O próprio SPEC já registrava
“clientes pedem” para a documentação OpenAPI.

**DoD:**
- OpenAPI 3.0 cobre o inventário `/connector/api/*` sem incluir `oauth/*` por acidente.
- Geração não realiza response calls, não consulta registros reais e não incorpora token, segredo ou PII.
- Payloads, responses e ordem dos middlewares legados permanecem byte/semanticamente compatíveis.
- Audiência e URL são decididas explicitamente antes de qualquer publicação.
- Felipe/Maiara completam uma jornada de suporte usando a documentação, com evidência de smoke.

### US-CONN-014 · [EPIC] Migrar a tela Conector (API) de Blade para Inertia (MWART)

> owner: [W] · priority: p2 · estimate: 2h · status: todo · type: epic
> blocked_by: —

**Implementado em:** _pendente_ — F1 PLAN entregue ([RUNBOOK-connector-index.md](RUNBOOK-connector-index.md));
nenhuma linha de runtime foi alterada.

**Escopo:** a ÚNICA tela viva do módulo — `clients/index.blade.php` (96 linhas), servida por
`ClientController@index` em `/connector/client`. Medido 2026-08-24: das 5 views referenciadas
pelos controllers, só essa existe em disco (ver RUNBOOK §10.3).

**Fora de escopo:** as 3 vistas novas do protótipo (`docs`, `saude`, `modulo`) — são capacidade
nova, não migração. Entram por US própria se [W] decidir.

**Sinal (ADR 0105):** o sidebar novo ([W] 2026-08) declara `connector` como item do
`SUPERADMIN_MENU` com 3 ghosts, o que pressupõe a tela em Inertia. O
[CHARTER](CHARTER-rest-api-external.md) já previa "futuro UI admin".

**DoD:** as 6 subtasks abaixo fechadas, nesta ordem.

### US-CONN-015 · F2 — Pest baseline do ClientController antes de tocar

> owner: [F] · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: US-CONN-014

**Implementado em:** _pendente_

**DoD:**
- ≥5 fixtures cobrindo `index` e `store`.
- Cross-tenant: client de outro `business_id` NÃO aparece (o filtro é `LEFT JOIN users` — `oauth_clients` não tem `business_id` próprio).
- Sem `superadmin` → 403.
- `APP_ENV=demo` → tabela some.
- Token Passport nunca real (Regra Tier 0 do módulo).

### US-CONN-016 · F2 — Dual render + feature flag + comando artisan

> owner: [F] · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: US-CONN-015

**Implementado em:** _pendente_

**DoD:**
- `ClientController@index` devolve `Inertia::render('Connector/Index')` só com header `X-Inertia` E flag ligada; senão Blade.
- Flag default OFF em `pos_settings`.
- `connector:enable-v2 <biz>` liga/desliga em menos de 30s.

### US-CONN-017 · F2 — Mapa de paridade campo-a-campo

> owner: [F] · priority: p2 · estimate: 30min · status: todo · type: story
> blocked_by: US-CONN-016

**Implementado em:** _pendente_

**DoD:**
- `index-parity.md` pelo [template](../_DesignSystem/PARITY-TEMPLATE.md), toda coluna do Blade com linha e severidade.
- Divergências propostas registradas como proposta, NÃO aplicadas: mascarar `secret` e confirmar antes de excluir (RUNBOOK §10.2 e §10.5) são decisão [W].

### US-CONN-018 · F3 — Tela Pages/Connector/Index.tsx

> owner: [F] · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: US-CONN-017

**Implementado em:** _pendente_

**DoD:**
- PT-01 Lista, 6 slots canônicos; ≤300 LOC; audit ≥70.
- Persistent layout AppShellV2; tokens semânticos (zero cor crua).
- Criação por modal/drawer — a rota `create` não é usada hoje (RUNBOOK §10.4).
- Estados `default`/`empty`/`demo`/`loading` conforme RUNBOOK §5.

### US-CONN-019 · F4 — QA hardening + smoke real

> owner: [W] · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: US-CONN-018

**Implementado em:** _pendente_

**DoD:**
- Audit ≥80, CRITICAL=0, WARN=0.
- Cada item de severidade `alta` da paridade com teste de comportamento citando o UC.
- Smoke real com status HTTP colado (R1) — narração não conta.

### US-CONN-020 · F5 — Cutover e sunset do Blade

> owner: [W] · priority: p3 · estimate: 30min · status: todo · type: story
> blocked_by: US-CONN-019

**Implementado em:** _pendente_

**DoD:**
- Flag ON — sem janela de aviso (admin de plataforma, sem cliente externo; MWART F0).
- 30 dias sem incidente antes de remover `clients/index.blade.php` e o dual render.
- Ao remover, reavaliar `na_justified.D4.c` no frontmatter: com tela Inertia viva a justificativa de N/A por "0 tsx" deixa de valer, e isso MUDA a nota do módulo — decisão [W].

## Pegadinhas catalogadas

- **3 formatos de body Delphi** (`array_tabelas`, `json_flat`, `pipe`) — todos suportados em `DelphiSyncService::detectBodyFormat()`
- **CNPJ resolution** prioriza `business_locations.cnpj` (filial), fallback `business.cnpj` (matriz)
- **HD compartilhado** entre N businesses (notebook de suporte remoto) — `update all` em `licenca_computador.hd`
- **Response `text/plain` literal** — NÃO mudar pra JSON nos endpoints legacy (Delphi parsa split(';'))

## Próximos passos potenciais

- Documentação OpenAPI 3.0 via `scribe` — formalizada na US-CONN-013; implementação pendente
- Rate limiting per-business em `/connector/api/*` (Hostinger shared = throttle 60/min default)
- WebHook outbound pra clientes Delphi notificarem mudanças (push em vez de poll)

## ADRs relacionadas

- [ADR 0021](../../decisions/0021-...-connector-delphi-restaurado.md) — Endpoints Delphi restaurados do 3.7 (se existir)
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — Runtime Hostinger ≠ CT 100
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1
