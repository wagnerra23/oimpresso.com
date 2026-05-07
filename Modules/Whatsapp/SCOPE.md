# SCOPE — Modules/Whatsapp/

Resumo executivo do escopo deste módulo. Documento curto pra dev novo entender em 2 minutos.

## Decisão arquitetural mãe

[ADR 0096](../../memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — **Z-API/Baileys default + Meta Cloud fallback obrigatório (Evolution PROIBIDO Tier 0)**.

## O que este módulo faz

Whatsapp transacional unificado pro setor comunicação visual (gráficas/printers): status OS Repair, boleto+NFe RecurringBilling, lembrete Financeiro, acompanhamento ConsultaOs, bot conversacional Jana com HITL.

## Drivers (Lote 2b)

- **`ZapiDriver`** (default) — Z-API SaaS BR via Baileys. Onboarding 5 min. Risco ban Meta MUITO ALTO.
- **`MetaCloudDriver`** (fallback obrigatório) — Oficial Meta. Onboarding 1-3 dias. Free 1k conv/mês.
- **`NullDriver`** (dev/CI) — implementado neste Lote 2a.
- **`EvolutionDriver`** — ❌ PROIBIDO Tier 0. Não vai ser implementado.

## Lotes do Sprint 1

| Lote | Conteúdo | Status |
|---|---|---|
| **2a** | Scaffold (este PR) — module.json + Providers + DataController + InstallController + Routes/web.php (3 rotas Install + 3 admin placeholder) + topnav + lang + Driver interface + NullDriver | ✅ scaffold |
| 2b | Migrations 4 tabelas (whatsapp_business_configs/conversations/messages/templates) + Eloquent Models com global scope business_id + ZapiDriver + MetaCloudDriver + DriverFactory + Pest com Http::fake() | pendente |
| 2c | SendWhatsappMessageJob + Listener Repair NotifyRepairCustomer + FormRequest wizard 2 passos + Settings/Templates/Conversations Inertia pages + Webhook controllers (Zapi + Meta) | pendente |

Sprint 2 (após Sprint 1): Inbox UI Cockpit + Health Check + Fallback automático + Bot Jana HITL.

## Não-escopo

- Marketing em massa (UX ruim Whatsapp + risco ban) → outras tools (RD Station, Active Campaign).
- Whatsapp pessoal (não-Business) — só Whatsapp Business Cloud API.
- Voice (chamadas Whatsapp) — beta Meta.
- Evolution API self-host — PROIBIDO Tier 0 (ADR 0096 emenda 3).

## Padrões obrigatórios

- **Multi-tenant Tier 0** ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` em todas Models; jobs com `$businessId` no constructor; webhook URL com `business_uuid` no path.
- **Hostinger ≠ CT 100** ([ADR 0062](../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)) — UI + webhook receiver no Hostinger; Job consumer no CT 100 Horizon.
- **Fallback gating duro** — FormRequest rejeita 422 se `driver=zapi` sem `meta_*` cadastrado.
- **Termo LGPD obrigatório** quando `driver=zapi` (registrado em `lgpd_acknowledged_at`).
- **PII redacted** em logs via `App\Support\PiiRedactor` (skill `commit-discipline`).

## Documentos

- [SPEC.md](../../memory/requisitos/Whatsapp/SPEC.md) — US + regras Gherkin
- [ARCHITECTURE.md](../../memory/requisitos/Whatsapp/ARCHITECTURE.md) — schema DB + fluxos + jobs + middlewares
- [CAPTERRA-FICHA.md](../../memory/requisitos/Whatsapp/CAPTERRA-FICHA.md) — concorrentes + pricing + por que Z-API default
- [README.md](../../memory/requisitos/Whatsapp/README.md) — pitch + revenue + roadmap 3 sprints
