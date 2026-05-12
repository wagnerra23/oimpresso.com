---
page: /whatsapp/settings
component: resources/js/Pages/Whatsapp/Settings.tsx
owner: wagner
status: deprecated
last_validated: 2026-05-09
deprecated_at: 2026-05-12
deprecated_by: US-WA-067
parent_module: Whatsapp
parent_capterra: memory/requisitos/Whatsapp/CAPTERRA-FICHA.md
related_adrs: [0058, 0093, 0096, 0104, 0107, 0112, 0135]
tier: A
charter_version: 2
---

# Page Charter — `/whatsapp/settings` (DEPRECATED)

> **Status:** deprecated em 2026-05-12 (US-WA-067).
>
> Drivers Z-API/Meta/Baileys migraram para `Modules\Whatsapp\Channels` (ADR 0135). Esta tela virou stub temporário com apenas Templates HSM + toggle Bot Jana. Será removida em US-WA-070 — bloco Jana move para `/atendimento/canais/jana-templates` e `/whatsapp/settings` vira 301 redirect para `/atendimento/canais`.
>
> Charter mantido como histórico das invariantes que valiam ANTES da migração Canais. Charter v3 nasce com a nova rota.

---

---

## Mission

Permitir que o admin business **conecte o Whatsapp do negócio em ≤ 60 segundos** sem precisar entender infra (instance_id, daemon URL, API key, webhook URL, HMAC, Bearer). Estado-da-arte SaaS (padrão Z-API, Twilio, Wati): tenant só vê dados de negócio; infra fica server-side.

---

## Goals — Features (faz)

- **Wizard 3 provedores:** Z-API · Meta Cloud · Baileys custom (cards equivalentes; Baileys NÃO é "avançado/sprint", é first-class).
- **Onboarding Baileys = 1 telefone E.164 + 1 checkbox LGPD + 1 botão Conectar.** Backend gera `instance_id`, usa `daemon_url`/`api_key` globais, dispara connect, retorna QR.
- **Estado reativo via Centrifugo** (channel `whatsapp:business:{id}`) — sem refresh manual:
  - `disconnected` → botão "Conectar"
  - `connecting` → spinner "Provisionando instance..."
  - `qr_required` → QR PNG + countdown 60s + instruções (⋮ → Aparelhos conectados)
  - `connected` → ✅ telefone formatado + verified_name (se sincronizado) + profile pic + [Desconectar] [Trocar número]
  - `banned` → ❌ link pro [runbook troubleshoot-ban](memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md) + opções recuperação
- **Anti-duplicate:** UNIQUE (`business_id`, `baileys_phone_e164`) impede mesmo número conectar 2x no mesmo business.
- **LGPD persistido** (`lgpd_acknowledged_at` + `_by_user_id`) — Badge verde "✓ aceito em DD/MM/AAAA" depois.
- **Fallback Meta Cloud obrigatório** quando driver=zapi/baileys (gating duro `BusinessSettingsRequest`).
- **Multi-tenant Tier 0** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` em todas queries.

---

## Non-Goals — Features (NÃO faz)

- ❌ Expor `daemon_url`, `api_key`, `instance_id` ao tenant — são server secrets / auto-gerados.
- ❌ Cadastro Meta Cloud automático — exige Meta Business Manager, fluxo separado e manual.
- ❌ Sync histórico Whatsapp Web (sessions backup) — daemon Baileys gerencia isolado.
- ❌ Trocar driver primário sem aceitar termo LGPD novo (driver=baileys/zapi).
- ❌ Permitir `driver=evolution` ou `driver=whatsapp_web_js` — bloqueado em [config('whatsapp.forbidden_drivers')](Modules/Whatsapp/Config/config.php) ([ADR 0096 emenda 4](memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)).
- ❌ Múltiplas instances Baileys por business — 1 número = 1 sessão.
- ❌ Configurar webhook Baileys manualmente — daemon e Hostinger se conhecem via config global app.

---

## UX Targets

- **Onboarding tempo total:** mediana < 60s (1 input + Conectar + escanear QR).
- **First-paint:** p95 < 1000ms.
- **QR rotation:** novo QR gerado pelo daemon a cada ~60s; UI atualiza sem refresh.
- **Connect-to-paired latency:** p95 < 5s do scan ao webhook `connected` chegar.
- **0 erros JS console** com config válida.
- **Cabe em 1280px** sem scroll horizontal (cliente piloto ROTA LIVRE — auto-mem confirmou).
- **Badges semânticos:** verde=ok · amber=warning · red=error · slate=idle (consistente com [DriverHealthBadge atual](resources/js/Pages/Whatsapp/Settings.tsx)).

---

## UX Anti-patterns

- ❌ Mostrar `instance_id`, `daemon_url`, `api_key` em campos editáveis para o tenant.
- ❌ Polling pra ver se conectou — usar Centrifugo subscribe (ADR 0058).
- ❌ Modal sobre Sheet (anti-stack).
- ❌ Sumir com QR sem aviso quando expira — sempre countdown visível + auto-refresh.
- ❌ Botão "Conectar" disabled sem tooltip explicando por quê.
- ❌ Confirmação dupla pra ações low-risk (1 click = abrir QR).
- ❌ Confirmação SIMPLES pra ações destrutivas (disconnect/trocar número exigem confirm modal).
- ❌ Driver Baileys disabled/marcado "experimental" — é first-class igual aos outros (ADR 0096 emenda 4 autorizou).

---

## Automation Hooks

- **Centrifugo subscribe** `whatsapp:business:{id}` no `useEffect` da page (cleanup no unmount).
- **POST `whatsapp.settings.update`** — Inertia form. Backend dispara `BaileysConnectJob` quando driver=baileys + phone novo/mudou + LGPD ok.
- **`BaileysConnectJob`** — chama daemon `POST /instances/{auto_id}/connect`, daemon emite webhook `qr_updated` ou `connected`, controller publica em Centrifugo.
- **Rate limit:** 3 tentativas connect/business/dia (anti-abuse). Bypass: nenhum.
- **Audit log** (futuro Sprint 5): toda ação connect/disconnect grava em `whatsapp_audit_log` com `user_id` + `ip` + `ts`.

---

## Automation Anti-hooks

- ❌ Não chama daemon no render da page (custa requisição externa). Estado vem do DB + Centrifugo updates.
- ❌ Não persiste `daemon_url` ou `api_key` em `whatsapp_business_configs` — vão em `config/whatsapp.php` (env vars).
- ❌ Não acessa daemon de outro `business_id` (URL é per-business via `business_uuid`, mas validação é defensive duplicada).
- ❌ Não armazena tokens em plain text (Laravel `encrypted` cast em todos os secrets que sobraram).
- ❌ Não dispara connect em request síncrono (≤ 200ms responsividade Inertia) — sempre via Job na queue `whatsapp` (Horizon CT 100).
- ❌ Não retenta connect mais de 5x sem intervenção manual (anti-loop ban Meta).

---

## Métricas vivas (Pest GUARD)

- `WhatsappSettingsCharterTest::it_does_not_expose_daemon_url_in_props()` — `Inertia::render` não envia `baileys_daemon_url` nem `baileys_api_key` em config.
- `WhatsappSettingsCharterTest::it_isolates_by_business_id()` — outro business não vê config alheia.
- `WhatsappSettingsCharterTest::it_blocks_duplicate_phone_per_business()` — UNIQUE constraint dispara 422.
- `WhatsappSettingsCharterTest::it_dispatches_connect_job_on_phone_change()` — `BaileysConnectJob::dispatch` quando phone novo.
- `WhatsappSettingsCharterTest::it_does_not_call_daemon_on_render()` — `Http::fake` não captura nenhuma chamada no GET.
- `WhatsappSettingsCharterTest::it_publishes_qr_updated_to_centrifugo()` — webhook event `qr_updated` resulta em `CentrifugoPublisher::publish()` no canal correto.
- `WhatsappSettingsCharterTest::it_rate_limits_connect_attempts()` — 4ª tentativa connect/dia/business retorna 429.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | Wagner + Sonnet | Charter inicial. Regista UX simplificada Baileys (US-WA-022) — daemon URL/api key passam pra config app, tenant só cadastra telefone E.164. |
