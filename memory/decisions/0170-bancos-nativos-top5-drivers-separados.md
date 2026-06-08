---
adr: 0170-bancos-nativos-top5-drivers-separados
title: "PaymentGateway Top-5 bancos brasileiros — drivers REST e CNAB SEPARADOS (Ondas 4f-4j)"
status: aceito
decided_by: [W]
proposed_by: [Claude]
date: 2026-05-25
related: [0093, 0094, 0105, 0106, 0170, 0170-onda5-simplificada]
supersedes: []
amends: [0170]
tipo: amendment
trust_required: tier-0
---

> **Histórico decisão arquitetural:**
> - v1 proposto 2026-05-25 — dual-mode (1 driver com `mode` REST|CNAB) — **rejeitado Wagner mesmo dia**: "prefiro driver diferente. não misture o gatway. configurações completamente diferentes"
> - v2 aceito 2026-05-25 — drivers separados por modalidade (gateway_key próprio cada). Wagner: "faça"
> - **v3 ampliado 2026-05-26** — Wagner: "sicoob cecred?" → aceitou batch máximo CNAB **11 bancos** (top-5 + Sicoob + Cecred/Ailos + Sicredi + Cresol + Banrisul + BTG). Drivers REST permanecem só top-4 viáveis (Itaú/BB/Bradesco/Santander). Sicoob/Sicredi REST ficam catalogados pra ondas futuras (têm Open Banking público), demais cooperativas só CNAB.

# Contexto

[ADR 0170](0170-paymentgateway-extracao-camada-cobranca.md) estabeleceu PaymentGateway como camada canônica de cobrança. Ondas 1-4e entregaram 5 drivers: **Inter** (banco), **C6** (banco), **Asaas** (gateway), **BCB PIX Automático** (regulador), **Pagar.me** (Stone group). Sessão 2026-05-23 catalogou backlog "driver nativo Bradesco/Itaú/BB/Sicredi/Sicoob" como prioridade alta condicional cliente.

**Demanda Wagner 2026-05-25:** "fazer pacote completo top-5 bancos" — Bradesco + Itaú + BB + Santander + Caixa.

**Pesquisa paralela 5 dossiês** (`audit-research-expert`, 2026-05-25) — resumo em [`memory/sessions/2026-05-25-consolidado-top5-bancos-paymentgateway.md`](../sessions/2026-05-25-consolidado-top5-bancos-paymentgateway.md):

| Banco | Verdict REST | Esforço cód. | Calendário humano |
|---|---|---|---|
| Itaú | ✅ VIÁVEL (reuso 80% Inter) | 7-9h | 2-3 sem |
| BB | ✅ VIÁVEL c/ ressalva | 12-18h | 10-25 dias |
| Bradesco | 🟡 PARCIAL (PKCS#7 proprietário) | 24h | 14 dias irredutíveis |
| Santander | 🟡 PARCIAL (burocrático) | 22h | 3-5 sem |
| Caixa | ❌ INVIÁVEL (portal quebrado, SOAP legado) | skip | 30-90d se forçar |

**Achado crítico:** lib `eduardokum/laravel-boleto` (já no projeto em `lib-custom/`) tem **API REST APENAS pro Inter** mas **CNAB pra 11 bancos** incluindo todos os 5 do pacote.

# Decisão

PaymentGateway entra na fase top-5 bancos brasileiros via **drivers SEPARADOS por modalidade** — cada combinação `banco+modalidade` é um `gateway_key` próprio com wizard, schema de credencial, contract e Pest próprios. **Nada de `mode` no `payment_gateway_credentials`.** Razão: configurações REST (OAuth2 + mTLS + webhook URL + secret) e CNAB (convênio + sequencial nosso-número + cedente + SFTP host/user/key + carteira/modalidade) são **completamente diferentes** — misturar polui wizard e código.

## Catálogo de novos `gateway_key`

| Banco | API REST (Open Banking) | CNAB (arquivo remessa/retorno) |
|---|---|---|
| **Bradesco** | `bradesco_api` (Onda 4i) | `bradesco_cnab` (Onda 4f.cnab) |
| **Itaú** | `itau_api` (Onda 4f) | `itau_cnab` (Onda 4f.cnab) |
| **BB** | `bb_api` (Onda 4g) | `bb_cnab` (Onda 4f.cnab) |
| **Santander** | `santander_api` (Onda 4j) | `santander_cnab` (Onda 4f.cnab) |
| **Caixa** | — (INVIÁVEL hoje) | `caixa_cnab` (Onda 4f.cnab) |

Cliente que quer Bradesco com boleto registrado **e** PIX REST cadastra **2 gateways** (`bradesco_cnab` + `bradesco_api`). Cada um aparece como linha independente em `/settings/payment-gateways` com indicador visual do modo.

## Ondas de execução

1. **Onda 4f.0 — Fundação CNAB compartilhada (~6h, sequencial obrigatório)**
   - Abstract `CnabBoletoAdapter` bridging `EmitirCobrancaInput` ↔ `Eduardokum\LaravelBoleto\Boleto\Banco\{X}`
   - Job `CnabRetornoProcessor` (upload arquivo retorno → parse → dispatch `CobrancaPaga`/`CobrancaVencida`)
   - UI `/payment-gateways/{id}/cnab-retorno` (upload manual + histórico processamento)
   - Migration `payment_gateway_credentials.config_json` aceita schema CNAB (convênio, sequencial, SFTP) — sem coluna nova
   - Pest `CnabBoletoAdapterContractTest`
   - **Sem `mode` column** — `gateway_key` distingue REST vs CNAB

2. **Onda 4f.cnab — 5 drivers CNAB em PARALELO (~10h total, ~2h cada)**
   - `BradescoCnabDriver`, `ItauCnabDriver`, `BBCnabDriver`, `SantanderCnabDriver`, `CaixaCnabDriver`
   - Cada um é fino — herda da fundação `CnabBoletoAdapter`, configura layout 240/400 + banco específico
   - Wizard CNAB próprio: campos convênio + carteira + agência + conta + cedente + SFTP opcional
   - Spawn 5 `audit-implement-expert` em paralelo (worktrees isolados, sem overlap)

3. **Ondas 4f-4j — 4 drivers REST (sequencial pra preservar pattern InterDriver canon)**
   - **4f `ItauApiDriver`** (~9h) — slot #1, reuso 80% Inter + Bolecode bônus, sandbox fechado (`Http::fake()`)
   - **4g `BBApiDriver`** (~16h) — slot #2, sandbox aberto facilita Pest, gotcha gw-app-key dual-header
   - **4i `BradescoApiDriver`** (~26h) — slot #3, PKCS#7 proprietário +80 LOC, credenciais visíveis 3 dias
   - **4j `SantanderApiDriver`** (~24h) — slot #4, cert A1 ICP-Brasil cliente compra, sandbox deprecado
   - (Caixa REST NÃO entra — INVIÁVEL hoje, reavaliar Q3-2026 ADR 0105)

## Cliente experience

- Dia 1: cliente cadastra `{banco}_cnab` no wizard → preenche convênio + carteira + cedente → **emite boleto via arquivo remessa imediato**
- Dia N: quando banco homologar Open API → cliente cadastra `{banco}_api` (não substitui CNAB, coexistem) → migra cobranças novas pra REST gradualmente → eventualmente desativa CNAB
- Cada gateway tem audit log próprio, métricas próprias, replay próprio (`mcp_audit_log` segrega por `gateway_key`)

**Esforço total:** ~6h fundação + ~10h CNAB paralelo + ~75h REST sequencial = **~91h código (~12 dias IA-pair ADR 0106)** · humano-limitado paralelo 14-45 dias calendário (não soma, não bloqueia CNAB)

# Consequências

**Positivas:**
- Cliente Bradesco/Itaú/BB/Santander/Caixa emite **boleto dia 1** via `{banco}_cnab` (não espera homologação 14-45d)
- Cobertura imediata top-5 bancos brasileiros (paridade Bling/Tiny/Omie)
- Drivers REST entram incrementais conforme cliente homologa Open API — **coexistem** com CNAB (não substituem)
- Wizard de cada driver tem **campos coerentes** (CNAB: convênio/carteira/SFTP · REST: OAuth2/mTLS/webhook) — zero condicional polui UX
- Código simples — cada driver implementa só sua modalidade, sem `if mode === 'cnab'` espalhado
- Fundação `CnabAdapter` reaproveitável pros 6 bancos restantes da lib (Sicredi/Sicoob/Cresol/Banrisul/BTG/Ailos/Bancoob) em ondas futuras
- Não desperdiça ~40-80h tentando driver REST Caixa que é inviável
- Audit log + métricas + replay segregados por `gateway_key` — facilita diagnóstico (cliente vê "Bradesco CNAB falhou 3x" vs "Bradesco API timeout")

**Negativas/riscos:**
- Cliente que quer ambas modalidades cadastra **2 gateways** pra mesmo banco — UX precisa indicador visual claro ("Bradesco · CNAB" vs "Bradesco · API REST") pra não confundir
- CNAB retorno requer upload manual ou auto-SFTP → UX inferior a webhook real-time
- Cliente esquece de processar retorno → status defasado (mitigação: alerta WhatsApp `business.contact_financeiro` se sem upload há 3 dias úteis)
- Lib `eduardokum/laravel-boleto` upstream breaking changes (mitigação: lock versão composer + PR isolado pra upgrade)
- Caixa REST entra "do nada" no futuro — adicionar `caixa_api` driver novo (~24h estimado quando portal Caixa voltar a funcionar)
- 9 novas keys em `PaymentGatewayService::DRIVERS` (bradesco_api, bradesco_cnab, itau_api, itau_cnab, bb_api, bb_cnab, santander_api, santander_cnab, caixa_cnab) — UI `/settings/payment-gateways/create` precisa agrupar visualmente por banco

**Tier 0:**
- `business_id` global scope honrado em `cnab_remessas`/`cnab_retornos` (HasBusinessScope)
- Cliente pode ter 2 `payment_gateway_credentials` ativas pro mesmo banco (`bradesco_cnab` + `bradesco_api`) — `for(Account)` resolver precisa critério explícito (preferência: `gateway_key` informado no input, ou fallback "última ativa")
- Sem cross-tenant nesta onda (cliente cadastra credencial dele em biz dele)

# Pré-condições humano-limitado (Wagner facilita)

1. **Cliente piloto com conta PJ por banco** (pra smoke real) — Wagner identifica 1 cliente por banco da carteira (CNAB não exige, REST exige cliente real)
2. **Cert A1 e-CNPJ por cliente** quando partir pra modo REST (Bradesco/Itaú/BB/Santander cobram ICP-Brasil R$ 200-400/ano — cliente compra)
3. **Email cliente → suporte API banco + gerente PJ** (homologação 14-45d, varia por banco)

# Refs

- Pai: [ADR 0170](0170-paymentgateway-extracao-camada-cobranca.md) — PaymentGateway extração
- Pai: [ADR 0170-onda5-simplificada](0170-onda5-simplificada.md) — dogfooding SaaS
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado (clientes têm contas PJ, Wagner não)
- [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Esforço recalibrado IA-pair
- Pesquisa consolidada: [`memory/sessions/2026-05-25-consolidado-top5-bancos-paymentgateway.md`](../sessions/2026-05-25-consolidado-top5-bancos-paymentgateway.md)
- Dossiês individuais: `memory/sessions/2026-05-25-arte-banco-{bradesco,itau,bb,santander,caixa}.md`
- Feedback pattern desta sessão: [`feedback-pesquisa-paralela-antes-pacote-grande.md`](../reference/feedback-pesquisa-paralela-antes-pacote-grande.md)
- Lib: [`lib-custom/laravel-boleto/`](../../lib-custom/laravel-boleto/) — eduardokum/laravel-boleto fork (API Inter + CNAB 11 bancos + Webhook Inter)
