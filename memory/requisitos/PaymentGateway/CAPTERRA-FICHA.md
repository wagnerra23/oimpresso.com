---
slug: paymentgateway-capterra-ficha
title: "CAPTERRA-FICHA — PaymentGateway"
type: capterra-ficha
module: PaymentGateway
status: ativo
nota_capterra: 67
nota_capterra_faixa: "Médio"
module_grade_v3: 63
gerado_por: capterra-senior
gerado_em: "2026-07-03"
related_adrs:
  - "0089-capterra-driven-module-evolution"
  - "0093-multi-tenant-isolation-tier-0"
  - "0101-tests-business-id-1-nunca-cliente"
  - "0170-paymentgateway-extracao-camada-cobranca"
owner: wagner
---

# CAPTERRA-FICHA — PaymentGateway

> **Ficha canônica de benchmark do módulo PaymentGateway** — fonte de verdade para a skill `comparativo-do-modulo` (ADR [0089](../../decisions/0089-capterra-driven-module-evolution.md)).
>
> ⚠️ **Encaixe (T6):** esta ficha é o **Passo 1** do [template-onda-modulo](../_Governanca/programa-ondas/template-onda-modulo.md) e **complementa** o [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md) ativo — **não** abre roadmap paralelo. Os gaps daqui alimentam o `/comparativo` (Passo 2) que apenda US ao [SPEC.md](SPEC.md) existente.
>
> **Estado avaliado:** `origin/main` @`7442c27c43` (2026-07-03). Read-only.

---

## §1 · Identidade do módulo

- **Nome interno:** `PaymentGateway`
- **Domínio:** camada técnica de cobrança bancária BR — **um único lugar pra falar com bancos**. Emite boleto/Pix/cartão, processa webhooks, reconcilia recebimentos, gera CNAB remessa/retorno. Consumido por `Sells`, `RecurringBilling`, `NfeBrasil`/`NFSe` e `Superadmin` (licença SaaS dogfooding).
- **Natureza (diferencia da concorrência):** **não é subadquirente/PSP** — é uma **camada de integração banco-direto** (API REST do banco + CNAB), multi-gateway por `business_id`. O dinheiro cai direto na conta do tenant, sem spread de intermediário.
- **Cliente-alvo:** Wagner biz=1 (dogfooding SaaS, Onda 5) + ROTA LIVRE (Larissa, biz=4) + qualquer business oimpresso que cobra.
- **Trust tier:** L3 · **Tier 0** (toca valor + multi-tenant). Charter ADR [0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md).
- **Nota Capterra (paridade de mercado):** **67/100 — Médio** (ponderação P0=4/P1=2/P2=1/P3=0.5). Sobe de **57** (audit 2026-05-25) após US-PG-001 (encrypted) + US-PG-002 (HMAC). `module-grade v3` = **63** (rubrica mais ampla: docs/tests/observabilidade).

---

## §2 · Concorrentes-alvo + posicionamento

| # | Concorrente | Tipo | Posicionamento | Ameaça direta |
|---|---|---|---|---|
| 1 | **Asaas** | Conta digital + gateway PME | Cobrança tudo-em-um (boleto/Pix/carnê + régua + Serasa nativo) | **Alta** — já é driver do módulo; referência de dunning/webhook |
| 2 | **Iugu** | Infra financeira SaaS/marketplace | Multisplit + recorrência + BaaS | Média — split/recorrência complexa |
| 3 | **Pagar.me** | Gateway/adquirente (Stone) | E-commerce/app de volume, cartão de ponta (PCI N1, 3DS 2.0) | **Alta** — já é driver; benchmark de cartão/antifraude |
| 4 | **Stripe** (BR) | Gateway global dev-first | SaaS/cross-border, DX + webhook/idempotency padrão-ouro | Média — sem Pix Automático BR, sem CNAB, bandeiras locais limitadas |
| 5 | **Mercado Pago** | Adquirente + fintech ML | Marketplace/PME, checkout link/WhatsApp | Média — split + Pix Automático + capilaridade |
| 6 | **Cielo** | Adquirente/maquininha + API | Lojista físico+online, **Conciliador/EDI** contábil | Média — conciliação contábil BR forte |

**Leitura estratégica:** os 6 são **PSP/adquirente conta-própria**; o PaymentGateway é **banco-direto multi-tenant**. Dois eixos onde o módulo **ganha por natureza** (multi-gateway isolado por business + CNAB 240/400 multi-banco) e três onde **perde** (split, cartão/antifraude, régua de inadimplência).

---

## §3 · Matriz comparativa por dimensão (8 dimensões pedidas)

Legenda: ✅ maduro · 🟡 parcial/pendente · ❌ ausente

| Dimensão | **PaymentGateway** | Asaas | Iugu | Pagar.me | Stripe | MercadoPago | Cielo |
|---|---|---|---|---|---|---|---|
| **Boleto** registrado API | ✅ 5 drivers | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **CNAB 240/400** remessa/retorno | ✅✅ **11 bancos** | ❌ | 🟡 interop | ❌ | ❌ | ❌ | ❌ |
| **Pix** cob/cobv | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Pix Automático** (mandato) | 🟡 driver pronto, sem homolog | ✅ | ✅ | ✅ (v5) | ❌ **BR** | ✅ | ✅ |
| **Cartão** token+charge | 🟡 2 drivers | ✅ | ✅ | ✅✅ | ✅✅ | ✅ | ✅ |
| **3DS / antifraude** próprio | ❌ (delega ao gateway) | ✅ | ✅ | ✅✅ | ✅✅ | ✅ | ✅ |
| **Split** múltiplos recebedores | ❌ | ✅ | ✅✅ | ✅ | ✅ | ✅ | ✅ |
| **Refund/estorno** API full+partial | 🟡 3/6 drivers | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Webhook** HMAC+idempotência | 🟡 3 reais + 4 fixados, hardening pendente | ✅✅ | 🟡 | 🟡 | ✅✅ | 🟡 | ✅ |
| **Reconciliação** auto (push+pull) | ✅ single-source canon | ✅ | ✅ | ✅ | ✅✅ | 🟡 | ✅✅ EDI |
| **Inadimplência/dunning** régua | ❌ (vive em RB) | ✅✅ Serasa | ✅ | 🟡 | ✅✅ ML | 🟡 | 🟡 |
| **Multi-gateway por business** (Tier 0) | ✅✅ **diferencial** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

**2 diferenciais que nenhum concorrente tem:** (a) **CNAB multi-banco** (11 drivers remessa/retorno) — território de banco, não de PSP; (b) **multi-gateway isolado por `business_id`** — o tenant escolhe o próprio banco e o dinheiro cai na conta dele, sem intermediário.

**Fato regulatório (Pix Automático):** LIVE desde **16/06/2025** (Res. BCB — o módulo referencia 380/2024; a data foi remarcada por norma posterior). **Todos os concorrentes exceto Stripe já expõem API**. O `BcbPixDriver` (PSP-agnóstico, Res. 380/2024) é uma aposta **early** correta, mas **não passou smoke/homologação** — pré-condições humano-limitadas do PLANO-ONDA5 §3.

---

## §4 · Capacidades baseline com score (P0-P3)

> Estado do módulo: ✅ APROVADO (1.0) · 🟡 PARCIAL (0.5) · ❌ AUSENTE (0). Evidência = path real em `origin/main`.

```yaml
capacidades:
  - id: boleto-api-banco-direto
    nome: "Boleto registrado via API do banco (sem intermediário)"
    score: P0
    estado: "✅"
    quem_tem: ["Asaas","Iugu","Pagar.me","MercadoPago","Cielo"]
    evidencia: "5 drivers API — InterDriver/AsaasDriver/C6Driver/PagarmeDriver/SicoobApiDriver::emitirBoleto()"

  - id: cnab-multibanco-remessa-retorno
    nome: "CNAB 240/400 remessa + retorno multi-banco"
    score: P1
    estado: "✅"
    quem_tem: ["—"]  # diferencial — nenhum PSP concorrente oferece
    evidencia: "11 CnabDrivers (Ailos/BB/Banrisul/Bradesco/BTG/Caixa/Cresol/Itau/Santander/Sicoob/Sicredi) + CnabBoletoAdapter + CnabRetornoProcessor job"

  - id: pix-cob-cobv
    nome: "Pix cobrança imediata (cob) e com vencimento (cobv)"
    score: P0
    estado: "✅"
    quem_tem: ["Asaas","Iugu","Pagar.me","Stripe","MercadoPago","Cielo"]
    evidencia: "InterDriver (cob+cobv), Asaas/C6/Pagarme/SicoobApi (cob)"

  - id: pix-automatico-mandato
    nome: "Pix Automático — mandato recorrente (Res. BCB 380/2024)"
    score: P1
    estado: "🟡"
    quem_tem: ["Asaas","Iugu","Pagar.me","MercadoPago","Cielo"]  # Stripe NÃO no BR
    evidencia: "BcbPixDriver::emitirPixAutomatico() PSP-agnóstico existe; homologação BCB + smoke = pré-condição humano-limitada PLANO-ONDA5 §3"

  - id: cartao-token-charge
    nome: "Cartão tokenizado + cobrança"
    score: P1
    estado: "🟡"
    quem_tem: ["Asaas","Iugu","Pagar.me","Stripe","MercadoPago","Cielo"]
    evidencia: "Só AsaasDriver + PagarmeDriver::cobrarCartao(); Inter/C6/Sicoob/BcbPix não suportam card"

  - id: cartao-recorrente-retry
    nome: "Cartão recorrente/assinatura + smart retry"
    score: P1
    estado: "🟡"
    quem_tem: ["Asaas","Iugu","Pagar.me","Stripe","MercadoPago","Cielo"]
    evidencia: "Recorrência vive em Modules/RecurringBilling; PaymentGateway só emite charge single; sem retry próprio"

  - id: antifraude-3ds
    nome: "3DS 2.0 / antifraude próprio"
    score: P2
    estado: "❌"
    quem_tem: ["Pagar.me","Stripe","Cielo","MercadoPago","Asaas","Iugu"]
    evidencia: "Delegado 100% ao gateway subjacente; nenhuma camada Radar/antifraude no módulo"

  - id: split-pagamento
    nome: "Split de pagamento (múltiplos recebedores)"
    score: P2
    estado: "❌"
    quem_tem: ["Asaas","Iugu","Pagar.me","Stripe","MercadoPago","Cielo"]
    evidencia: "grep split → só CONTRACTS.md/README (menção), zero implementação em driver"

  - id: refund-api
    nome: "Refund/estorno via API (full + parcial)"
    score: P0
    estado: "🟡"
    quem_tem: ["Asaas","Iugu","Pagar.me","Stripe","MercadoPago","Cielo"]
    evidencia: "AsaasDriver/InterDriver(PIX)/PagarmeDriver refund; C6/Sicoob/BcbPix throw DriverNotSupported; boleto Inter = TED reverso manual"

  - id: pix-devolucao
    nome: "Pix devolução via API"
    score: P1
    estado: "🟡"
    quem_tem: ["Asaas","Pagar.me","Stripe","MercadoPago","Cielo"]
    evidencia: "InterDriver refund PIX (Onda 4c); não generalizado a todos os drivers Pix"

  - id: webhook-hmac-idempotente
    nome: "Webhook com assinatura HMAC + idempotência"
    score: P0
    estado: "🟡"
    quem_tem: ["Asaas","Stripe","Cielo"]
    evidencia: "PagarmeWebhookController/InterPixWebhookController/SicoobApiWebhookController = HMAC real; legacy 4 fixados US-PG-002 @98cae0a; idempotência via gateway_webhook_events UNIQUE; MAS throttle+timestamp-window+nonce (US-PG-003) PENDENTE + Inter mTLS (US-PG-006) PENDENTE"

  - id: reconciliacao-auto
    nome: "Reconciliação automática push+pull (single source)"
    score: P0
    estado: "✅"
    quem_tem: ["Asaas","Iugu","Pagar.me","Stripe","Cielo"]
    evidencia: "ReconciliarCobrancaService canon compartilhado por ProcessarWebhookPixInterJob (push) + InterReconcilePixCommand (pull polling); dispara CobrancaPaga → Financeiro"

  - id: webhook-orphan-retry
    nome: "Retry/re-resolve de webhook órfão (race emissão)"
    score: P1
    estado: "🟡"
    quem_tem: ["Asaas","Stripe"]
    evidencia: "RetryOrphanWebhookJob + CobrancaWebhookResolver (linkage PR #3371); cron DORMENTE flag PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED=OFF até cutover+dry-run (REGRA MESTRE valor)"

  - id: extrato-saldo-api
    nome: "Extrato/saldo bancário + conciliação contábil"
    score: P2
    estado: "🟡"
    quem_tem: ["Asaas","Cielo (EDI)","Stripe (Sigma)"]
    evidencia: "InterImportarRecebimentosCommand puxa recebimentos; sem saldo_cached unificado nem relatório de conciliação como Cielo EDI/Stripe Sigma"

  - id: dunning-regua
    nome: "Régua de cobrança automática (D+1/D+3/D+7)"
    score: P1
    estado: "❌"
    quem_tem: ["Asaas","Iugu","Stripe","MercadoPago"]
    evidencia: "Fora do escopo do módulo — dunning vive em RecurringBilling; PaymentGateway não dispara lembretes"

  - id: negativacao-serasa
    nome: "Negativação Serasa/SPC nativa"
    score: P2
    estado: "❌"
    quem_tem: ["Asaas","Iugu"]
    evidencia: "Nenhum fluxo de negativação no módulo"

  - id: bloqueio-inadimplencia-saas
    nome: "Bloqueio/desbloqueio tenant por inadimplência (dogfooding SaaS)"
    score: P2
    estado: "🟡"
    quem_tem: ["—"]  # diferencial vertical (SaaS próprio)
    evidencia: "Listeners OnCobrancaPaga/VencidaUpdateSubscription (Onda 5, PR #1148) codados; smoke biz=1 humano-limitado pendente"

  - id: multi-gateway-por-business
    nome: "Multi-gateway isolado por business_id (Tier 0)"
    score: P0
    estado: "✅"
    quem_tem: ["—"]  # diferencial estrutural
    evidencia: "payment_gateway_credentials.business_id global scope; for(Account) resolve credencial do tenant; PaymentGatewayCredentialResolver"

  - id: credenciais-encrypted
    nome: "Credenciais (api_key/secret/cert) encrypted at rest"
    score: P0
    estado: "✅"
    quem_tem: ["Asaas","Stripe","Pagar.me","Cielo","MercadoPago","Iugu"]
    evidencia: "config_json cast 'encrypted:array' (US-PG-001 @98cae0a) + RewrapCredentialsCommand; PCI-DSS 4.0 app-layer"
```

---

## §5 · Nota 0-100 ponderada

**Fórmula:** `Σ(estado × peso) / Σ(peso) × 100`, pesos P0=4·P1=2·P2=1·P3=0.5, estado ✅=1·🟡=0.5·❌=0.

| Faixa | Capacidades | Ganho / Máximo |
|---|---|---|
| **P0 (peso 4)** | boleto✅ · pix-cob✅ · refund🟡 · webhook-hmac🟡 · reconcile✅ · multi-gateway✅ · cred-encrypt✅ | 24.0 / 28 |
| **P1 (peso 2)** | cnab✅ · pix-auto🟡 · cartao-token🟡 · cartao-recorr🟡 · pix-devol🟡 · orphan-retry🟡 · dunning❌ | 8.0 / 14 |
| **P2 (peso 1)** | 3ds❌ · split❌ · extrato🟡 · negativacao❌ · bloqueio-saas🟡 | 1.0 / 5 |
| **Total** | 19 capacidades | **33.0 / 47 = 70** |

> Ajuste de honestidade: o boleto-híbrido (boleto+QR Pix no mesmo doc, P1 🟡) e a granularidade de refund puxam pra baixo → **nota final calibrada 67/100 (Médio)**. Consistente com `module-grade v3 = 63` (a rubrica ampla penaliza doc/observabilidade além de features).

**Interpretação:** forte na **espinha dorsal boleto/Pix/CNAB/multi-tenant** (P0 quase todo verde), fraco na **camada de produto de PSP moderno** (split, cartão de ponta, dunning). O que falta pra "Alto" (≥80) é fechar refund + webhook hardening (P0 🟡→✅) e entregar split + Pix Automático homologado (P1/P2).

---

## §6 · Gaps priorizados (impacto × esforço)

| # | Gap | Score | Impacto | Esforço | Ação sugerida |
|---|---|---|---|---|---|
| G1 | **Webhook hardening** (throttle 120/min + timestamp window + nonce) — US-PG-003 | P0 | Alto (fraude/DoS Tier 0) | Baixo (~4h) | Fechar US-PG-003 (já especificada) |
| G2 | **Refund uniforme** nos 6 drivers (C6/Sicoob boleto + Pix devolução geral) | P0 | Alto (operação real) | Médio | Estender contrato refund; TED reverso doc pra boleto |
| G3 | **Pix Automático homologado** (smoke BCB + prod) | P1 | Alto (recorrência SaaS Onda 5) | Humano-limitado | Executar pré-condições PLANO-ONDA5 §3 (Wagner + BCB 1-3d) |
| G4 | **Cutover webhooks Onda 3** (registrar URLs + habilitar orphan retry) | P0 | Alto (linkage já pronto, dormante) | Baixo + gate | Dry-run antes→depois + aprovação Wagner (REGRA MESTRE) |
| G5 | **Inter webhook mTLS** (US-PG-006) + URL pública CT100 (US-PG-007) | P1 | Médio (latência; polling cobre) | Médio (infra) | Confirmar mecanismo real Inter; expor Caddy CT100 |
| G6 | **Split de pagamento** (múltiplos recebedores) | P2 | Médio (marketplace/comissão) | Alto | Só se sinal de cliente (ADR 0105) — feature-wish hoje |
| G7 | **Boleto híbrido** (boleto + QR Pix no mesmo documento) | P1 | Médio (UX pagador) | Médio | Inter/Asaas suportam; expor no adapter |
| G8 | **Cartão recorrente + smart retry** próprio | P1 | Médio (assinatura) | Alto | Depende de estratégia RB↔PG; hoje RB detém |
| G9 | **Extrato/saldo unificado + conciliação contábil** (estilo Cielo EDI) | P2 | Médio (financeiro Wagner) | Alto | Onda futura; integra fin_contas_bancarias.saldo_cached |
| G10 | **3DS/antifraude** próprio | P2 | Baixo (gateway já cobre) | Alto | Não priorizar — delegação ao PSP é aceitável |
| G11 | **Dunning/negativação** no módulo | P1/P2 | Baixo p/ escopo (RB cobre) | Alto | Manter em RB; não duplicar |
| G12 | **Smokes Onda 5 dogfooding** (biz=1 + canary Larissa) — US-PG-009 | P1 | Alto (valida ciclo end-to-end) | Humano-limitado | Roteiro + evidência (ADR 0106 relógio real) |

**Ordem recomendada (encaixa no PLANO-ONDA5, não paralelo):** G1 → G4 → G2 → G3/G12 (humano-limitados em paralelo) → depois G7 → avaliar G6 por sinal.

---

## §7 · Como auditar este módulo (paths + critérios)

> Lido pela skill `comparativo-do-modulo` no passo 2.5.

**Locais a inspecionar (paths exatos `origin/main`):**
- Contratos: `Modules/PaymentGateway/Contracts/{PaymentGatewayContract,PaymentDriverContract}.php`
- Service orquestrador: `Modules/PaymentGateway/Services/PaymentGatewayService.php` (for/idempotência/DRIVERS map)
- Drivers API: `Modules/PaymentGateway/Services/Drivers/{Inter,Asaas,C6,BcbPix,Pagarme,SicoobApi}Driver.php`
- Drivers CNAB: `Modules/PaymentGateway/Services/Cnab/Drivers/*.php` (11) + `Cnab/CnabBoletoAdapter.php`
- Reconciliação: `Modules/PaymentGateway/Services/ReconciliarCobrancaService.php` + `Webhook/CobrancaWebhookResolver.php`
- Webhooks: `Modules/PaymentGateway/Http/Controllers/Webhooks/{WebhookProcessor,*WebhookController}.php`
- Jobs: `Modules/PaymentGateway/Jobs/{ProcessarWebhookPixInterJob,RetryOrphanWebhookJob,CnabRetornoProcessor}.php`
- Commands: `Modules/PaymentGateway/Console/Commands/*.php` (8 — reconcile/rewrap/register-permissions/retry-orphan/register-webhook)
- Models: `Modules/PaymentGateway/Models/{Cobranca,PaymentGatewayCredential,GatewayWebhookEvent,CnabRetornoUpload,InterWebhookLog}.php`
- Tabelas: `payment_gateway_credentials`, `cobrancas`, `gateway_webhook_events`
- UI: `resources/js/Pages/Settings/PaymentGateways/{Index,CnabRetorno}.tsx` (+ `.charter.md`)
- Tests: `Modules/PaymentGateway/Tests/Feature/*.php` (**43 arquivos**)

**Critérios de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita | ❌ AUSENTE |
|---|---|---|---|
| Boleto API | driver emitirBoleto + Pest + webhook recebido | driver existe, sem prod-evidence | sem driver |
| Refund | contrato refund nos drivers relevantes + Pest | 1-3 drivers, resto NotSupported | nenhum |
| Webhook HMAC | validateSignature real + throttle + timestamp + Pest fail-secure | HMAC parcial, hardening pendente | signature_valid hardcoded |
| Pix Automático | driver + smoke sandbox BCB + mandato prod ≥1 | driver sem homologação | sem driver |
| Split | modelo + endpoint + divisão correta em Pest | menção em doc | nada |
| Reconciliação | push+pull single-source + CobrancaPaga + Pest idempotência | 1 caminho só | manual |

**Métricas de prod relevantes** (health check `paymentgateway:health`):
- `webhook_idempotency` — `gateway_webhook_events` sem duplicatas/hora (meta 0)
- `cobrancas_em_erro` — status=erro/hora abaixo do threshold
- `race_orphan_cobranca_paga` — meta 0 (US-PG-008)
- `credenciais_ativas` — ≥1 por business com cobrança aberta

---

## §8 · UX heuristics + Automation targets (Capterra v2)

```yaml
ux_heuristics:
  - id: cadastrar-gateway-clicks
    nome: "Passos pra cadastrar um gateway novo (wizard 3 steps)"
    score: P1
    benchmark: "Asaas: cola api_key (1 campo). Stripe: connect OAuth. Inter mTLS: cert+id+secret+conta."
    target: "<= 3 steps (Driver → Credenciais → Vínculo conta)"
    metrica: "navegacao_steps_novo_gateway"

  - id: emitir-cobranca-drawer-clicks
    nome: "Cliques pra emitir cobrança a partir da Venda"
    score: P0
    benchmark: "Asaas link 2. MP checkout link 1-2."
    target: "<= 2 (SaleSheet → Emitir cobrança herda contato/valor)"
    metrica: "navegacao_steps_emitir_cobranca_venda"

  - id: ver-status-cobranca
    nome: "Tempo até ver 'paga' após pagamento"
    score: P1
    benchmark: "Asaas/Stripe webhook <5s. Inter polling ~minutos."
    target: "webhook <30s p95; polling fallback <15min"
    metrica: "cobranca_paga_latencia_p95"

automation_targets:
  - id: webhook-idempotente
    nome: "Webhook de pagamento idempotente (replay 2x não dá double credit)"
    score: P0
    benchmark: "Todos. Padrão UNIQUE(provider,event_id) + timestamp window."
    target: "gateway_webhook_events UNIQUE + throttle 120/min + timestamp 5min + nonce (US-PG-003)"
    metrica: "webhook_double_credit_24h (alvo 0) + webhook_replay_rejected"

  - id: reconcile-pull-fallback
    nome: "Reconciliação por polling quando gateway não chama webhook"
    score: P0
    benchmark: "Inter não exige webhook → polling é canônico."
    target: "InterReconcilePixCommand cron + ReconciliarCobrancaService single-source"
    metrica: "cobrancas_reconciliadas_pull_24h + reconcile_drift"

  - id: orphan-requeue
    nome: "Webhook chega antes da emissão → re-resolve em vez de desistir"
    score: P1
    benchmark: "Asaas trava fila após 15 falhas; Stripe retry 3 dias."
    target: "RetryOrphanWebhookJob re-resolve cobranca_id NULL (flag ON pós-cutover)"
    metrica: "orphan_webhook_reresolved_rate"

  - id: paga-libera-tenant
    nome: "Paga → libera tenant SaaS / vence → bloqueia (dogfooding)"
    score: P2
    benchmark: "—"  # diferencial vertical
    target: "OnCobrancaPaga/Vencida listeners + Business.officeimpresso_bloqueado (Onda 5)"
    metrica: "bloqueios_inadimplencia_24h + subscription_pagamentos_24h"
```

---

## §9 · Métricas de adoção

- **1ª auditoria Capterra:** 2026-07-03 (esta ficha — antes só existia audit inline 2026-05-25 = 57/100)
- **Nota Capterra atual:** 67/100 (Médio) · `module-grade v3`: 63
- **Capacidades P0:** 5 ✅ / 2 🟡 (refund, webhook-hmac) — nenhuma ❌
- **Gap P0+P1 atual:** webhook hardening (G1), refund uniforme (G2), Pix Automático homolog (G3), cutover webhooks (G4)
- **Cobertura de teste:** 43 arquivos Pest Feature
- **Estado prod:** parcial — boleto Inter LIVE biz=1 (via `inter_webhook_log` + `ProcessarWebhookPixInterJob`); Onda 5 dogfooding aguarda smokes humano-limitados
- **Próxima reauditoria sugerida:** 2026-10-03 (trimestral) ou ao fechar G1-G4

---

## §10 · Histórico de revisão + Referências externas

**Histórico da ficha:**
- `2026-07-03` — capterra-senior — criação (Passo 1 template-onda-modulo, complementa PLANO-ONDA5). Base `origin/main`@`7442c27c43`. 6 concorrentes × 8 dimensões, 19 capacidades P0-P3, nota 67.

**Referências internas:**
- [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md) · [SPEC.md](SPEC.md) (US-PG-001..009) · [BRIEFING.md](BRIEFING.md) · [RUNBOOK-settings-gateways.md](RUNBOOK-settings-gateways.md) · [RUNBOOK-sicoob-api.md](RUNBOOK-sicoob-api.md)
- ADR [0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) charter · [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0 · [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) sinal · [0089](../../decisions/0089-capterra-driven-module-evolution.md) Capterra-driven

**Referências externas (com citação da pesquisa 2026-07-03):**
- Asaas: [Pix Automático](https://docs.asaas.com/docs/pix-automatico) · [Split](https://docs.asaas.com/docs/split-de-pagamentos) · [Webhook idempotência](https://docs.asaas.com/docs/como-implementar-idempotencia-em-webhooks) · [Preços](https://www.asaas.com/precos-e-taxas)
- Iugu: [Pix Automático API](https://dev.iugu.com/docs/cobrar-com-pix-automatico-por-api) · [Multisplit](https://dev.iugu.com/docs/split-de-pagamentos) · [Estorno API](https://dev.iugu.com/docs/realizar-o-reembolso-de-faturas-estorno-por-api)
- Pagar.me: [Pix](https://docs.pagar.me/docs/pix-1) · [Recorrência v5](https://docs.pagar.me/docs/overview-recorr%C3%AAncia) · [Webhooks](https://docs.pagar.me/reference/vis%C3%A3o-geral-sobre-webhooks)
- Stripe: [Pix](https://docs.stripe.com/payments/pix) · [Métodos BR](https://support.stripe.com/questions/accepted-payment-methods-in-brazil) · [Smart Retries](https://docs.stripe.com/billing/revenue-recovery/smart-retries) · [Pix via EBANX](https://www.prnewswire.com/news-releases/stripe-users-can-now-accept-pix-in-brazil-via-ebanx-302526007.html)
- Mercado Pago: [Pix](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/pix) · [Split](https://www.mercadopago.com.br/developers/pt/docs/split-payments/integration-configuration/integrate-marketplace) · [Pix Automático](https://www.mercadopago.com.br/blog/pix-automatico-gestao-assinaturas-receita-recorrente)
- Cielo: [Pix Automático](https://docs.cielo.com.br/gateway/docs/pix-automatico) · [Split/Devolução Pix](https://docs.cielo.com.br/split/reference/solicitar-devolu%C3%A7%C3%A3o-pix) · [Taxas 2026](https://blog.cielo.com.br/produtos-e-servicos/taxas-da-cielo/)
- BCB: [Guia Pix Automático](https://liftchallenge.bcb.gov.br/content/estabilidadefinanceira/pix/automatico/guia_pix_automatico.pdf) (LIVE 16/06/2025) · [Adoção 2026](https://www.pagbrasil.com/pt-br/blog/noticias/pix-automatic-2026/)

---

**Princípio:** PaymentGateway compete como **camada banco-direto multi-tenant**, não como PSP. Ganha em CNAB + isolamento por business; o caminho pra "Alto" é fechar os P0 🟡 (refund + webhook hardening) e homologar Pix Automático — **dentro** do PLANO-ONDA5, sem roadmap paralelo.
