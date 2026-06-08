# Proposta — API "Score Gráfica BR" como Data Product

**Status:** rascunho / discovery
**Autor:** Claude (BD+API architect) + Wagner
**Data:** 2026-05-09
**Tipo:** business model + spec técnica
**Origem:** dados financeiros REAIS de 37 clientes OfficeImpresso (26 anos histórico, 132k empresas pagantes finais, 12 UFs diretas)

---

## TL;DR

Vender via API REST um **score 0-1000 de risco/saúde de gráfica brasileira** baseado em dados que **só a oimpresso tem** (histórico 26 anos de pagamento de 37 clientes diretos + 132k empresas que pagaram a essas 37 gráficas). Mercado endereçável: fintechs (Asaas/Iugu/Stone), seguradoras (Porto/Tokio), fornecedores de máquina (Roland/HP/Mimaki), insumos (3M/Avery), associações (ABIGRAF).

**Cenário realista 24m: R$ 250k ARR adicional** (2 fintechs Starter + 2 fornecedores Pro + 1 seguradora pay-per-use). Validação via 1 piloto fintech de R$ 0 com data subset.

**Risco regulatório principal: LGPD ANPD** — vender score de PJ sem opt-in explícito tecnicamente cabe em "interesse legítimo" (Art. 7º IX), mas exige LIA formal + DPIA + jurídico forte; mitigar com (a) opt-in dos 37 clientes diretos contratualmente, (b) anonimização agregada como default, (c) score CNPJ-específico só com base legal documentada.

---

## 1. Produto core: "Score Gráfica BR"

### 1.1 Endpoint principal

```
GET /api/v1/score/{cnpj}
Authorization: Bearer <oauth2_token>
```

**Response 200:**

```json
{
  "cnpj": "12.345.678/0001-90",
  "score": 742,
  "tier": "B",                  // A (900+) / B (700-899) / C (500-699) / D (300-499) / E (<300)
  "computed_at": "2026-05-09T14:30:00-03:00",
  "components": {
    "payment_history": {
      "weight": 0.30,
      "value": 820,
      "explain": "27 meses sem atraso > 7 dias; 2 atrasos curtos em 36m"
    },
    "mrr_estimate": {
      "weight": 0.20,
      "value": 680,
      "explain": "MRR estimado R$ 18-25k/mês (faixa Pro), volume 2.4k OS/mês"
    },
    "gmv_proxy": {
      "weight": 0.20,
      "value": 750,
      "explain": "GMV proxy R$ 280k/mês via boletos pagos a essa gráfica em 12m"
    },
    "delinquency": {
      "weight": 0.15,
      "value": 720,
      "explain": "% atraso médio dos clientes finais: 8.2% (mercado: 12%)"
    },
    "geography": {
      "weight": 0.10,
      "value": 700,
      "explain": "SP capital — região saudável; sem concentração risco"
    },
    "churn_risk": {
      "weight": 0.05,
      "value": 880,
      "explain": "Cliente desde 2018; renovou 7x; uso ativo módulos core"
    }
  },
  "alerts": [
    {
      "code": "MRR_DECLINE_3M",
      "severity": "warning",
      "message": "MRR caiu 12% nos últimos 3 meses (R$ 22k → R$ 19.5k)"
    }
  ],
  "data_freshness": {
    "last_payment_observed": "2026-05-08",
    "last_invoice_observed": "2026-05-09",
    "stale_days": 1
  },
  "consent": {
    "basis": "interesse_legitimo",
    "lia_ref": "LIA-2026-001",
    "opt_in_available": false
  }
}
```

**Response 404** (CNPJ fora da base):

```json
{
  "error": "cnpj_not_in_panel",
  "message": "CNPJ não está no painel observado de 37 gráficas + 132k clientes finais",
  "fallback_suggestions": [
    "GET /api/v1/score/aggregate?uf=SP&segment=fachada",
    "GET /api/v1/score/peer-comparison?cnpj_peer=...&uf=SP"
  ]
}
```

### 1.2 Endpoints auxiliares

| Endpoint | Use case |
|---|---|
| `GET /api/v1/score/{cnpj}/history?months=12` | Série temporal mensal do score |
| `GET /api/v1/score/aggregate?uf=SP&segment=fachada` | Score médio por segmento+UF (anônimo, sem opt-in) |
| `GET /api/v1/peer-comparison/{cnpj}` | Posiciona o CNPJ no decil dentro de UF+segmento |
| `GET /api/v1/market/heatmap?metric=delinquency&period=2026-Q1` | Heatmap geográfico (anônimo agregado) |
| `POST /api/v1/webhooks/subscribe` | Webhook quando score mudar > X pontos |
| `GET /api/v1/audit/{query_id}` | Trilha de auditoria de consulta (LGPD) |

### 1.3 Componentes do score (pesos)

| Componente | Peso | Fonte de dados |
|---|---:|---|
| **Payment history** (atrasos contas próprias da gráfica) | 30% | `transactions` UltimatePOS dos 37 clientes |
| **MRR estimate** (MRR pago à oimpresso) | 20% | `recurring_billing` faturamento mensal |
| **GMV proxy** (volume de boletos pagos pela gráfica aos clientes finais) | 20% | `transaction_payments` dos 132k clientes finais |
| **Delinquency rate** (% atraso dos clientes finais da gráfica) | 15% | Agregado dos 132k pagantes |
| **Geography** (UF/cidade/concentração) | 10% | `business.location` |
| **Churn risk** (uso recente, módulos ativos, renovação) | 5% | `mcp_audit_log` + `subscription_events` |

> **Explainable AI obrigatório.** Cliente recebe breakdown por componente — não black-box. Modelo é regressão logística + features engineered (não ML profundo) precisamente pra ser explicável e auditável pela ANPD.

---

## 2. Persona-alvo (5 customers tipo)

### Persona 1 — Fintech (Asaas, Iugu, Stone, BlueShift)

- **Dor:** querem oferecer empréstimo / crédito de máquina / antecipação de recebíveis pra gráficas, mas Serasa Experian não tem score vertical e dados de PJ pequena são ruins
- **Volume estimado:** 200 consultas/mês (uma fintech mid)
- **Willingness to pay:** R$ 5/consulta (confirmar via piloto)
- **Decisor:** Head of Risk + CTO
- **Sales motion:** outbound LinkedIn → demo 30min → piloto 90d com data subset → contrato anual
- **Timeline pra fechar:** 4-6 meses

### Persona 2 — Seguradora (Porto Seguro, Tokio Marine, Bradesco Seguros)

- **Dor:** seguro de equipamento gráfico (plotter R$ 250k, impressora UV R$ 800k) tem alta sinistralidade — oficina fecha, equipamento "some". Underwriting cego.
- **Volume estimado:** 50-100 consultas/mês por seguradora
- **Willingness to pay:** R$ 50/consulta (alto valor unitário)
- **Decisor:** Head of Underwriting PJ
- **Sales motion:** intro via corretor especializado → POC pré-cotação → underwriting team aprova
- **Timeline pra fechar:** 6-9 meses (compliance interno é lento)

### Persona 3 — Fornecedor de máquina (Roland DG, HP Latex, Mimaki, Mutoh)

- **Dor:** vendem plotter/impressora R$ 80k-800k em 36-60x parceladas; querem prever inadimplência + identificar gráficas crescendo (quem precisa de máquina nova)
- **Volume estimado:** dataset bulk monthly + alertas
- **Willingness to pay:** R$ 200/mês unlimited consultas + R$ 2k bulk export
- **Decisor:** Head of Sales BR + Risco
- **Sales motion:** parceria estratégica (oimpresso vira "channel partner" da Roland) → pode virar revenue share
- **Timeline pra fechar:** 8-12 meses (multinacional decide devagar)

### Persona 4 — Fornecedor de insumo (3M, Avery Dennison, Heytex, Sihl)

- **Dor:** distribuição capilar — querem saber QUAIS gráficas estão crescendo em QUAIS cidades pra dirigir esforço comercial dos representantes
- **Volume estimado:** dashboards mensais + alertas trimestrais
- **Willingness to pay:** R$ 500/mês acesso completo + R$ 5k relatório customizado
- **Decisor:** Marketing Intelligence + Head of Channel Sales
- **Sales motion:** congresso ABIGRAF → demo → piloto regional (1 estado) → expansão
- **Timeline pra fechar:** 6-9 meses

### Persona 5 — Associação setorial (ABIGRAF, ABICOMV, SINDIGRAF)

- **Dor:** publicam relatório anual setorial mas com survey de 200 respondentes — qualidade ruim. Querem dados duros.
- **Volume estimado:** 1 relatório anual customizado + acesso continuado
- **Willingness to pay:** R$ 50k/ano (relatório + dashboard)
- **Decisor:** Diretor Executivo + Conselho
- **Sales motion:** apresentação em assembleia → comissão técnica → contrato anual
- **Timeline pra fechar:** 12 meses (ciclo associativo lento)

---

## 3. Spec técnica

### 3.1 Stack

- **Runtime:** FrankenPHP em CT 100 Proxmox (não Hostinger — daemons + alta concorrência)
- **Framework:** Laravel 13.6 (mesmo do oimpresso) + Modules/ScoreGrafica
- **Auth:** OAuth2 client_credentials grant (Laravel Passport)
- **Rate limit:** Redis token bucket por client_id
- **Cache:** Redis 6h TTL para scores estáveis, 5min pra ativos
- **Observabilidade:** OTel → Grafana (latency p50/p95/p99, error rate, cache hit ratio)
- **Documentação:** Scribe pública em `score-api.oimpresso.com/docs`
- **Domain isolado:** `score-api.oimpresso.com` — separar do core ERP

### 3.2 Auth & scopes

```
POST /oauth/token
{
  "grant_type": "client_credentials",
  "client_id": "...",
  "client_secret": "...",
  "scope": "score:read aggregate:read webhook:write"
}
```

| Scope | Descrição |
|---|---|
| `score:read` | Consultar score por CNPJ |
| `score:read:bulk` | Bulk export (Pro+) |
| `aggregate:read` | Métricas agregadas anônimas |
| `webhook:write` | Subscribe a updates |
| `audit:read` | Cliente vê próprio audit trail |

### 3.3 Rate limiting por tier

| Tier | Burst | Sustained | Concurrent |
|---|---:|---:|---:|
| Trial | 10/min | 100/dia | 2 |
| Starter | 60/min | 5.000/mês | 5 |
| Pro | 200/min | 50.000/mês | 20 |
| Enterprise | 1000/min | unlimited | 100 |

429 retorna `Retry-After` header.

### 3.4 Webhooks

```
POST /api/v1/webhooks/subscribe
{
  "event_types": ["score.changed", "score.threshold_crossed", "alert.created"],
  "filter": { "min_score_delta": 50, "tier_change_only": true },
  "callback_url": "https://fintech-x.com/oimpresso-webhook",
  "secret": "..."
}
```

Payload assinado HMAC-SHA256. Retry com backoff exponencial (5min/30min/4h/24h) e DLQ após 4 tentativas.

### 3.5 SDKs oficiais

- **Python:** `pip install oimpresso-score` (aiohttp + pydantic)
- **Node:** `npm install @oimpresso/score-sdk` (axios + zod)
- **PHP:** opcional v2

Geração via OpenAPI 3.1 spec → `openapi-generator-cli`.

### 3.6 Modelo de dados (pipeline)

```
[37 oimpresso clients DBs]
    ↓ daily ETL (cron CT 100)
[score_grafica.raw_facts]  ← business_id-segregated
    ↓ feature engineering
[score_grafica.features_monthly]
    ↓ logistic regression scoring
[score_grafica.scores]  ← CNPJ-level com versioning
    ↓ explainability layer
[score_grafica.explanations]
    ↓ API serving + cache
```

Tabelas isoladas em schema próprio `score_grafica` — não tocar `transactions` core. Read-only views agregadoras.

### 3.7 Latency budget

| Endpoint | p50 | p95 | p99 |
|---|---:|---:|---:|
| `GET /score/{cnpj}` (cached) | 30ms | 80ms | 200ms |
| `GET /score/{cnpj}` (cold) | 200ms | 500ms | 1000ms |
| `GET /aggregate/...` | 100ms | 300ms | 600ms |
| Webhook delivery | 1s | 5s | 30s |

---

## 4. LGPD compliance

### 4.1 Bases legais aplicáveis (Art. 7º LGPD)

| Cenário | Base legal | Documento exigido |
|---|---|---|
| Score CNPJ-específico vendido a fintech | **VI — exercício regular de direito** + **IX — interesse legítimo** | LIA (Legitimate Interest Assessment) + DPIA |
| Agregados anônimos (UF/segmento) | Não há tratamento de dado pessoal | OK irrestrito |
| Score com CNPJ titular (ex: gráfica X consultando score próprio) | **I — consentimento** | Termo opt-in claro |
| Dados públicos (Receita Federal, Junta Comercial) | **IV — exercício regular de direito** | OK |

> **Pessoa Jurídica vs Pessoa Física.** LGPD aplica-se primariamente a dados de pessoa natural. Dados puramente de PJ (CNPJ, razão social, faturamento) **não são dados pessoais**. **Mas:** se a query revela info do sócio (PJ unipessoal, CNPJ MEI = CPF do sócio), volta a ser pessoal. **Mitigação:** filtrar MEIs (até bater 1 LIA específica) + nunca expor sócios PF no payload.

### 4.2 Controles obrigatórios

- ✅ **LIA documentada** com 3 elementos: legitimidade do interesse, necessidade, balanceamento direitos
- ✅ **DPIA** (Data Protection Impact Assessment) antes do go-live — exigido pela ANPD
- ✅ **Audit log imutável** de toda consulta (`mcp_audit_log` style — append-only + hash chain)
- ✅ **DPO designado** (pode ser jurídico externo retainer R$ 3-5k/mês)
- ✅ **Auditoria externa anual** (BDO/EY/PwC LGPD) — R$ 25-50k/ano
- ✅ **Transparência ativa:** página pública `/lgpd/score-grafica-br` listando bases legais, finalidades, prazo de retenção, contato DPO
- ✅ **Direito de oposição:** CNPJ pode pedir remoção via formulário → fica em `score_excluded` table → API retorna 404
- ✅ **Contratos com clientes API com cláusula DPA** (Data Processing Agreement) — fintech cliente também responde solidariamente

### 4.3 Riscos LGPD específicos

| Risco | Probabilidade | Mitigação |
|---|---|---|
| ANPD multa por falta de LIA documentada | Média | LIA antes do go-live + revisão jurídica externa |
| CNPJ titular reclama vazamento | Baixa | Direito de oposição + audit trail + DPA com cliente API |
| Cliente API revende dados | Média | DPA proibitiva + watermark per-request + rate limit detect |
| MEI unipessoal vira "dado pessoal" | Alta | Filtrar todos MEIs do dataset enquanto não houver opt-in específico |

---

## 5. Pricing tiers da API

| Tier | Mensal | Setup | Consultas/mês | Bulk | SLA | Suporte | Use case |
|---|---:|---:|---:|---|---|---|---|
| **Trial** | R$ 0 | R$ 0 | 100 | ❌ | best-effort | community | discovery, devs avaliando |
| **Starter** | R$ 299 | R$ 0 | 5.000 | ❌ | 99% | email 48h | startup fintech early-stage |
| **Pro** | R$ 999 | R$ 1.500 | 50.000 | 1×/mês | 99.5% | email 24h | mid-fintech, fornecedor máquina |
| **Enterprise** | R$ 4.999+ | R$ 5.000 | unlimited | sob demanda | 99.9% | dedicated | corp, seguradora, associação |

**Add-ons:**
- Webhook firehose: +R$ 500/mês
- SDK custom: R$ 2.500 one-time
- Relatório customizado: R$ 5.000-50.000
- Dashboard branded: R$ 1.500/mês

**Pagamento:** Asaas (parceria potencial — confirmar) — boleto/cartão/PIX. Anual com 15% desconto.

---

## 6. Modelo de receita 24m

### 6.1 Cenário pessimista — R$ 60k ARR

| Customer | Tier | ARR |
|---|---|---:|
| 1 fintech | Starter | R$ 3.6k |
| 1 fornecedor insumo | Pro | R$ 12k |
| 0 seguradoras | — | R$ 0 |
| 0 associações | — | R$ 0 |
| Pay-per-use sparse | — | R$ 5k |
| **Total** | | **~R$ 21k → R$ 60k** (com upsell ano 2) |

Premissa: ciclo de venda longo, validação demora 12m, 2 customers ano 1 + 2 ano 2.

### 6.2 Cenário realista — R$ 250k ARR ⭐

| Customer | Tier | ARR |
|---|---|---:|
| 2 fintechs | 1 Starter + 1 Pro | R$ 15.6k |
| 2 fornecedores | 1 Pro + 1 Enterprise base | R$ 72k |
| 1 seguradora | Pay-per-use 50/mês × R$ 50 | R$ 30k |
| 0 associações | — | R$ 0 |
| Add-ons + relatórios custom | — | R$ 80k |
| Bulk exports | — | R$ 50k |
| **Total** | | **~R$ 250k** |

Premissa: 1 piloto fintech fecha em 6 meses, vira referência, abre mais 2 logos. 1 fornecedor insumo viceja ano 1, 1 fornecedor máquina entra ano 2.

### 6.3 Cenário otimista — R$ 800k ARR

| Customer | Tier | ARR |
|---|---|---:|
| 5 fintechs | 2 Starter + 2 Pro + 1 Enterprise | R$ 90k |
| 3 fornecedores | 2 Pro + 1 Enterprise | R$ 100k |
| 2 seguradoras | 1 Enterprise + 1 pay-per-use | R$ 120k |
| 1 associação | Custom | R$ 50k |
| Bulk + add-ons + custom reports | — | R$ 200k |
| Channel revenue share Roland | — | R$ 240k |
| **Total** | | **~R$ 800k** |

Premissa: parceria estratégica Roland fecha (revenue share 5% vendas máquinas), virando referência setorial. ABIGRAF contrata. Imprensa especializada cobre.

---

## 7. Validation pre-launch (4 fases)

### Fase 1 — Piloto fintech (mês 0-3) ⭐ MVP

- Escolher **1 fintech** com baixo ego e velocidade alta — **Asaas** é candidato natural (já está no radar via integração financeira existente)
- Subset de dados: 5 clientes oimpresso top + 5k pagantes finais agregados
- Free tier — fintech testa risk-scoring real contra modelo deles
- Métrica de sucesso: **uplift > 8%** em discriminação de inadimplência vs baseline Serasa

### Fase 2 — ROI provado (mês 3-6)

- Fintech publica (com permissão) número agregado: "API X reduziu nossa default em Y%"
- Caso público em blog técnico oimpresso + apresentação em evento (SlushBR/Web Summit Rio)

### Fase 3 — Outreach gerado (mês 6-9)

- Contratar SDR part-time (R$ 4-6k/mês)
- Lista 200 prospects (50 fintechs + 30 seguradoras + 30 fornecedores + 90 long-tail)
- Sequência outbound 4 touchpoints + LinkedIn + email
- KPI: 5 demos/semana, 1 piloto novo/mês

### Fase 4 — Sales deck + comercial (mês 9-12)

- Sales deck profissional (12 slides: dor + solução + dados únicos + ROI + pricing + caso + roadmap)
- Onboarding self-service no Trial tier
- Programa de afiliados (advisors fintech recebem 10% MRR ano 1)

---

## 8. Riscos & mitigações

### 8.1 Concorrente entra no nicho

- **Quem:** Serasa Experian, Boa Vista SCPC, Quod
- **Probabilidade:** baixa-média ano 1, alta ano 2-3
- **Mitigação:**
  - **Moat:** dados primários (oimpresso opera as gráficas), não derivados — Serasa só tem sinais externos
  - **Vertical específico:** comunicação visual é nicho onde Serasa não tem expertise
  - **Speed:** ser primeiro a publicar caso bem-sucedido cria efeito Lindy
  - **Parceria estratégica** com associação (ABIGRAF) — vira "score oficial setorial"

### 8.2 LGPD ANPD multar

- **Probabilidade:** média (ANPD ativa em fiscalização desde 2024)
- **Multa máxima:** 2% faturamento até R$ 50M
- **Mitigação:**
  - LIA + DPIA obrigatórios pré-lançamento
  - DPO retainer
  - Auditoria externa anual
  - Filtrar MEIs até bater LIA específica
  - Transparência ativa
  - Pólice de seguro cyber/D&O — R$ 2-5k/ano cobre R$ 500k

### 8.3 Cliente fintech vazar dado

- **Probabilidade:** baixa-média (fintechs têm SOC2 mas erros acontecem)
- **Mitigação:**
  - DPA contratual com cláusula de responsabilidade
  - Watermark per-request (consulta tem assinatura única)
  - Audit trail detectável (queries em padrão suspeito → alerta)
  - Right to terminate cláusula
  - NDA reforçada no tier Enterprise

### 8.4 Modelo "winner-take-all" mas mercado pequeno

- **Realidade:** 8-10k gráficas no Brasil, mercado endereçável fintech-side ~R$ 5-10M ARR teto
- **Mitigação:**
  - Não tratar como "unicórnio" — tratar como **vertical SaaS data product** sustentável
  - Expandir lateral: oficina mecânica auto, salão de beleza, padaria — mesmo padrão de score vertical
  - Score Gráfica BR é o **proof-of-concept** pra "Score Vertical BR" — plataforma multi-vertical

### 8.5 37 clientes não autorizam contratualmente

- **Probabilidade:** média (Larissa/ROTA LIVRE pode resistir)
- **Mitigação:**
  - Cláusula de uso de dados agregados anônimos no contrato master oimpresso (já em vigor padrão SaaS)
  - Opt-in explícito pra dados CNPJ-identificados — oferecer **revenue share 10%** pros clientes que aceitarem (alinhamento de interesse)
  - Segregar funcionalidade: clientes que opt-out, score deles fica anônimo agregado; opt-in vai pro core comercial

---

## 9. Próximos passos imediatos (30 dias)

1. **Wagner valida** este rascunho — decide se vai pra DPIA pré-discovery ou descarta
2. **Conversa exploratória com 3 fintechs** (Asaas, Iugu, mais 1) — só descoberta, sem demo
3. **Jurídico externo** lê Art. 7º + dá opinião escrita sobre base legal viável (R$ 3-5k one-time)
4. **Contratualmente** — adicionar cláusula opt-out aos 37 clientes oimpresso (envio comunicado + 30d)
5. **Spike técnico 1 semana** — montar API mock retornando score sintético em CT 100 staging pra demo
6. **ADR canon** — se Wagner aprovar, criar `0xxx-data-product-api-score-grafica-br.md` com decisão e link pra esta proposta

---

## 10. Decisão pendente Wagner

| Pergunta | Opções |
|---|---|
| Vai discovery formal? | (a) sim, próximas 4 semanas (b) parking lot 6 meses (c) descarta |
| Qual fintech abordar primeiro? | (a) Asaas (já tem integração financeira) (b) Iugu (c) Stone (d) BlueShift |
| Quanto investir em jurídico LGPD pré-launch? | (a) R$ 5k LIA mínima (b) R$ 15k LIA + DPIA (c) R$ 30k LIA + DPIA + auditoria |
| Cláusula contratual opt-out vs opt-in com revenue share? | (a) opt-out simples (b) opt-in com 10% RS (c) ambos (clientes escolhem) |

---

**Refs relacionados:**
- [Auto-vertical strategy](auto-vertical-strategy.md) — pattern de expandir lateral pra outros verticais
- [Foco empresa 2026-2027](foco-empresa-2026-2027-camadas-priorizadas.md) — onde encaixa no roadmap
- [Pricing % GMV baseado em 32 clientes](pricing-pct-gmv-baseado-em-32-clientes.md) — economics base
- [Integrações estratégicas 12m](integracoes-estrategicas-12m.md) — Asaas como parceiro natural
- ADR 0093 — multi-tenant isolation Tier 0 (score API NÃO pode vazar entre business_id)
- ADR 0053 — MCP server governança como produto (mesmo padrão "data como produto")
