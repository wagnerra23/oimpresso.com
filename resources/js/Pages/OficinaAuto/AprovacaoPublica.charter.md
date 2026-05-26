---
page_id: oficina-auto/aprovacao-publica
status: live
owner: '[W]'
related_us: [US-OFICINA-006, US-OFICINA-014]
created: 2026-05-16
last_review: 2026-05-26
promoted_live_at: 2026-05-26
related_adrs: [0093, 0094, 0143, 0171, 0192, 0194]
---

# Charter — Aprovação Pública OS (PIN via WhatsApp)

> **Contexto sub-vertical 4 ([ADR 0194](../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** aplicável principalmente pra OS de **mecânica pesada caminhão basculante** (sub-vertical 4 Martinho LIVE prod biz=164) onde cliente terceiro precisa aprovar orçamento de peça hidráulica / hora-trabalho antes de execução. Não específico de locação — schema OS é genérico (manutencao OR locacao preservado nullable). Add-on faturável WhatsApp R$ 99/instância beta 30d ([ADR 0171](../../../../memory/decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)).

## Mission

Cliente final (não-User) aprova ou rejeita um orçamento de OS da oficina automotiva em menos de 30s, sem precisar criar conta, com nível de segurança suficiente pra ter validade comercial (rastreável, anti-bruteforce, anti-tampering).

## Goals

- **G1.** Tornar aprovação do orçamento mecânico de WhatsApp em fluxo de 2 toques (clica link → digita PIN → aprova).
- **G2.** Reduzir tempo médio de aprovação Martinho/Vargas de ~2h (chamada telefônica) pra ~3min (notificação push).
- **G3.** Audit trail: toda aprovação/rejeição gera log estruturado com `os_id`, `business_id`, `ip`, timestamp.
- **G4.** Funciona em tela 360px (Android low-end Martinho clientes) sem precisar zoom.

## Non-Goals

- ❌ NÃO substitui assinatura eletrônica formal (ICP-Brasil) — quando exigido por lei (frota PJ acima de X), fluxo separado.
- ❌ NÃO permite editar valor/itens do orçamento — cliente só aprova/rejeita o que tá.
- ❌ NÃO suporta múltiplos aprovadores (gerente + dono) — V0 single approver.
- ❌ NÃO mostra histórico de OS anteriores do cliente — apenas a OS deste token.

## UX Targets

- **Tempo p50:** abrir link → tela renderizada ≤ 800ms (3G Brasil interior).
- **Tela 360px** sem scroll horizontal, PIN input ocupa largura confortável pro polegar.
- **Empty state robusto:** token inválido/expirado mostra mensagem clara + CTA "entre em contato com a oficina" (sem vazar qual condição falhou).
- **Rate limit feedback:** após 1ª tentativa errada, mostrar "x de 5 tentativas restantes" — transparência > segredo.
- **Lockout claro:** "Aguarde 30 minutos" sem deixar usuário confuso re-tentando.
- **PIN auto-complete iOS/Android:** `autocomplete="one-time-code"` permite SMS code reading se cliente usar device pareado.

## Anti-hooks

- ⛔ NÃO enviar PIN no mesmo canal do link (WhatsApp) — Job WhatsApp envia link, PIN vai por SMS OU 2ª mensagem ≥60s depois (out-of-band).
- ⛔ NÃO armazenar PIN plain-text — apenas hash SHA-256 em cache.
- ⛔ NÃO usar `Math.random()` ou `Str::random()` pra gerar PIN — `random_int(0, 9999)` (CSPRNG).
- ⛔ NÃO loggar payload do token (mesmo decodificado) — `os_id`/`business_id` ok, payload completo não.
- ⛔ NÃO permitir aprovação de OS em status ≠ `orcamento` — idempotente (cenário 5 do test) mas silenciosa.
- ⛔ NÃO redirecionar pra rota admin após sucesso — cliente não tem conta, fica na própria tela com flash.

## Automation Hooks

- `AprovacaoOsService::gerarTokenAprovacao(OS)` chamado por `EnviarLinkAprovacaoWhatsappJob` quando OS muda pra status `orcamento` (US-OFICINA-006 next step).
- Aprovação dispara evento `ServiceOrderAprovadaCliente` (US-OFICINA-006 next step) que aciona FSM transition pra próximo stage.
- Rejeição registra apenas log estruturado — não muda status. Operador humano fecha o loop via chamada/WhatsApp.

## Riscos catalogados

- **R1.** Cliente compartilha link+PIN com terceiro → terceiro aprova. Mitigação: PIN 1-shot (consumido após sucesso), audit log com IP.
- **R2.** Bruteforce PIN 4 dígitos (10k combos) → throttle:30,1 + lockout 5 tentativas + log lockout em telemetria.
- **R3.** Token vazado em link encurtador / WhatsApp preview → TTL 7 dias hard, sem extensão silenciosa.
- **R4.** Cliente clica link após status já mudou (race condition) → `validarToken` re-checa status=`orcamento`; aprovação `lockForUpdate` + idempotência.

## Histórico de promoção draft → live (Wave 4 US-OFICINA-014 — 2026-05-26)

- [x] Job `EnviarLinkAprovacaoWhatsappJob` criado (Wave 4.1) — `Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php`
- [x] Observer `ServiceOrder::updated` hook adicionado (Wave 4.2) — dispatcha Job quando status → orcamento
- [x] Pest tests Wave 4.3:
  - `WhatsAppAprovacaoPinTest` placeholder removido + 2 cenários HTTP integration novos (GET token + POST PIN)
  - `EnviarLinkAprovacaoWhatsappJobTest` novo (Bus::fake + multi-tenant + LGPD + idempotência cache)
  - `AprovacaoOsTokenTest` existente (Service token+PIN geração — validado isolado)
- [x] Charter promovido `status: live` 2026-05-26
- [ ] PR mergeado + canary 7d biz=1 sem incident (próximo passo wallclock)
- [ ] US-OFICINA-006 SPEC.md cross-link com US-OFICINA-014 (gap doc — PR separado)
- [ ] Wagner aprovou screenshot final mobile 360px (smoke prod pós-merge via Chrome MCP)

@see Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php
@see Modules/OficinaAuto/Services/AprovacaoOsService.php
@see Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php (Wave 4.1)
@see Modules/OficinaAuto/Observers/ServiceOrderObserver.php (hook orcamento — Wave 4.2)
@see Modules/OficinaAuto/Tests/Feature/WhatsAppAprovacaoPinTest.php
@see Modules/OficinaAuto/Tests/Feature/EnviarLinkAprovacaoWhatsappJobTest.php (Wave 4.3)
@see Modules/OficinaAuto/Tests/Feature/AprovacaoOsTokenTest.php
