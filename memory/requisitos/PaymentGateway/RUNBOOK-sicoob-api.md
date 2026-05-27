---
slug: runbook-sicoob-api
title: "RUNBOOK — Sicoob API v3 driver (US-FIN-044)"
type: runbook
authority: canonical
lifecycle: ativo
status: ativo
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0170-bancos-nativos-top5-drivers-separados
related_us:
  - US-FIN-044
parent_module: PaymentGateway
persona: Wagner (superadmin) + Kamila (Admin#164 Martinho Caçambas biz=164)
session_date: '2026-05-27'
owner: W
last_validated: '2026-05-27'
---

# RUNBOOK · Sicoob API v3 driver (US-FIN-044)

> Driver REST nativo do Sicoob (Sistema Cooperativista de Crédito do Brasil, FEBRABAN 756) — OAuth2 client_credentials + mTLS PKCS12 + webhook real-time. Caminho alternativo ao `SicoobCnabDriver` (arquivo CNAB 240) pra clientes que querem baixa em tempo real sem importar retorno.

## Mission

Cliente PJ cooperativada Sicoob (ex: Martinho Caçambas @ biz=164) emite boleto registrado direto pela API REST, recebe notificação real-time quando cliente paga via webhook HMAC, sem precisar baixar/importar arquivo CNAB 240 manualmente 2× por dia.

## Sinal qualificado (ADR 0105)

Origem: Kamila (Admin#164, operadora do Martinho Caçambas biz=164) perguntou Wagner em 2026-05-27 "Sicoob quer API". Wagner aprovou implementação. Martinho Caçambas (locação/manutenção caçambas, região Tubarão/SC, migrado em 2026-05-14 de WR2 Firebird legacy, competidor HiSoft) é cliente pagante ativo — usa Sicoob como banco operacional. NÃO é hipótese de feature wish — é cliente real reportando necessidade. **Atenção:** ROTA LIVRE (biz=4 Larissa vestuário Gravatal/SC) NÃO usa Sicoob — não confundir os 2 clientes.

## Roadmap PRs (6 fatias merged sequencial)

| PR | Escopo | Status |
|---|---|---|
| #1718 | Migration `requires_mtls`/`mtls_pfx_path` + skeleton SicoobApiDriver + registry | ✅ Merged |
| #1720 | OAuth2 client_credentials + emitirBoleto + cancelar + consultar + healthCheck | ✅ Merged |
| #1722 | mTLS handshake real (.pfx + senha cifrada via Laravel Crypt) | ✅ Merged |
| #1724 | SicoobApiWebhookController + HMAC `x-sicoob-signature` validation | ✅ Merged |
| #1725 | Wizard UI step Sicoob no SheetNovoGateway + .pfx upload backend | ⏳ Em CI |
| #1726 | RUNBOOK + Charter + Pest cross-tenant ampliado (este) | ⏳ Este PR |

## URLs Sicoob v3

| Componente | Sandbox | Produção |
|---|---|---|
| OAuth2 token | `https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token` | mesma URL |
| API REST base | `https://sandbox.sicoob.com.br/sicoob/sandbox/cobranca-bancaria/v3` | `https://api.sicoob.com.br/cobranca-bancaria/v3` |
| Developer Portal | `https://developers.sicoob.com.br` | — |

Scopes pra cobrança: `boletos_inclusao boletos_consulta boletos_alteracao`.
Webhook scopes (PR4+): `webhooks_inclusao webhooks_consulta`.

## Models + Schema

- `Modules\PaymentGateway\Models\PaymentGatewayCredential` — tabela `payment_gateway_credentials`
  - `gateway_key='sicoob_api'` (enum ampliado em [migration PR1](../../../Modules/PaymentGateway/Database/Migrations/2026_05_27_120000_add_sicoob_api_to_payment_gateway_credentials.php))
  - `requires_mtls=true` (novo bool desde PR1)
  - `mtls_pfx_path='sicoob/{business_id}.pfx'` (novo string desde PR1, relativo a `storage/app/private/`)
  - `config_json` (encrypted-at-rest AES-256-CBC + MAC desde Onda 2):
    - `client_id` (Sicoob Developer Portal)
    - `client_secret`
    - `numero_cliente` (convênio / código cedente Sicoob)
    - `codigo_modalidade` (carteira — 1 Simples / 3 Caucionada)
    - `numero_conta` (conta corrente cooperativada)
    - `especie_documento` (default 'DM')
    - `mtls_pfx_password_encrypted` (Crypt::encryptString da senha do .pfx)
    - `webhook_secret` (HMAC `x-sicoob-signature`)

## Backend canônico

### Driver
`Modules\PaymentGateway\Services\Drivers\SicoobApiDriver`

| Método | Endpoint Sicoob | Comportamento |
|---|---|---|
| `emitirBoleto()` | POST `/boletos` (batch de 1) | retorna `nossoNumero` + `linhaDigitavel` + `codigoBarras` |
| `cancelar()` | PATCH `/boletos/baixa` | `codigoBaixa=1` (comandar baixa) |
| `consultar()` | GET `/boletos?numeroCliente=…&codigoModalidade=…&nossoNumero=…` | mapeia `situacaoBoleto` → status canon |
| `healthCheck()` | POST `/openid-connect/token` (force refresh) | mede latência, classifica ok/degraded/down |
| `processWebhook()` | parse payload | extrai nossoNumero, normaliza shape |
| `emitirPix*()` / `cobrarCartao()` / `refund()` | — | `DriverNotSupportedException` (Sicoob CNAB ou outro driver) |

### Webhook Controller
`Modules\PaymentGateway\Http\Controllers\Webhooks\SicoobApiWebhookController`

- Rota: `POST /paymentgateway/webhooks/sicoob-api/{businessId}` (SEM auth — chamado externamente)
- Pipeline canon (espelha `InterWebhookController`):
  1. Resolve credential via `business_id` + `gateway_key=sicoob_api` (`withoutGlobalScopes` — webhook não tem session)
  2. Valida HMAC `x-sicoob-signature` ANTES de qualquer parse/DB-write (US-PG-002 / VULN SEC P0-#2)
  3. Extrai `eventId` determinístico (id → eventId → nossoNumero → md5 payload)
  4. Delega `WebhookProcessor::handle()` — idempotência DB-level via UNIQUE `(business_id, gateway_key, gateway_event_id)`

### mTLS handshake
- `SicoobApiDriver::mtlsOptions(PaymentGatewayCredential $cred): array`
  - `requires_mtls=false` → `[]` (sandbox sem cert)
  - `requires_mtls=true` + `.pfx` válido + senha decifrável → `['cert' => [abs_path, plain_password]]`
  - Guzzle 7 propaga pra curl como CURLOPT_SSLCERT + SSLCERTPASSWD + SSLCERTTYPE=P12

### Cache OAuth
- Key: `sicoob_api:token:{business_id}:{ambiente}:{client_id_hash}` (hash sha256 truncado 12 chars)
- TTL: 3500s (margem antes do Sicoob expirar em 3600s)
- Driver: Laravel Cache (Redis-safe, multi-process) — não in-memory

## Frontend canônico (PR5)

- `resources/js/Pages/Settings/PaymentGateways/_components/SheetNovoGateway.tsx`
  - Step 2 Sicoob form: 7 campos (client_id, client_secret, numero_cliente, codigo_modalidade select 1/3, numero_conta, .pfx upload, pfx_password, webhook_secret)
- `resources/js/Pages/Financeiro/Cobranca/_lib/cobranca-shared.ts`
  - `'sicoob_api'` em `GatewayKey` type
  - Entry em `DRIVERS` (cor emerald-700, pricing, requirements, credentialSource → developers.sicoob.com.br)

## Multi-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))

- `.pfx` armazenado em `storage/app/private/sicoob/{business_id}.pfx` — path por tenant
- Cache OAuth key keyed por `business_id` — token NUNCA compartilhado
- Webhook valida HMAC com `webhook_secret` da credential do `business_id` da rota — secret de biz=4 NÃO valida biz=99
- Pest cross-tenant amplo no PR6 valida explicitamente:
  - `.pfx` biz=4 não vaza pra biz=99 (mtls_pfx_path isolado)
  - OAuth token biz=4 não autentica biz=99 (cache key diferente)
  - Webhook secret biz=4 não valida biz=99 (signature_invalid → 401)
  - Emissão boleto biz=4 usa `numero_cliente` biz=4, não biz=99

## Operação — Wagner cadastra Sicoob

### 1. Liberação Sicoob (pré-requisito humano Kamila)
Conforme checklist [memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md](../../sessions/2026-05-27-sicoob-api-credenciais-pedido.md):
- Acesso ao Sicoob Developer Portal liberado pelo gerente
- Aplicativo criado no portal com scopes `boletos_inclusao boletos_consulta boletos_alteracao`
- client_id + client_secret salvos
- `.pfx` baixado + senha em mãos
- Convênio (Código Cedente) anotado

### 2. Wagner cadastra via wizard
`/settings/payment-gateways` → "Novo gateway" → step 1 escolher "Sicoob API" → step 2:
- client_id + client_secret (collados do portal)
- Convênio + Carteira (1 = Simples padrão) + Conta corrente
- Upload `.pfx` + senha
- Webhook secret (HMAC arbitrário gerado pra Sicoob configurar — `openssl rand -hex 32`)
- Step 3 → vincular conta destino + ambiente sandbox (testa) ou production

### 3. Wagner configura webhook no portal Sicoob
- URL: `https://oimpresso.com/paymentgateway/webhooks/sicoob-api/{business_id}`
- Eventos: `cobranca.liquidada`, `cobranca.vencida`, `cobranca.cancelada`
- Secret HMAC: o `webhook_secret` cadastrado no step 2

### 4. Wagner roda health check
- Botão "Testar conexão" → driver força OAuth refresh → mede latência → grava `health_status`/`health_checked_at`

### 5. Emissão real
Wagner cria cobrança via `/financeiro/cobranca` ou via Sale flow → `PaymentGatewayService::for(account)->emitirBoleto(input)` → backend monta payload v3 + manda pro Sicoob → grava `Cobranca` com `nossoNumero`.

### 6. Baixa real-time
Sicoob notifica `POST /paymentgateway/webhooks/sicoob-api/{biz}` quando cliente paga → controller valida HMAC → grava `GatewayWebhookEvent` → dispatcher Onda 4d (futura) emite evento `CobrancaPaga`.

## Troubleshooting

### `CredentialMisconfiguredException: gateway_key='X' não bate`
Credential cadastrada com gateway_key diferente. Re-cadastrar como `sicoob_api`.

### `CredentialMisconfiguredException: numero_cliente (convênio/código cedente)`
Convênio Sicoob não foi cadastrado. Buscar no extrato/internet banking ou pedir gerente.

### `CredentialMisconfiguredException: mtls_pfx_path está vazio`
.pfx não foi feito upload. Re-upload pelo wizard.

### `CredentialMisconfiguredException: .pfx não encontrado em 'storage/...'`
Storage foi limpo OU diretório `storage/app/private/sicoob/` não existe. Re-upload pelo wizard recria.

### `CredentialMisconfiguredException: APP_KEY do Laravel mudou`
APP_KEY rotacionado e .pfx password ficou inválido. Re-cadastrar senha do .pfx no wizard.

### `GatewayUnavailableException: Sicoob API falhou (401)`
Token expirado ou inválido. Cache OAuth pode estar com token corrompido — limpar via `php artisan cache:forget sicoob_api:token:{biz}:{amb}:{hash}`.

### `GatewayUnavailableException: Sicoob API falhou (404 Not Found em /boletos/{nn})`
nossoNumero não existe nesse convênio. Geralmente acontece se tentou consultar boleto emitido em ambiente diferente (sandbox vs prod) ou em convênio diferente.

### Webhook 401 signature_invalid
- `webhook_secret` no `config_json` não bate com o usado pelo Sicoob ao calcular `x-sicoob-signature`
- Sicoob pode usar header diferente (`signature`, `X-Sicoob-Signature`, etc) — pendente confirmação com gerente

### Webhook 404 credential_not_found
- `business_id` na URL não tem credencial `sicoob_api` ativa
- Ou credential `ativo=false`

## Audit / Compliance

- LGPD: `webhook_secret` + `client_secret` + `mtls_pfx_password_encrypted` ficam em `config_json` que é encrypted-at-rest (AES-256-CBC + MAC, US-PG-001). `activity_log` exclui `config_json` (`logOnly` em PaymentGatewayCredential model).
- PCI: `.pfx` em `storage/app/private/sicoob/` com chmod 0600 (Linux). Path fora de disco público.
- Idempotência webhook: UNIQUE `(business_id, gateway_key, gateway_event_id)` impede dupla baixa.

## Próximos passos (post-merge)

1. **Wagner manda checklist pra Kamila** ([memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md](../../sessions/2026-05-27-sicoob-api-credenciais-pedido.md))
2. **Lea/Kamila junta credenciais sandbox** (~2-7 dias úteis, depende cooperativa)
3. **Wagner cadastra biz=4 no wizard** com sandbox
4. **Smoke E2E real**: emite boleto fake → Sicoob retorna nossoNumero → simula pagamento sandbox → webhook chega → GatewayWebhookEvent gravado
5. **Wagner aprova migração biz=4 sicoob_cnab → sicoob_api** (manter sicoob_cnab como fallback durante 30 dias)
6. **Health check daily cron** (futuro) — monitora drift Sicoob API uptime

## Referências

- [memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md](../../sessions/2026-05-27-sicoob-api-credenciais-pedido.md) — checklist Kamila
- [ADR 0170 §4f.sicoob_api](../../decisions/0170-bancos-nativos-top5-drivers-separados.md) — drivers separados
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [Sicoob Developer Portal](https://developers.sicoob.com.br) — docs oficiais
- PRs: #1718 #1720 #1722 #1724 #1725 #1726
