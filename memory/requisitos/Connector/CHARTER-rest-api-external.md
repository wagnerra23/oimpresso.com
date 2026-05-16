# CHARTER — REST API Externa (`Modules/Connector`)

> **Contrato vivo** entre oimpresso e consumidores externos (Delphi WR Comercial, SaaS Woo, futuros).
> **Status:** `live` · **Owner:** [F] Felipe + [W] Wagner · **Última revisão:** 2026-05-16

## Mission

Expor capacidades transacionais e cadastrais do oimpresso a clientes externos via REST API com **contrato congelado**, garantindo que:

1. Clientes Delphi legacy (~6 OfficeImpresso) continuem operando sem regressão durante migração para o oimpresso novo
2. Apps mobile e integrações futuras (SaaS Woo, e-commerce) consumam dados multi-tenant scoped
3. Pipeline de auth, log e isolamento Tier 0 seja imutável (mudança = ADR + canary 7d)

## Goals

- ✅ **Auth Passport** (`auth:api` Bearer) em 100% das rotas `/connector/api/*`
- ✅ **business_id automático** via token (sem cliente externo precisar enviar)
- ✅ **3 formatos de payload Delphi** aceitos (array_tabelas / json_flat / pipe)
- ✅ **Response `text/plain`** legacy preservado pros endpoints Delphi (`S;msg`/`N;motivo`)
- ✅ **Middleware `log.delphi`** ANTES de `auth:api` (captura 401 pra debug)
- 🟡 **Documentação OpenAPI 3.0** (P0 — pendente scribe)
- 🟡 **Rate limiting per-business** (P1 — pendente)

## Non-Goals (NÃO faremos aqui)

- ❌ **Webhook outbound** — push de mudanças (cliente externo polla — feature separada futura, ADR específico)
- ❌ **GraphQL** — REST é o contrato; GraphQL viraria módulo separado se ROI provado
- ❌ **Streaming/WebSocket** — Centrifugo cuida disso ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)); Connector é HTTP request/response
- ❌ **MCP tools exposed em Hostinger** — Hostinger não suporta MCP ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md))
- ❌ **Modificar payload/response legacy sem ADR** — Delphi parsa literal, mudança quebra produção dos 6 clientes

## UX targets (consumidor externo)

- **Latência p95 < 800ms** em endpoints transacionais (`sell`, `processa-dados-cliente`)
- **401 fail-secure** em <50ms (auth pipeline curto)
- **Rate limit 60/min default** por token (compatível Hostinger shared)
- **Erros JSON `{message, errors}`** padrão Laravel (sem stack trace)
- **Content negotiation** — Aceita `application/json` (novo) e `text/plain` (legacy Delphi)

## Automation hooks

- `php artisan officeimpresso:inspect-api` — inspeciona últimos request_headers/body_preview de cada endpoint
- `php artisan route:list --path=connector/api` — lista rotas + middleware (gate ordem `log.delphi` antes `auth:api`)
- `php artisan jana:health-check` — inclui checks que poderiam tocar `connector` (drift de schema, vazamento PII em log)
- Pest CI: `Modules/Connector/Tests/Feature/*Test.php` + `tests/Feature/Connector/DelphiOImpressoContractTest.php`

## Anti-hooks (sinais de violação do charter)

- 🚨 PR que muda response Delphi de `text/plain` pra `application/json` sem ADR
- 🚨 PR que move/renomeia controller `Modules\Connector\Http\Controllers\Api\*` (URL fixa em token Delphi)
- 🚨 PR que remove `log.delphi` do stack ou inverte ordem (`auth:api` antes)
- 🚨 PR que adiciona endpoint sem `auth:api` (anônimo público)
- 🚨 Query Eloquent sem `business_id` scope em controller Connector
- 🚨 Testes Connector usando `business_id=4` (cliente ROTA LIVRE) — viola ADR 0101

## Contratos atômicos por endpoint

### `/connector/api/processa-dados-cliente` (Delphi G1 legacy)
- **Request:** `POST` JSON array `[{NOME_TABELA: 'EMPRESA', ...}, {NOME_TABELA: 'LICENCIAMENTO', HD: '...'}]`
- **Response:** `text/plain` literal `S;Cliente e equipamento liberados` ou `N;<motivo>`
- **Imutável** — Delphi parsa split(';'), JSON quebra cliente

### `/connector/api/oimpresso/registrar` (WR Comercial G2)
- **Request:** `POST` JSON flat `{cnpj, serial_hd, hostname, versao_exe}`
- **Response:** `application/json` `{autorizado: 'S'|'N', licenca_id, dias_restantes, data_expiracao}`
- **Imutável** — Services.OImpresso.Registro.pas faz `Resp.GetValue<string>('autorizado', 'N') = 'S'`

### `/connector/api/check-update` (Services.RegistroSistema.pas)
- **Request:** `POST` `text/plain` `CNPJ;VersaoAtual`
- **Response:** `text/plain` `VersaoNova;VersaoMinObrigatoria` ou `N;VersaoMinObrigatoria`
- **Source da verdade:** `business.versao_disponivel` + `business.versao_obrigatoria` (superadmin)

### REST CRUD UltimatePOS (`/contactapi`, `/product`, `/sell`, etc)
- **Request/Response:** JSON Laravel API Resource padrão
- **Scope:** automático via token (`business_id` do user)
- **Paginação:** `?per_page=20&page=2` (default 20)

## Mudança no charter

1. Abre PR editando este arquivo
2. Cita ADR mãe (criar se mudança estrutural)
3. Wagner aprova explicitamente
4. Se mudança quebra contrato Delphi: **canary 7d + aviso prévio 30d aos clientes ativos**

## Skills relacionadas

`charter-first` · `commit-discipline` · `multi-tenant-patterns` · `preflight-modulo` · `mwart-quality` (futuro UI admin)
