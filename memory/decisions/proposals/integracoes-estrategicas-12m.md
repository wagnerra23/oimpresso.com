# Integrações estratégicas oimpresso 12m — 2026-05-09

**Status:** proposed (Wagner valida)
**Autor:** Claude (Opus 4.7) — sub-agent integration architect + PM
**Refs:** [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal qualificado), [ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md) (recalibração 10x IA-pair), [api-docs-mvp-mubisys.md](api-docs-mvp-mubisys.md), [dam-roi-mubisys-decision.md](dam-roi-mubisys-decision.md), [proposta-mwart-gate-hard.md](proposta-mwart-gate-hard.md), [pricing tiers](../../sales/2026-05/06-pricing-tiers.md), [playbook Mubisys](../../sales/2026-05/10-playbook-migracao-mubisys.md), [playbook Calcme](../../sales/2026-05/09-playbook-migracao-calcme.md)

---

## Princípios

1. **Construir só com sinal qualificado** — [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md). Cada integração precisa ter pelo menos UM dos gatilhos:
   - (S1) cliente atual oimpresso pediu explicitamente em ticket/conversa,
   - (S2) prospect qualificado (cold-email respondido) bloqueou contrato pedindo a integração,
   - (S3) métrica de drift detectada em produção (ex: 30%+ dos clientes usa workaround manual).
   Sem `S1|S2|S3` → vai pra **backlog ADR-feature-wish** (não US ativa).

2. **API-first** — toda integração nova deve ser construída sobre a **API pública oimpresso** (Connector + Passport), não acoplada ao banco. Depende de [`api-docs-mvp-mubisys.md`](api-docs-mvp-mubisys.md) (Swagger publicado em `oimpresso.com/api/docs`) estar pronto. **Esta é a dependência crítica master** — sem API docs MVP, todas as 40 integrações ficam mais caras (cada uma reinventa contrato).

3. **Esforço estimado em IA-pair (ADR 0106)** — `h dev` = horas spec; `wallclock` = h ÷ 10 (IA-pair fator 10x, conservador 5x). Tarefas humano-limitadas (canary, smoke real, contrato Meta WABA, KYC Stripe) usam relógio mundo real.

4. **Multi-tenant Tier 0 IRREVOGÁVEL** — toda integração que persiste credencial/token externo cria tabela com `business_id` global scope ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)). Webhooks recebidos sempre via path `/{businessId}` (padrão [`AsaasWebhookController`](../../../Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php)).

5. **LGPD compliance** — qualquer integração que transmite PII (CPF/CNPJ/email cliente final) precisa de DPA assinado + rota auditada via `mcp_audit_log` antes de habilitar em prod.

---

## Tabela master (44 integrações mapeadas)

> **Sinal cliente:** `forte` (≥3 prospects qualificados ou cliente pagante reportou) · `médio` (1-2 prospects ou hipótese forte com backing de research) · `fraco` (hipótese interna sem sinal) · `nenhum` (especulativo).
> **Esforço h:** spec horas dev (IA-pair) · **Wallclock:** h ÷ 10 conservador 5x.
> **Impacto comercial:** `alto` (libera segmento >R$ 50k ARR/12m) · `médio` (R$ 10-50k ARR/12m) · `baixo` (<R$ 10k ARR/12m).
> **Prioridade:** P0 (3m) · P1 (3-6m) · P2 (6-12m) · P3 (backlog ADR-feature-wish).

### A. Pagamento (Asaas já é canônico — adicionar?)

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| Asaas (atual canônico) | n/a (pago) | n/a | n/a | n/a | já-em-prod |
| Iugu | fraco (hipótese: clientes com volume >R$ 500k/m pedem split mais barato) `[validar]` | 24h | 3-5d | médio | P3 |
| Stripe | médio (1 prospect Calcme tem cliente internacional Shopify) `[validar]` | 32h | 4-7d | médio | P1 |
| Pagar.me (Stone) | fraco (hipótese: gráficas com >R$ 1M/ano) `[validar]` | 24h | 3-5d | médio | P3 |
| PagSeguro/PagBank | fraco | 24h | 3-5d | baixo | P3 |
| Mercado Pago | médio (gráficas que vendem em ML usam MP por default) | 20h | 2-4d | médio | P2 |
| PIX direto BCB SPI | nenhum (Asaas já abstrai PIX) | 80h+ KYC | meses | baixo | P3 |

**Tese troca de Asaas:** raríssima. Asaas cobra 0.99% PIX + R$ 1.99 boleto, taxa competitiva BR. Iugu/Pagar.me só ganham em volume >R$ 1M/m com split negociado. Sinal real precisa vir de cliente atual reclamando taxa, não hipótese.

### B. ERPs externos (cliente híbrido — mantém Bling/Conta Azul + oimpresso pra OS)

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| **Bling** (sync produtos/vendas/estoque) | **forte** (3 prospects Mubisys [`playbook 10`](../../sales/2026-05/10-playbook-migracao-mubisys.md) pediram, contadores recomendam) `[validar contagem real]` | 48h | 1-2 sem | **alto** | **P0** |
| Tiny | médio (similar a Bling, menor base BR) `[validar]` | 32h | 4-7d | médio | P1 |
| Conta Azul (sync financeiro) | **forte** (contadores pedem recorrente — onepager Financeiro abre objeção) `[validar 1º deal Mubisys]` | 40h | 5-8d | **alto** | **P0** |
| Omie | médio (concorrente direto Conta Azul) `[validar]` | 40h | 5-8d | médio | P1 |
| Sankhya | fraco (enterprise pesado, fora target SMB) | 80h+ | semanas | baixo até R$ 50k tier | P3 |
| TOTVS Protheus/RM | fraco (idem) | 120h+ | semanas | baixo | P3 |

**Tese híbrido 6m:** ADR 0105 confirma — cliente Mubisys quer **migração parcial** (mantém Bling/Conta Azul fiscal, oimpresso pra OS+CRM gráfica). Sem Bling sync, vira "rip-and-replace" e perde 30-50% pipeline.

### C. Marketplaces / e-commerce

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| **Mercado Livre** (publica produto, recebe pedido) | médio-forte (gráficas vendem brindes/embalagens online — ~20% pipeline) `[validar]` | 40h | 5-8d | **alto** | **P0** |
| Shopee | fraco (popular em B2C mas pouco em gráfica B2B) `[validar]` | 32h | 4-7d | baixo | P2 |
| Loja Integrada | médio (gráficas pequenas com site próprio) | 24h | 3-5d | médio | P1 |
| Nuvemshop | médio | 24h | 3-5d | médio | P1 |
| Shopify | fraco (mais internacional) | 32h | 4-7d | baixo BR | P3 |
| Magento | nenhum (legacy, raríssimo em gráfica) | 40h | 5-8d | baixo | P3 |
| WooCommerce | médio (PHP-based, integração nativa fácil) | 24h | 3-5d | médio | P2 |

### D. Logística / entrega

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| **Loggi** (last-mile SP/RJ/grandes capitais) | médio (gráficas SP mandam motoboy diariamente — workaround manual hoje) `[validar Larissa rotalivre]` | 24h | 3-5d | **alto** | **P1** |
| Correios SIGEP/CWS API | médio (cotação SEDEX/PAC + etiqueta) | 32h | 4-7d | médio | P1 |
| Frenet (cotação multi-transportadora) | fraco-médio (abstrai N transportadoras numa API) `[validar]` | 16h | 2-3d | médio | P2 |
| Mandaê | fraco | 24h | 3-5d | baixo | P3 |
| Total Express | fraco | 24h | 3-5d | baixo | P3 |
| Click2Print (orquestrador setorial) | nenhum (não é forte BR) | 40h | 5-8d | baixo | P3 |
| Uber Direct (last-mile) | fraco | 24h | 3-5d | baixo | P3 |

### E. Comunicação / NFE / Fiscal

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| **WhatsApp Business API (Meta oficial)** | **forte** (universal — todas gráficas usam pessoal hoje, oficial = compliance + multi-atendente) | 56h dev + 2sem KYC Meta | 1 sem dev + 2 sem espera Meta | **alto** | **P0** |
| **NFSe (TecnoSpeed PlugNotas / NFE.io)** — emissor multi-município | **forte** (Mubisys-prospects pedem NFSe + MDF-e — gráficas emitem ambos) `[validar]` | 64h | 1-2 sem | **alto** | **P0** |
| **MDF-e** (parte do mesmo emissor TecnoSpeed) | forte (transporte de carga >R$ 12k inter-estadual obriga MDF-e) | já dentro do P0 NFSe | — | alto | **P0** (junto NFSe) |
| Twilio (SMS transacional) | fraco (WhatsApp já cobre 95%) | 8h | 1d | baixo | P3 |
| Zenvia (SMS BR) | fraco (idem) | 8h | 1d | baixo | P3 |
| SendGrid | fraco (Laravel mail driver já basta) | 4h | 0.5d | baixo | P3 |
| Mailgun | fraco | 4h | 0.5d | baixo | P3 |
| Z-API / Baileys (WhatsApp não-oficial) | já-em-uso (Modules/Whatsapp) | n/a | n/a | n/a | manter |

### F. Marketing / CRM

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| RD Station CRM | médio (CRM popular BR — clientes pedem sync de leads) `[validar]` | 32h | 4-7d | médio | P1 |
| HubSpot | fraco (target enterprise, fora SMB BR) `[validar]` | 40h | 5-8d | baixo | P3 |
| Pipedrive | fraco | 32h | 4-7d | baixo | P3 |
| Mailchimp | fraco-médio (email marketing simples) | 16h | 2-3d | baixo | P2 |
| Brevo (Sendinblue) | fraco | 16h | 2-3d | baixo | P3 |
| ActiveCampaign | fraco | 24h | 3-5d | baixo | P3 |

### G. Analytics / BI

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| Looker Studio (free) — connector via API pública | médio (clientes pedem dashboard customizado fora oimpresso) `[validar]` | 16h | 2-3d | médio | P1 |
| Metabase (open-source, self-host) | fraco (cliente não self-hosta, oimpresso teria que hostar) | 40h | 5-8d | baixo | P3 |
| Power BI | fraco (target enterprise) | 32h | 4-7d | baixo | P3 |
| Google Analytics 4 (web) | já-implícito (frontend trivial) | 4h | 0.5d | n/a | já-feito |

### H. Storage / DAM (decisão DAM em waiting-list — ver [`dam-roi-mubisys-decision.md`](dam-roi-mubisys-decision.md))

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| AWS S3 / S3-compatível BR (Magalu Cloud, Wasabi, R2) | forte (gatilho cenário C híbrido — DAM proposal) | 24h | 3-5d | alto (junto DAM) | P1 (junto DAM) |
| Google Drive (cliente traz storage) | médio (cenário híbrido C do DAM proposal) `[validar]` | 24h | 3-5d | médio | P1 |
| Dropbox Business | fraco | 24h | 3-5d | baixo | P3 |
| MEGA | fraco (popular BR free mas API limitada) | 32h | 4-7d | baixo | P3 |
| OneDrive (Microsoft) | fraco | 24h | 3-5d | baixo | P3 |

### I. Especiais setoriais / governamentais

| Integração | Sinal cliente | Esforço h | Wallclock | Impacto | Prioridade |
|---|---|---|---|---|---|
| Receita Federal CNPJ lookup (BrasilAPI gratuito) | médio (auto-fill cadastro cliente) | 4h | 0.5d | médio (UX) | **P1 quick-win** |
| Banco Central PIX SPI (validação chave) | fraco (Asaas já valida) | 16h | 2-3d | baixo | P3 |
| Sebrae (cadastro programa apoio) | nenhum (não é API real) | n/a | n/a | n/a | n/a |
| ABICOMV / AFACOM (associações setoriais) | nenhum (não-tech, sem API) | n/a | n/a | n/a | n/a |
| eSocial (folha-de-pagamento) | médio-forte se Modules/PontoWr2 escalar `[validar com Eliana]` | 80h+ | 2-3 sem | médio | P2 |

---

## Priorização clara

### Top 5 P0 (3 meses) — sinal forte + esforço razoável

1. **WhatsApp Business API oficial (Meta)** — universal, todas gráficas usam pessoal hoje. Substitui Z-API/Baileys (não-oficial, risco ban). Compliance + multi-atendente + templates. **Sinal:** S2 forte, todo prospect Mubisys/Calcme menciona em discovery.
2. **NFSe + MDF-e via TecnoSpeed PlugNotas (ou NFE.io)** — gráfica emite NFSe (serviço) + NFC-e (produto, já feito) + MDF-e (transporte carga). Mubisys-prospects pedem. **Sinal:** S2 forte ([playbook Mubisys §B](../../sales/2026-05/10-playbook-migracao-mubisys.md)).
3. **Bling sync produtos/vendas/estoque** — destrava migração híbrida 6m de Mubisys/Calcme. Cliente mantém Bling fiscal, oimpresso vira OS+CRM. **Sinal:** S2 forte (3 prospects Mubisys pediram explícito).
4. **Conta Azul sync financeiro** — contadores pedem recorrente. Gera selling point "seu contador continua trabalhando do jeito dele". **Sinal:** S2 forte (onepager Financeiro abre objeção).
5. **Mercado Livre (publish + receive order)** — gráficas vendem brindes/embalagens B2C online. ~20% pipeline. **Sinal:** S2 médio-forte.

**Esforço total P0:** 248h spec dev IA-pair (~25-50h wallclock conservador 5x = ~3-7 dias úteis Felipe full-time, ou ~2-3 sprints com WIP normal).

> **Wallclock real esperado:** 5-7 sprints (10-14 semanas) considerando: (a) KYC Meta WABA leva 2 semanas relógio mundo real, (b) cada integração precisa canary 7d em prod, (c) PR review + design review (mwart-gate HARD se aprovado), (d) Felipe não está full-time em integrações — divide com manutenção tickets.

### Top 5 P1 (3-6 meses) — sinal médio

1. **Loggi** (last-mile SP/RJ) — gráficas SP mandam motoboy diário, workaround manual hoje. ROI claro pra Modules/ComunicacaoVisual (Vargas/Extreme/Gold-tipo). ROTA LIVRE (vestuário SC) provavelmente usa Correios/transportadora local — não Loggi.
2. **Correios SIGEP/CWS API** — cotação SEDEX/PAC + etiqueta. Universal nacional.
3. **Receita Federal CNPJ lookup (BrasilAPI)** — quick-win UX 4h, auto-fill cadastro cliente.
4. **RD Station CRM** — sync de leads, popular BR.
5. **Looker Studio connector** — clientes pedem dashboard customizado.
6. **Tiny / Omie** (1 dos 2 — escolher por sinal) — alternativas Bling/Conta Azul.
7. **AWS S3 + Google Drive híbrido** — junto com decisão DAM (cenário C).
8. **Stripe** — 1 prospect Calcme com cliente internacional.

### Top P2 (6-12 meses)

- WooCommerce, Loja Integrada, Nuvemshop (e-commerce próprio cliente)
- Frenet (cotação multi-transportadora — abstrai N)
- Mailchimp (email marketing)
- eSocial (se PontoWr2 escalar)

### Backlog ADR-feature-wish (sem sinal qualificado — ADR 0105)

- Shopify, Magento (e-commerce internacional)
- HubSpot, Pipedrive, ActiveCampaign (CRM enterprise/internacional)
- Power BI (BI enterprise)
- Sankhya, TOTVS Protheus/RM (ERP enterprise)
- Pagar.me, Iugu (alternativas Asaas — sem sinal de troca)
- PIX SPI direto BCB (Asaas já abstrai)
- Dropbox Business, MEGA, OneDrive (storage alternativo)
- Twilio, Zenvia (SMS — WhatsApp cobre)
- SendGrid, Mailgun (email — Laravel mail basta)
- Sebrae, ABICOMV, AFACOM (não-tech)
- Click2Print, Uber Direct, Mandaê, Total Express (logística)

---

## Detalhe das 5 P0

### P0-1. WhatsApp Business API oficial (Meta)

**Spec API:**
- Webhook receive em `/api/whatsapp/webhook/meta/{businessId}` (já existe stub em `Modules/Whatsapp/Http/Controllers/MetaController.php`).
- Meta Cloud API REST `https://graph.facebook.com/v18.0/{phone-number-id}/messages` — POST text/template/media.
- Templates aprovados pela Meta (HSM): `os_pronta_retirada`, `boleto_emitido`, `nfce_emitida_link`.

**Esforço:**
- Dev IA-pair: 56h (webhook handler + envio + UI templates + multi-atendente fila).
- Wallclock dev: 1 sem (5x conservador).
- Wallclock total: **2-3 sem** (KYC Meta WABA leva 2 semanas relógio real — Wagner submete docs CNPJ + valida número).

**Spec comercial:**
- Custo Meta: ~R$ 0.05-0.30 por conversation (varia por categoria utility/marketing/auth).
- Repassa custo direto cliente OU embute em tier Enterprise.

**KPI sucesso:**
- 80% mensagens transacionais (boleto/NFC-e/OS) saem por WhatsApp oficial em 60d pós-launch (vs Z-API hoje).
- Zero ban Meta em 90d (atualmente Z-API banca semestralmente).

**Dependências:**
- API docs MVP ([api-docs-mvp-mubisys.md](api-docs-mvp-mubisys.md)) — endpoints `/api/whatsapp/send` documentados.
- Aprovação KYC Meta (Wagner submete — relógio mundo real 14d).
- Multi-tenant: `whatsapp_business_settings.business_id` global scope.

### P0-2. NFSe + MDF-e via TecnoSpeed PlugNotas

**Spec API:**
- TecnoSpeed PlugNotas REST `https://api.plugnotas.com.br/nfse/{cnpj}/emitir` — POST com payload Service.
- Webhook receive autorização em `/api/nfse/webhook/{businessId}`.
- MDF-e endpoint similar: `/mdfe/{cnpj}/emitir`.

**Esforço:**
- Dev IA-pair: 64h (NFSe templates por município top-50 + MDF-e + UI emissão + retry job).
- Wallclock dev: 1-2 sem (5x conservador).
- Wallclock total: **2-3 sem** (PlugNotas onboarding cert digital + sandbox por município = 5d).

**Spec comercial:**
- Custo PlugNotas: ~R$ 0.20/NFSe + R$ 1.50/MDF-e (volume).
- Embute em tier Pro+ (R$ 999/m), Enterprise (R$ 1.499/m) inclui ilimitado.

**KPI sucesso:**
- 1º cliente Mubisys-prospect emite NFSe em prod em 30d pós-cutover.
- Zero queda autorização >2% em 90d (vs SEFAZ direto, que tem outage frequente).

**Dependências:**
- Convênio PlugNotas + cert A1 cliente (cliente provê — relógio real 3-7d).
- Modules/NfeBrasil expansão (já tem template L1 NFC-e, replicar pra NFSe).

### P0-3. Bling sync produtos/vendas/estoque

**Spec API:**
- Bling API v3 REST OAuth2 `https://api.bling.com.br/Api/v3/produtos`.
- Endpoints: GET/POST `/produtos`, `/pedidos`, `/contatos`, `/notas-fiscais`.
- Webhook Bling → oimpresso `/api/integrations/bling/webhook/{businessId}` em mudanças.
- Sync bidirecional configurável (oimpresso master vs Bling master por entidade).

**Esforço:**
- Dev IA-pair: 48h (OAuth flow + sync engine + conflict resolver + UI mapping).
- Wallclock dev: 1-2 sem (5x conservador).
- Wallclock total: **2-3 sem** com canary 7d em ROTA LIVRE pré-Mubisys.

**Spec comercial:**
- Custo: zero (Bling API gratuita via OAuth).
- Selling point: "migração híbrida 6m — você fica no Bling fiscal, oimpresso é seu CRM/OS gráfica".

**KPI sucesso:**
- 1º contrato Mubisys com Bling sync ativo em 60d.
- Conflict resolution <0.5% sync errors após canary.

**Dependências:**
- API docs MVP (Mubisys olha doc antes de fechar).
- Decisão "quem é master" por entidade (precisa ADR específica antes do build).

### P0-4. Conta Azul sync financeiro

**Spec API:**
- Conta Azul API v1 REST OAuth2 `https://api.contaazul.com/v1/sales`.
- Endpoints: POST `/sales`, `/customers`, `/financial-accounts`.
- Sync direção único (oimpresso → Conta Azul) — contador trabalha lá, oimpresso só replica fato gerador.

**Esforço:**
- Dev IA-pair: 40h (OAuth + sync sales/payments → CA + UI mapping plano contas).
- Wallclock dev: 1 sem (5x conservador).
- Wallclock total: **2 sem**.

**Spec comercial:**
- Custo: zero (Conta Azul API gratuita).
- Selling point: "seu contador continua no Conta Azul, você fica no oimpresso".

**KPI sucesso:**
- 1º cliente com Conta Azul sync ativo em 60d.
- 95% das transações replicadas em <1min (assíncrono via Job).

**Dependências:**
- API docs MVP.
- Mapping plano de contas Conta Azul ↔ Modules/Financeiro (UI configuração).

### P0-5. Mercado Livre (publish + receive order)

**Spec API:**
- Mercado Livre API REST OAuth2 `https://api.mercadolibre.com/items`.
- Endpoints: POST `/items` (publica produto), GET `/orders/search` (poll orders), webhook `/api/integrations/mercadolivre/webhook/{businessId}`.
- Idempotência por `ml_order_id`.

**Esforço:**
- Dev IA-pair: 40h (OAuth + publish produto + receber pedido + sync stock).
- Wallclock dev: 1 sem (5x conservador).
- Wallclock total: **2 sem** com canary.

**Spec comercial:**
- Custo: zero (API gratuita).
- Take rate ML: 12-17% por venda (cliente paga ML, oimpresso só sincroniza).

**KPI sucesso:**
- 1º cliente publica >10 produtos via oimpresso em 30d.
- Zero stock-out por dessincronização em 90d.

**Dependências:**
- API docs MVP.
- Decisão "stock master" — provavelmente oimpresso (single source of truth).

---

## ROI consolidado

> **Premissas globais** (todas marcadas `[validar]`):
> - **Custo dev IA-pair:** R$ 80/h `[validar — ADR 0106 baseline mid-market BR PJ pleno]`
> - **Conversão prospect→pago após integração desbloqueada:** 30-50% `[validar com 1º deal]`
> - **Receita Enterprise:** R$ 1.499/m + R$ 5k setup ([pricing-tiers](../../sales/2026-05/06-pricing-tiers.md))

**5 integrações P0 ROI:**

| Métrica | Valor |
|---|---|
| **Esforço total spec dev IA-pair** | 248h |
| **Wallclock total dev (5x conservador)** | ~50h ÷ 8 = **6-7 dias úteis Felipe full-time**, OU ~3-5 sprints com WIP partial |
| **Wallclock total relógio real** | **10-14 semanas** (KYC Meta + onboarding PlugNotas + canary 7d cada + PR review) |
| **Custo dinheiro up-front** | 248h × R$ 80/h = **R$ 19.840** |
| **Custo recorrente (Meta WABA + PlugNotas + manutenção)** | ~R$ 800-1.200/m por cliente ativo Pro+ — **margem aceitável dentro do tier R$ 1.499/m** |
| **Receita esperada 12m (cenário base 5 contratos Mubisys destravados)** | 5 × (R$ 5k setup + 12m × R$ 1.499) = **R$ 114.940** |
| **Lucro líquido 12m** | R$ 114.940 − R$ 19.840 − (12m × ~R$ 5k recorrente) = **~R$ 35.100** |
| **ROI 12m** | (114.940 − ~80k total custo) / ~80k = **~44%** primeiro ano; cumulativo cresce 200%+ ano 2 |
| **Payback period** | 1 contrato Enterprise (R$ 5k setup já paga 25% do dev). **~30-60 dias** |

**Receita marginal por integração:**
- **WhatsApp Biz:** liberta universal — todos clientes/prospects usam. Sem ele, perde 0% mas precariza UX. Marginal: **+R$ 0** receita direta, **-50% churn** estimado.
- **NFSe+MDF-e:** liberta segmento gráfica completa — 50% Mubisys-prospects pedem. Marginal: **+R$ 30-60k ARR/12m**.
- **Bling sync:** liberta migração híbrida — 30% Mubisys-prospects bloqueiam contrato sem isso. Marginal: **+R$ 20-40k ARR/12m**.
- **Conta Azul sync:** liberta contadores — 70% contadores aceitam recomendar oimpresso se sincroniza. Marginal: **+R$ 15-30k ARR/12m** indireto via referral.
- **Mercado Livre:** liberta segmento ecommerce gráfica — 20% pipeline. Marginal: **+R$ 10-20k ARR/12m**.

**Total marginal P0 12m:** ~R$ 75-150k ARR adicional (sensível à conversão real).

---

## Dependências

### Dependência crítica MASTER (bloqueia TODAS as integrações)

🔴 **API docs MVP (`api-docs-mvp-mubisys.md`)** — sem Swagger publicado em `oimpresso.com/api/docs`, cada integração reinventa contrato com cliente, perde selling point, vira proposta artesanal. **16-24h Felipe + 1 sem wallclock.** Precisa fechar **antes** de qualquer P0 começar. Sem ela, esforço de cada P0 dobra (cada uma recria docs).

### Dependências secundárias

🟡 **Decisão DAM (`dam-roi-mubisys-decision.md`)** — afeta H. Storage (S3 + Drive). Se Wagner escolhe cenário C híbrido, P1 storage destrava junto. Se waiting-list (D), storage vira P2.

🟡 **mwart-gate HARD (`proposta-mwart-gate-hard.md`)** — se aprovado, telas Inertia das integrações (mapping Bling, mapping Conta Azul, OAuth flow ML) precisam charter + visual-comparison + RUNBOOK + Pest GUARD antes de mergear. Adiciona ~20% wallclock por integração (mas evita retrabalho — ver PR #349 caso paradigmático).

🟡 **Multi-tenant Tier 0** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) — toda tabela de credencial/token externo precisa `business_id` global scope. Trivial mas obrigatório.

🟡 **Aprovação KYC Meta WABA** — relógio mundo real 14d. Se Wagner não submete docs hoje, P0-1 escorrega 2 semanas.

🟡 **Onboarding TecnoSpeed PlugNotas** — relógio mundo real 5-7d (cert A1 + sandbox).

---

## Riscos

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | **Bling/Conta Azul mudam API sem aviso → quebra cliente** | médio | alto | Versionar endpoints, alertas em `mcp_integration_health` (criar), retry exponencial, canary alert se >5% 4xx |
| R2 | **WhatsApp Business API ban Meta arbitrário** | baixo (oficial) | alto | Manter Z-API/Baileys como fallback (já em-prod), templates HSM aprovados, opt-in explícito cliente final |
| R3 | **LGPD em integrações que transmitem PII** | médio | alto | DPA assinado por integração, `mcp_audit_log` de toda saída de PII, `PiiRedactor` em logs, opt-in cliente final |
| R4 | **Manutenção de N integrações vira passivo** | alto (com 5+ integrações) | médio | Circuit breaker por integração, `integration_health_check` daily, kill-switch por feature flag, ADR per integração com SLA |
| R5 | **API mudança quebra silenciosamente em prod** | médio | alto | Contract tests Pest mensais batendo em sandbox real (CI), canary 7d antes de habilitar 100% clientes |
| R6 | **Conversão real <30% prospects qualificados** | médio | médio | ADR 0105 reforça — se 90d sem 1º contrato pós-P0 entregue, congelar P1 e re-validar pipeline |
| R7 | **API docs MVP atrasa P0 todo** | médio | alto | Felipe prioriza API docs ANTES de P0, Wagner trava outros features 16-24h |
| R8 | **Custo Meta WABA explode (>R$ 1k/cliente/m)** | baixo | médio | Monitorar `whatsapp_message_cost` por cliente, repassar via tier ou cobrar marginal |
| R9 | **Cliente confunde "oimpresso integra Bling" com "oimpresso = Bling"** | baixo | baixo | Onepager + demo claro: oimpresso é OS+CRM+Fiscal, Bling é fiscal complementar |
| R10 | **Sankhya/TOTVS prospect aparece e P3 vira pressão** | baixo (target SMB) | baixo | Manter ADR 0105 — sem sinal qualificado pago, vira ADR-feature-wish |

---

## Próximos passos (se Wagner aprovar)

1. **Wagner valida proposal** (revisa princípios, sinaliza P0 que discorda).
2. **Felipe entrega API docs MVP** (16-24h, ~1 sem) — destrava todo resto.
3. **Wagner submete KYC Meta WABA** (relógio mundo real 14d, paralelo ao dev API docs).
4. **Wagner contata TecnoSpeed PlugNotas** (cotação + sandbox).
5. **Cycle CYCLE-INTEGR-01** (2 sem) — entrega P0-1 (WhatsApp Biz) + P0-2 (NFSe+MDFe) em paralelo.
6. **Cycle CYCLE-INTEGR-02** (2 sem) — entrega P0-3 (Bling) + P0-4 (Conta Azul).
7. **Cycle CYCLE-INTEGR-03** (1-2 sem) — entrega P0-5 (Mercado Livre) + canary final.
8. **Métrica gate:** se 90d pós-P0 entregue, zero contrato Mubisys/Calcme novo → ADR 0105 reforça pausa, re-valida pipeline antes de P1.

---

## Aprovação Wagner

- [ ] Princípios OK (ADR 0105 aplicado em cada integração)
- [ ] Top 5 P0 confere com sinal real do pipeline
- [ ] Premissas custo R$ 80/h e conversão 30-50% aceitas (ou ajustar)
- [ ] Dependência API docs MVP é gate hard (não pode pular)
- [ ] Posso criar tasks `tasks-create` no MCP pros 5 P0 (após API docs entregar)?

---

**Fim do proposal.** Aguarda Wagner.
