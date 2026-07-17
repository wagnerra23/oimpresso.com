---
slug: 0111-emenda-0096-bypass-meta-fallback-per-business
number: 111
title: "Emenda 5 ao ADR 0096 — Bypass Meta-fallback per-business via env (piloto biz=1 smoke Z-API)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-08"
accepted_at: 2026-05-08
accepted_by: wagner
module: whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, zapi, gating-tier-0, bypass-piloto, smoke, biz-1]
related_adrs: [0094, 0096, 0093]
parent_charter: null
parent_adr: 0096
supersedes: []
supersedes_partially: []
superseded_by: []
authors: [wagner, opus-4.7]
pii: false
review_triggers:
  - Meta Cloud aprovada pra biz=1 (volta a aplicar gate Tier 0)
  - Algum ban Meta no smoke (forçar migração imediata pro fallback)
  - 7 dias sem aprovação Meta passados (escalada Wagner)
  - Outro business pedir bypass (aplicar mesmo princípio com nova ADR)
---

# ADR 0111 — Emenda 5 ao 0096: Bypass Meta-fallback per-business (piloto biz=1)

## Contexto

[ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md) estabelece gate Tier 0 IRREVOGÁVEL: `driver=zapi` ou `driver=baileys` exige Meta Cloud cadastrado como `fallback_driver` (gating duro no [BusinessSettingsRequest:99-119](../../Modules/Whatsapp/Http/Requests/BusinessSettingsRequest.php:99)). Sem `meta_phone_number_id` + `meta_access_token` + `meta_app_secret` preenchidos, FormRequest retorna 422.

Realidade operacional 2026-05-08: Wagner quer validar pipeline Z-API ponta-a-ponta no `business_id=1` (WR2 SC) usando credenciais reais antes de submeter Meta Business Manager pra aprovação. Meta Cloud onboarding leva 1-3 dias úteis (verificação número + HSM aprovação por template). Forçar fluxo "primeiro Meta, depois Z-API" impede smoke do canal default Sprint 1.

Princípio Constituição V2 (ADR 0094) #4 — "Loop fechado por métrica" — exige validar emit/receive Z-API real antes de declarar Sprint 1 pronta. Sem smoke = sem sinal.

## Decisão

**Adicionar lista per-business `bypass_business_ids` no config Whatsapp.** Quando `business_id` está na lista, FormRequest **pula apenas a regra 1** (Meta gating cross-field) — termo LGPD continua obrigatório, drivers proibidos continuam proibidos, gating Tier 0 multi-tenant continua em todos outros businesses.

```php
// config/whatsapp.php — adição
'fallback' => [
    // ... existente ...
    'bypass_business_ids' => array_filter(
        array_map('intval', explode(',', env('WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS', '')))
    ),
],
```

`.env` Hostinger biz=1 piloto:
```
WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS=1
```

## Justificativa

1. **Escopo cirúrgico** — não relaxa gate global; só biz_id explicitamente listado. ROTA LIVRE (`business_id=4`) e qualquer cliente externo continuam sob Tier 0 IRREVOGÁVEL.
2. **Auditável via env** — flip claro em `.env`, fácil reverter (`WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS=`) sem deploy de código.
3. **Wagner WR2 (biz=1) é tenant interno** — Wagner é dono do número, do risco e da decisão. Não há cliente externo afetado se Z-API banir o número dele durante smoke.
4. **LGPD continua exigido** — bypass não suspende termo `lgpd_acknowledged_at`. Wagner ainda precisa marcar ciente.
5. **Drivers proibidos continuam proibidos** — `evolution`, `whatsapp_web_js` rejeitados em qualquer biz, listed ou não.
6. **Test Pest cobre regressão** — 1 caso valida bypass biz=1; 1 caso valida que biz=4 (RotaLivre) **continua bloqueado**. Skill `multi-tenant-patterns` Tier A enforce.

## Consequências

**Positivas:**
- Smoke Z-API biz=1 hoje, sem aguardar 1-3 dias Meta Business Manager.
- Sprint 1 fecha loop fechado por métrica (princípio #4).
- Padrão "lista bypass per-tenant" reutilizável pra futuros gates Tier 0 com piloto interno.

**Negativas / Trade-offs:**
- Biz=1 não tem fallback automático se Z-API banir → conversa perde até Wagner cadastrar Meta Cloud manual. Mitigado: smoke é curto (dias), risco aceito conscientemente.
- `effectiveDriver()` no Model pode retornar `fallback_driver=null|meta_cloud` quando `driver_health` degrada — Listener falha silencioso. Mitigado: durante smoke biz=1 health monitor desativado ou tolerante.
- Risco de "esqueci o bypass ligado" — `review_triggers` força revisão quando Meta Cloud aprovada pra biz=1.

**Riscos mitigados:**
- **Cross-tenant leak** — `business_id` checado contra lista exata, não regex/wildcard. Test cobre.
- **PII vazamento** — bypass não afeta encryption cast tokens nem PiiRedactor logs.
- **Permanente by accident** — `review_triggers` força revisão; ADR tem deadline implícito (Meta Cloud aprovada).

## Exit criteria

Esta ADR fica em `lifecycle: ativo` até **uma** das condições:

1. Meta Cloud aprovada e cadastrada pra biz=1 → remover `1` de `WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS`. Bypass fica anotado em commit history; ADR vira `lifecycle: historical` mas append-only preservado.
2. Smoke Z-API descobre ban Meta no biz=1 → bypass urgente removido + ADR nova forçando Meta Cloud antes de qualquer Z-API.
3. 30 dias sem decisão Wagner → escala automática pra recall (Wagner avalia continuar bypass ou bloquear).

## Alternativas consideradas

- **Flag global `WHATSAPP_FALLBACK_REQUIRED=false`** — descartado: relaxa Tier 0 pra todos businesses, viola princípio multi-tenant. Bypass per-ID preserva isolation.
- **Coluna nova `whatsapp_business_configs.bypass_meta_fallback`** — descartado: força migration + config persistida em DB; menos auditável que env. Bypass via env é flip rápido sem schema change.
- **Tinker SSH inserindo direto na tabela bypassing FormRequest** — descartado: deixa rastro pior, salta encryption cast (perde tokens cifrados), zero auditoria.
- **Aguardar Meta Cloud aprovar** — descartado por Wagner: bloqueia smoke Sprint 1 1-3 dias úteis, perde momento.

## Referências

- ADR [0096](0096-modulo-whatsapp-meta-cloud-api-direto.md) — Módulo Whatsapp original (parent_adr)
- ADR [0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição V2 (princípios #4 loop fechado, #6 multi-tenant Tier 0)
- ADR [0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant isolation Tier 0
- Código alterado: [config/whatsapp.php](../../Modules/Whatsapp/Config/config.php), [BusinessSettingsRequest.php](../../Modules/Whatsapp/Http/Requests/BusinessSettingsRequest.php)
- Test cobrindo: [BusinessSettingsValidationTest.php](../../Modules/Whatsapp/Tests/Feature/BusinessSettingsValidationTest.php)
