---
title: "Plano Gap 4 — SMS provider real out-of-band pra PIN aprovacao"
date: "2026-05-26"
type: gap-plan
status: draft
gap_id: 4
modulo: Whatsapp + OficinaAuto
us_relacionada: US-OFICINA-014-bis (extensao Wave 4 PIN out-of-band real)
cliente: Martinho biz=164
esforco_estimado: "6-8h IA-pair (fator 10x ADR 0106) + 2h smoke real"
roi: medio-robustez
bloqueia_demo: nao (delay 60s WhatsApp aceita charter)
---

# Plano Gap 4 — SMS provider real out-of-band PIN

## Contexto

Wave 4 (PR #1627) mergeou WhatsApp PIN aprovacao com **mitigacao temporal in-band**: msg 1 link + msg 2 PIN com delay 60s ambos via WhatsApp. Comentario `EnviarLinkAprovacaoWhatsappJob.php` linha 33-35:

> "Ideal seria SMS pro PIN (canal real out-of-band), mas ate provider SMS chegar, delay temporal no mesmo canal mitiga"

Anti-hook charter exige PIN em canal SEPARADO do link pra evitar:
- Atacante intercepta WhatsApp (SIM swap, sessao roubada, screen-share inadvertida) e ja tem link+PIN no mesmo lugar
- LGPD: PIN out-of-band reduz superficie de ataque (defesa em profundidade)

## Research estado-da-arte 2026

3 mercados de provider SMS Brasil:

1. **Twilio** — global, $0.0617/SMS BR ([Twilio pricing BR](https://www.twilio.com/en-us/sms/pricing/br)). Fallback nativo "Verify API" SMS → voice → email. Bill USD. Latencia 2-5s.
2. **Zenvia** — CPaaS brasileiro publico, R$ [redacted Tier 0]-0.12/SMS, Pix billing, suporte PT-BR. Lider LatAm.
3. **AWS SNS** — barato ($0.03/SMS BR), sem features (sem template management, sem retry/fallback nativo), bill USD. Bom pra commodity.

**Multi-provider fallback 2026 best-practice** ([Twilio Verify fallback docs](https://www.twilio.com/docs/verify/fallback-scenarios), [Prelude](https://prelude.so/blog/twilio-competitors)):
- "RCS → SMS → Voice" hierarquia
- Detectar carrier sem cobertura, fallback automatico
- A/B routing por taxa entrega regional

**Para o caso oimpresso (PIN 4d, ~30 mensagens/mes biz=164 piloto):** custo desprezivel R$ [redacted Tier 0]-5/mes. **Decisao key NAO eh provider, eh ARQUITETURA: single-driver agora vs multi-driver-strategy ja preparado pra fallback.**

## Inventario oimpresso

`Modules/Whatsapp` JA tem **driver strategy pattern em uso** — pasta `Services/Drivers/`:
- `DriverInterface.php` (assumido)
- `MetaCloudDriver.php` (oficial Meta — usado hoje)
- Drivers Zapi/Baileys mencionados em comentario (linha 21-23 MetaCloudDriver)

**Decisao arquitetural recomendada:** espelhar pattern Whatsapp drivers em **novo modulo `Modules/Sms`** (ou pasta `app/Services/Sms/Drivers/`). Strategy interface com 3 implementacoes preparadas (Twilio + Zenvia + AwsSns) + 1 active configurada per-business via `business_settings.sms_driver`.

**Cuidado risco scope creep:** Wagner pediu "SMS provider real" — NAO transformar em mega-feature multi-driver V0. **Single-driver Twilio (cobertura global e doc) + interface preparada pra fallback futuro** eh o ROI maximo. Multi-driver completo eh V2 quando volume justificar.

## Arquivos a tocar

| Arquivo | Operacao | Notas |
|---|---|---|
| `app/Services/Sms/Contracts/SmsDriverInterface.php` | NOVO — interface `send(string $to, string $message, int $businessId): SmsSendResult` | Strategy pattern |
| `app/Services/Sms/Drivers/TwilioDriver.php` | NOVO — implementacao via `twilio/sdk` composer package | OtelHelper::span pattern (espelha MetaCloudDriver) |
| `app/Services/Sms/Drivers/LogOnlyDriver.php` | NOVO — driver fake pra testes/dev | Loga em storage/logs/sms.log, retorna success=true |
| `app/Services/Sms/SmsSendResult.php` | NOVO — DTO `{success: bool, provider_id: string, error: ?string, latency_ms: int}` | Espelha WhatsappSendResult |
| `app/Services/Sms/SmsService.php` | NOVO — facade publica `SmsService::send($to, $message, $businessId)` | Resolve driver via config(`sms.default_driver`) ou business_settings override |
| `config/sms.php` | NOVO — config provider keys + default driver | TWILIO_SID/TOKEN/FROM em .env |
| `database/migrations/2026_05_27_*_add_sms_settings_to_business.php` | NOVO — coluna `sms_provider` enum nullable + `sms_from_number` nullable | aditivo |
| `Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php` | EDIT — branch SMS quando `config('sms.enabled') === true` | Msg 1 WhatsApp link + Msg 2 SMS PIN (real out-of-band, sem delay 60s) |
| `Modules/OficinaAuto/Services/AprovacaoOsService.php` | EDIT — metodo helper `dispatchPinSms(string $phone, string $pin, int $businessId)` | Wrapper SmsService |
| `tests/Feature/Sms/SmsServiceTest.php` | NOVO — 5 Pest specs | LogOnlyDriver send OK + Twilio mock SDK + cross-tenant business_id passado + retry on 5xx + LGPD redaction PIN em logs |
| `Modules/OficinaAuto/Tests/Feature/EnviarLinkAprovacaoWhatsappJobTest.php` | EDIT — adicionar 2 specs branch SMS | Quando `config('sms.enabled')` Msg 2 vai via SmsService nao WhatsApp |
| `composer.json` | EDIT — `require: twilio/sdk: ^7.0` | Twilio SDK |
| `app/Console/Commands/SmsHealthCheckCommand.php` | NOVO — `php artisan sms:health-check` ping Twilio API valida credentials | Daily schedule |
| `Modules/OficinaAuto/SCOPE.md` | EDIT — declarar deps SmsService | scope-guard |

## Restricoes Tier 0 deste gap

1. **Multi-tenant ADR 0093** — `SmsService::send` recebe `businessId` no constructor params. Validation: business_settings checks provider config existir antes de send (fallback LogOnlyDriver se faltar). NUNCA `session()` em Job/Service.
2. **LGPD** — PIN NAO pode aparecer em logs claros. `OtelHelper::span` redact PIN automatic (similar Whatsapp). Log do SMS body: substituir PIN por `****`.
3. **Hostinger != CT 100 (ADR 0062)** — Twilio SDK funciona em Hostinger (HTTP outbound permitido). Sem dependencia de Octane.
4. **ZERO segredos no git (ADR 0061)** — `TWILIO_AUTH_TOKEN` em `.env` server + Vaultwarden registro. Nunca commit.
5. **Anti-hook charter** — Job continua disparando APENAS quando Observer detecta status='orcamento'. Sem disparo automatico em outras transicoes.
6. **Cost guard** — `business_settings.sms_quota_monthly` limite (default 100/mes biz=164). Exceed bloqueia + alerta Wagner. Defesa contra runaway loop.
7. **Twilio Verify API vs Programmable SMS** — usar **Programmable SMS** (envio direto, sem ciclo de vida verification). Verify e overkill aqui.

## Mini-comparativo atual → target

| Aspecto | Hoje (WhatsApp delay 60s) | Target Gap 4 (SMS real) |
|---|---|---|
| Canal Msg 1 (link) | WhatsApp | WhatsApp (mantem) |
| Canal Msg 2 (PIN) | WhatsApp +60s delay | SMS imediato |
| Defesa em profundidade | temporal (fraca) | canal separado (forte) |
| Custo | R$ [redacted Tier 0] (WhatsApp Meta free tier) | R$ [redacted Tier 0]-0.12/PIN biz=164 (~R$ [redacted Tier 0]-5/mes) |
| Falha provider | retry mesma fila WhatsApp | fallback WhatsApp +60s se SMS fail (graceful degrade) |
| Latencia | 60s+ | 2-5s SMS |
| Risco bypass | SIM swap WhatsApp leva tudo | precisa comprometer 2 canais distintos |
| Provider lock-in | Meta only | Strategy: Twilio default, troca via config |
| LGPD audit | log WhatsApp | log SMS + redaction PIN |

## Esforco estimado

- composer require twilio/sdk + config/sms.php: 30min
- SmsDriverInterface + TwilioDriver + LogOnlyDriver + SmsSendResult: 1.5h
- SmsService facade + cost guard: 1h
- Migration aditiva business sms_settings: 30min
- EnviarLinkAprovacaoWhatsappJob branch SMS + AprovacaoOsService helper: 1h
- 7 Pest specs (SmsService + Job extension): 1.5h
- SmsHealthCheckCommand + schedule: 30min
- ENV docs + Vaultwarden secret entry: 30min
- **Total: 6-8h IA-pair** (fator 10x ADR 0106) + 2h smoke real Wagner (Twilio account setup + numero remetente + envio teste)

## Smoke criteria

- [ ] biz=164 Martinho: criar OS, transicionar status='orcamento', WhatsApp link chega + SMS PIN chega celular ~3-5s depois
- [ ] Twilio dashboard: 1 SMS enviado, status delivered
- [ ] Tinker biz=1: tentar send com biz_id=164 deve falhar guard
- [ ] Log audit: PIN aparece como `****` no log SMS, full PIN em VaultEncryptionService
- [ ] Cost guard: simular 100 PINs num dia, 101a request falha "sms_quota_exceeded"
- [ ] Fallback teste: setar TWILIO_AUTH_TOKEN invalido, job tenta SMS, fail, fallback WhatsApp +60s aciona (graceful degrade)
- [ ] Cross-tenant: SMS provider config biz=164 NAO leak pra biz=1

## Dependencias

- **PR independente** — nao depende de outros gaps
- Wagner precisa criar conta Twilio + comprar numero BR remetente (~$1-5/mes) + colocar token Vaultwarden ANTES do smoke real
- Pre-req: ler `Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php` (pattern mae a IMITAR)

## DRAFT task pra Wagner copy-paste

```yaml
title: "Gap 4 — SMS provider Twilio out-of-band PIN aprovacao"
module: Sms (novo) + Whatsapp + OficinaAuto
us: US-OFICINA-014-bis
priority: medium
estimated_hours: 8
owner_proposal: claude-paralelo
description: |
  Implementar canal SMS real para PIN aprovacao OS, substituindo mitigacao
  temporal in-band (WhatsApp +60s delay) por canal genuinamente separado.

  Single-driver Twilio V0 + strategy interface preparada pra fallback futuro
  (Zenvia/AwsSns). Cost guard quota per-business. LGPD redaction PIN logs.

  NAO eh mega-feature multi-driver — single-driver suficiente piloto Martinho.

  Pre-req Wagner:
  - Conta Twilio criada
  - Numero remetente BR comprado
  - TWILIO_AUTH_TOKEN no Vaultwarden + .env CT 100 + Hostinger

  Refs: ADR 0093, ADR 0094 §SoC, ADR 0106, padrao MetaCloudDriver
acceptance_criteria:
  - "biz=164 OS orcamento dispara WhatsApp link + SMS PIN, ambos chegam <5s"
  - "Twilio dashboard mostra SMS delivered"
  - "Fallback teste: token invalido, Job degrade pra WhatsApp +60s"
  - "Cross-tenant: provider biz=164 nao leak biz=1"
  - "Pest 7/7 verde local + SmsHealthCheckCommand passa"
```

## Refs

- [Twilio SMS Brazil pricing](https://www.twilio.com/en-us/sms/pricing/br)
- [Twilio Verify fallback channels](https://www.twilio.com/docs/verify/fallback-scenarios)
- [Prelude Twilio competitors 2026](https://prelude.so/blog/twilio-competitors)
- [MojoAuth SMS OTP cost analysis 2026](https://mojoauth.com/blog/sms-otp-cost-ecommerce-passkeys-cut-80-percent)
- `Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php` (pattern mae)
- `Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php` (lugar de wiring)
- [ADR 0093 Multi-tenant](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR 0094 SoC brutal (modulo Sms desacoplado)
