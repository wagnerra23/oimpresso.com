# Network Effect Engine — oimpresso.com

> **Status:** proposal (não-aceita) — análise estratégica
> **Autor:** Claude (growth strategist + product economist)
> **Data:** 2026-05-09
> **Contexto:** 41 clientes legacy WR2/Office Comercial + base oimpresso novo crescente; concorrentes (Mubisys/Zênite/Bling) NÃO publicam benchmark setorial
> **Tese central:** "quanto mais clientes oimpresso, mais inteligente fica Jana pra todos" → moat crescente que concorrente sem dado não replica

---

## 1. Tese estratégica

ERPs gráficos brasileiros vendem **software**. oimpresso vende **software + inteligência agregada**. Toda gráfica que entra contribui dado anônimo (preço/m², ticket médio, sazonalidade, mix vertical) e em troca recebe insights que ela sozinha NÃO consegue gerar (benchmark regional, forecast colaborativo, detecção de tendência).

Mubisys/Zênite/Bling tentando replicar precisam:
1. Reconquistar 50+ gráficas mesma vertical/região (~18-24m)
2. Convencê-las a topar partilhar dado (Mubisys nunca pediu — não tem prática)
3. Construir pipeline ML + governança LGPD (~6-12m engenharia)

**Defensibilidade estimada: 24-36 meses de vantagem.**

Diferente de moat "feature" (replicável em 3m) — moat **dado-acumulado** só cresce com tempo + clientes pagantes.

---

## 2. Tipologia aplicada

| Tipo | Aplica em oimpresso? | Mecanismo |
|---|---|---|
| **A. Direct** | ❌ baixo (não é rede social) | — |
| **B. Indirect** | ✅ alto | Mais clientes → benchmark melhor → atrativo pra próximo cliente |
| **C. Data network effect** | ✅ ALTO (core) | ML treina em todos, beneficia cada um |
| **D. Two-sided** | 🟡 médio (futuro) | Lado fornecedor (Avery/3M/Roland) paga por lead qualificado |
| **E. Local network effect** | ✅ alto | Cluster regional (SC/SP/MG) + cluster vertical (com.visual/oficina) |

**Foco S1-S2 (12m):** B + C + E. **D entra S3+ (~18m)** quando volume justifica negociação fornecedor.

---

## 3. Os 8 mecanismos

### Mecanismo 1 — Benchmark Setorial Anônimo

**Mockup (tela `/financeiro/benchmark`):**
```
┌─────────────────────────────────────────────────────────────┐
│ Você no mercado — Com.Visual / Santa Catarina / 2026-Q2     │
├─────────────────────────────────────────────────────────────┤
│ Receita mensal             Ticket médio          DSO         │
│ R$ [redacted Tier 0]k                    R$ [redacted Tier 0]                34 dias     │
│ ↑ acima da média (P62)     P50                  ↑ acima P50  │
│                                                              │
│ Mediana SC com.visual: R$ [redacted Tier 0]k | P75: R$ [redacted Tier 0]k | P90: R$ [redacted Tier 0]k │
│                                                              │
│ Base: 18 gráficas SC com.visual (k-anonymity ≥5 ✅)          │
│ Última atualização: ontem 23h00 BRT                          │
└─────────────────────────────────────────────────────────────┘
```

**Estrutura de dados:**
```sql
CREATE TABLE network_benchmark_aggregates (
  id BIGINT UNSIGNED PRIMARY KEY,
  cluster_key VARCHAR(80),          -- "SC|com_visual|2026Q2|receita_mensal"
  vertical ENUM('com_visual','oficina','grafica_rapida','brindes'),
  region_uf CHAR(2),
  period_quarter VARCHAR(7),        -- '2026Q2'
  metric VARCHAR(40),               -- receita_mensal, ticket_medio, dso, etc
  n_businesses SMALLINT UNSIGNED,   -- gate ≥5 senão NULL
  p25 DECIMAL(14,2),
  p50 DECIMAL(14,2),
  p75 DECIMAL(14,2),
  p90 DECIMAL(14,2),
  computed_at TIMESTAMP,
  INDEX idx_cluster (cluster_key)
);
```

**Fluxo de evento:**
1. Job `network:benchmark:rebuild` (daily 04h BRT)
2. Itera vertical × region × metric
3. Se `COUNT(DISTINCT business_id) < 5` → NULL bin (k-anonymity gate)
4. Se ≥5 → calcula p25/p50/p75/p90 + write tabela
5. Frontend lê via Service `BenchmarkLookupService::byCluster()`

**LGPD:**
- ❌ Nunca expor `business_id` individual em bin
- ✅ k-anonymity ≥5 (config `network.k_anonymity_min=5`)
- ✅ Diferential privacy noise opcional (Laplacian ε=1.0) em métricas sensíveis (margem)
- ✅ Opt-out granular: cliente pode sair do agregado em `/copiloto/admin/privacidade`
- ✅ Termo de uso v3 explica contribuição anônima (Wagner aprova ADR antes de live)

**ROI:**
- Custo: 1 sprint (~80h) implementar + 1h/dia infra
- Receita: feature gateia tier "Pro" R$ [redacted Tier 0]/m → 10 clientes adesão = R$ [redacted Tier 0]/ano
- **Defensibilidade:** ALTA (dado, não feature)

---

### Mecanismo 2 — Forecast Colaborativo

**Mockup (`/financeiro/forecast`):**
```
Receita prevista próximos 90 dias

┌─── seu próprio dado (12m histórico) ───┐
│ R$ [redacted Tier 0]k ± 28%   ← intervalo amplo       │
└─────────────────────────────────────────┘

┌─── Forecast Pro (modelo coletivo) ─────┐
│ R$ [redacted Tier 0]k ± 9%    ← 3x mais preciso       │
│ Treinado em 47 gráficas com.visual SC   │
│ Sazonalidade aplicada: jan-mar -12%,    │
│ jun pico campanha eleitoral +28%        │
└─────────────────────────────────────────┘
```

**Estrutura:**
- Model registry: `network_forecast_models` (vertical, region, model_version, mae, rmse)
- Modelo: Prophet ou LightGBM gradient boosted, treinado em série temporal `monthly_revenue_per_business`
- Cliente individual = inferência sobre modelo coletivo + ajuste personal (transfer learning)

**Fluxo:**
1. Weekly retrain (Sunday 03h BRT) em CT 100 (não Hostinger)
2. `BusinessFeatureExtractor::extract($business_id)` gera vetor 24-feature (ticket médio, mix vertical, DSO, sazonalidade, etc) — **anonimizado**
3. Inferência: `ForecastService::predict($business_id, $horizon=90)`
4. UI mostra intervalo + comparativo com modelo individual

**LGPD:**
- Features extraídas ≠ dados crus; treino sobre tendência agregada
- Cliente vê seu próprio forecast; outros businesses sumiram nos pesos do modelo
- Auditoria: log model card "n=47 businesses, periodo treino, MAE=0.09"

**ROI:**
- Pricing: feature "Forecast Pro" R$ [redacted Tier 0]/m
- **Pré-requisito: 50+ clientes mesma vertical** (senão modelo overfitta)
- Implementação: ~1 sprint Python + integração Laravel via `laravel/ai` (ADR 0035)
- Receita ano cheio (target 30 clientes Pro): R$ [redacted Tier 0]
- **Defensibilidade:** ALTÍSSIMA (modelo IP + dado IP)

---

### Mecanismo 3 — Recomendação Cross-Cliente

**Mockup (sidebar `/dashboard`):**
```
💡 Insight Jana
Outras 5 gráficas do seu porte (R$ [redacted Tier 0]-200k/m, com.visual SC)
adicionaram Asaas como adquirente nos últimos 6 meses e
cresceram receita média 12%.
[Saiba mais] [Não mostrar mais]
```

**Estrutura:**
- Tabela `network_action_outcomes`: para cada ação (adicionar Asaas, ativar NFCe, criar landing CMS, etc) + business → mediu delta receita 90d antes/depois
- Mining: clusters parecidos (k-NN sobre features anonimizadas) + ação que gerou maior delta positivo

**LGPD:**
- "5 gráficas do seu porte" = aggregate count, nunca nome
- Opt-in pra contribuir outcome (default ON com termo)

**ROI:**
- Driver de upsell de outros módulos (Asaas integration, NFeBrasil, CMS) → recomenda no momento certo
- Impacto receita estimado: +15% conversion module add
- **Defensibilidade:** MÉDIA-ALTA (dado de outcome, raro)

---

### Mecanismo 4 — Pricing Index Colaborativo

**Mockup (`/repair/precificar` ou tela quote):**
```
Preço médio mercado SC — Lona 440g impressa
┌──────────────────────────────────┐
│ P25: R$ [redacted Tier 0]/m²                    │
│ P50: R$ [redacted Tier 0]/m²  ← mediana         │
│ P75: R$ [redacted Tier 0]/m²                    │
│                                   │
│ Você está cobrando: R$ [redacted Tier 0]/m²     │
│ ⚠️ 18% abaixo da mediana          │
│                                   │
│ Base: 23 gráficas SC com.visual  │
└──────────────────────────────────┘
```

**Estrutura:**
```sql
CREATE TABLE network_pricing_samples (
  id BIGINT,
  business_id BIGINT,         -- nunca exposto fora; só pra dedup + opt-out
  product_taxonomy_id INT,    -- FK pra catálogo unificado (lona_440g, vinil_adesivo, etc)
  price_per_unit DECIMAL,
  unit ENUM('m2','un','linear_m'),
  vertical, region_uf,
  sampled_at TIMESTAMP,
  INDEX (product_taxonomy_id, region_uf, vertical)
);
```

**LGPD:**
- Preço por si só não é PII
- Opt-out via `/copiloto/admin/privacidade`
- k-anon ≥5 antes de exibir bin

**ROI:**
- Posiciona oimpresso como "fonte de verdade" preço gráfica BR
- Lateral: vende relatório anual `precos-grafica-br.pdf` pra Avery/Roland (ADR proposal `api-score-grafica-br-data-product.md` correlato)
- Implementação: ~2 sprints (catálogo unificado é complexo)
- **Defensibilidade:** ALTA (catálogo + amostra)

---

### Mecanismo 5 — Detecção de Churn de Cliente Final

**Mockup (`/clients/{id}` aba Saúde):**
```
🟡 Risco de churn estimado

Cliente: Boutique Aurora Ltda
Score: 73% chance de não voltar nos próximos 60d

Fatores (modelo coletivo):
- ↓ frequência pedidos (-40% últimos 90d)
- ↑ ticket médio caindo (-18%)
- Sem pedido há 47d (média do segmento: 31d)
- Atraso pagamento última fatura (+12d)

Sugestão Jana: ligar + enviar cupom 10%
[Criar followup] [Ignorar]
```

**Estrutura:**
- Modelo treinado em todos os 41+ clientes oimpresso × seus contatos finais
- Features: recência, frequência, monetary (RFM clássico) + delta DSO + sazonalidade
- Output: probabilidade churn 60d/90d

**LGPD:**
- Nome cliente final é PII: cliente oimpresso vê SEU cliente nominal (legítimo, é dado dele)
- Modelo NÃO mostra clientes finais de outras gráficas — só padrão agregado

**ROI:**
- CAC retenção: cada cliente final salvo = R$ X mantido
- Driver de uso CRM/comercial → upsell module
- **Defensibilidade:** ALTA (precisa volume + histórico longo)

---

### Mecanismo 6 — Lead Matching (Two-Sided Light)

**Mockup (`/marketplace/capacidade`):**
```
🤝 Match capacidade ociosa

Gráfica X (50km de você, Jaraguá do Sul) tem 200 m²
de impressão lona disponível esta semana e busca parceria.

Você tem demanda > capacidade?
[Conectar] [Ignorar]

🔒 Identidade só liberada após match mútuo (consentimento)
```

**Estrutura:**
- Tabela `network_capacity_offers` (business_id, capacidade_tipo, qtd, valido_ate, geo_lat/lng com privacy ofuscado em raio 5km)
- Match algoritmo: Haversine distance + tipo equip + janela temporal
- Identidade só revelada pós-mutual-opt-in

**LGPD:**
- Geo ofuscado (raio 5km) até match
- Razão social só pós-consentimento mútuo

**ROI:**
- Take rate 5% sobre transação intermediada (futuro modelo de monetização)
- Pré-requisito: ~100+ clientes mesma região
- **Defensibilidade:** MÉDIA (rede física)

---

### Mecanismo 7 — Feedback Loop Fornecedor (Two-Sided)

**Mockup interno (admin Jana):**
```
🎯 Oferta direcionada — Avery
Avery DENNISON paga R$ [redacted Tier 0] por impressão de oferta
em gráfica que comprou ≥R$ [redacted Tier 0]k em vinil adesivo nos últimos 12m.

Audiência elegível: 18 gráficas (k-anon ≥5 ✅)
Oferta: 15% desconto vinil 651 + frete grátis pedido ≥R$ [redacted Tier 0]k
Periodo: 2026-05-15 → 2026-06-15
```

E pra cliente final:
```
💎 Oferta exclusiva oimpresso × Avery
Vinil 651 com 15% off + frete grátis (pedido ≥R$ [redacted Tier 0]k)
Válido até 15/06. Use código OIMP-AVERY-Q2
[Aproveitar]
```

**Estrutura:**
- `network_supplier_offers` (supplier_id, target_segment_filter, offer_text, valid_period, payout_model)
- `network_supplier_payouts` (offer_id, business_id, conversao_evento, valor_pago)
- Atribuição: clique tracking + conversão validada pelo fornecedor

**LGPD:**
- Cliente oimpresso opt-in pra ofertas fornecedor
- Fornecedor recebe métricas agregadas (impressions, click_rate, conversion), nunca lista nominal

**ROI:**
- Modelo CPM/CPA com fornecedores (ADR proposal `daas-oimpresso-plano-operacional.md` correlato)
- Receita estimada ano 1 com 3 fornecedores: R$ [redacted Tier 0]k-120k
- **Pré-requisito:** 50+ clientes E volume compras rastreável (NFE inbound parsing)
- **Defensibilidade:** MÉDIA-ALTA (escala + relacionamento fornecedor)

---

### Mecanismo 8 — Trend Detection Regional

**Mockup (notificação proativa Jana):**
```
📈 Tendência detectada — sua região

Em MG (sua filial), demanda por sinalização interna
cresceu 18% nos últimos 60 dias (base: 14 gráficas).

Vertical com maior alta: condomínios (+34%)

Sugestão: priorizar campanha digital nessa categoria.
[Ver dashboard regional] [Dispensar]
```

**Estrutura:**
- Job semanal mapa-de-calor: agrupa OS criadas por (region × subvertical × periodo) → calcula delta vs trimestre anterior
- Threshold: delta ≥15% E base ≥10 businesses → alerta

**LGPD:**
- Agregação total, sem business_id individual

**ROI:**
- "Magic moment" Jana — proativo, lock-in psicológico
- Driver de retenção (cliente sente que oimpresso é "consultor", não só ERP)
- **Defensibilidade:** ALTA (precisa amplitude geográfica + histórico)

---

## 4. Métricas de network effect

| Métrica | Definição | Target ano 1 | Target ano 2 |
|---|---|---|---|
| **Coefficient (k)** | % aumento valor médio por +1 cliente | k=0,3% (cada cliente +0,3% utilidade) | k=0,5% |
| **Saturation point** | clientes pra atingir 80% valor | 200 clientes | 500 clientes (vertical-específico) |
| **Defensibility** | meses concorrente alcançar | 24m | 36m |
| **Lock-in** | % churn citando "perderia benchmark" | ≥15% | ≥30% |
| **Cluster coverage** | % verticais × regions com k-anon ≥5 | 30% | 70% |
| **Opt-in rate** | % clientes contribuindo dado | ≥80% | ≥90% |

---

## 5. Roadmap implementação 12 meses

### Q1 (M1-M3) — Fundação
- ✅ ADR 0120 "Network Effect Engine — Carta de Privacidade" (Wagner aprova)
- ✅ Termo de uso v3 com cláusula contribuição anônima
- ✅ Tabela `network_benchmark_aggregates` + job daily
- ✅ Mecanismo 1 (Benchmark Setorial) MVP — vertical com.visual + UF SC
- ✅ Opt-out UI em `/copiloto/admin/privacidade`
- 🎯 Marco: 30 clientes ativos no benchmark, k-anon ≥5 em ≥3 clusters

### Q2 (M4-M6) — Pricing Index + Recomendação
- Mecanismo 4 (Pricing Index) — catálogo unificado lona/vinil/sinalização
- Mecanismo 3 (Recomendação Cross-Cliente) — outcomes Asaas/NFE
- Tier "Pro" launch (R$ [redacted Tier 0]/m) gateando Mecanismos 1+3+4
- 🎯 Marco: 10 clientes Pro, R$ [redacted Tier 0]k ARR network features

### Q3 (M7-M9) — ML Pipeline + Forecast
- CT 100: pipeline Python (Prophet/LightGBM) treino weekly
- Mecanismo 2 (Forecast Colaborativo) MVP
- Mecanismo 5 (Churn cliente final) MVP
- 🎯 Marco: 50+ clientes mesma vertical (com.visual), modelo MAE ≤0,10

### Q4 (M10-M12) — Two-Sided + Trend Detection
- Mecanismo 8 (Trend Detection regional)
- Mecanismo 7 (Feedback Loop fornecedor) — piloto Avery
- Mecanismo 6 (Lead matching capacidade) — beta fechado
- 🎯 Marco: 1 fornecedor pagante (R$ [redacted Tier 0]k+/m), R$ [redacted Tier 0]k ARR network features

---

## 6. LGPD compliance — checklist transversal

| Item | Cobre |
|---|---|
| ✅ Base legal **legítimo interesse** + consentimento granular | Art. 7º LGPD |
| ✅ k-anonymity ≥5 em todos bins exibidos | mínimo padrão setor |
| ✅ Opt-out granular por mecanismo (não tudo-ou-nada) | autonomia titular |
| ✅ DPO appointment (Wagner ou contratar) | obrigatório >50 funcionários OU dados sensíveis |
| ✅ DPIA (Data Protection Impact Assessment) antes de Mecanismo 5 e 7 | risco médio-alto |
| ✅ Audit log toda extração agregada (`network_audit_extracts`) | accountability |
| ✅ Differential privacy ε=1.0 em métricas sensíveis (margem) | belt-and-suspenders |
| ✅ Termo de uso v3 explica em PT-BR claro o que é compartilhado | transparência |
| ✅ Direito de portabilidade — exporta agregados que cliente contribuiu | Art. 18 LGPD |
| ✅ Direito de eliminação — opt-out remove de futuros agregados (passado já anonimizado, irreversível) | Art. 18 LGPD |

---

## 7. Riscos e mitigações

| Risco | Severidade | Mitigação |
|---|---|---|
| **Cliente sente "alimenta concorrente"** | ALTA (#1 risco) | Comunicação clara: "você ganha mais do que dá"; demonstração ROI primeiro mês; opt-out trivial |
| Concorrente faz scrape do benchmark público | MÉDIA | Rate limiting + auth-required; benchmarks detalhados só pra pagantes |
| Modelo enviesado (poucos clientes em vertical) | MÉDIA | Gate k-anon ≥5 + n_min ≥30 pra forecast; transparência intervalo confiança |
| Drift dado (cliente reporta preço errado) | MÉDIA | Outlier detection + flag manual review |
| Vazamento via inferência (ataque linkage) | BAIXA-MÉDIA | DP noise + audit logs; ADR sobre adversarial testing |
| LGPD enforcement (multa ANPD) | ALTA potencial | DPO + DPIA + advogado revisa termo v3 |
| Custos infra ML CT 100 explodir | MÉDIA | Budget mensal R$ [redacted Tier 0] GPU/CPU; alertas em `jana:health-check` |

---

## 8. Decisão pendente Wagner

1. **Aprovar tese** "oimpresso vende software + inteligência agregada" (vs só software)
2. **Aprovar ADR 0120** Network Effect Engine antes de implementar Q1
3. **Aprovar termo v3** com cláusula contribuição anônima (revisão jurídica recomendada)
4. **Definir budget** infra ML CT 100 (sugestão: R$ [redacted Tier 0]/m piloto, escala conforme uso)
5. **Definir tier Pro** R$ [redacted Tier 0]/m vs incluído em tier topo

---

## 9. Referências cruzadas

- Proposal correlata: `api-score-grafica-br-data-product.md` — relatório anual vendido externamente
- Proposal correlata: `daas-oimpresso-plano-operacional.md` — modelo Data-as-a-Service mais amplo
- Proposal correlata: `feature-financial-snapshot-multi-cliente.md` — base benchmark intra-cliente
- ADR 0093 multi-tenant Tier 0 — `business_id` global scope IRREVOGÁVEL
- ADR 0094 Constituição v2 — princípio #6 multi-tenant, princípio #7 transparência
- ADR 0035 stack IA canônica — `laravel/ai` ^0.6.3 driver Camada A pra modelos
- ADR 0053 MCP server governança — pattern de tool exposed condicional `MCP_TOOLS_EXPOSED`

---

**Última atualização:** 2026-05-09 — proposta inicial Claude growth strategist (não-aceita; aguarda Wagner)
