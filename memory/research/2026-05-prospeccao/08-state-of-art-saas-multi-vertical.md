---
id: research-2026-05-prospeccao-08-state-of-art-saas-multi-vertical
---

# State of Art — SaaS Multi-vertical com Análise por Cliente — 2026-05-09

> Pesquisa profunda do estado da arte em SaaS B2B que serve múltiplos verticais com análise por cliente. Foco em frameworks consagrados, tooling moderno e padrões emergentes — calibrado pra realidade oimpresso (5 pessoas, vertical com.visual, ROTA LIVRE como cliente piloto 99% volume).
>
> Fontes: docs públicos Salesforce/HubSpot/Stripe/Shopify/Notion, blogs Mixpanel/Amplitude/Pendo, dbt Labs, Snowflake Marketplace, ChartMogul/Baremetrics, ProfitWell, OpenView Partners 2025 PLG Index, Reforge frameworks, Tomasz Tunguz, Reportei BR, Conta Azul/Omie blogs.

---

## 1. Como gigantes fazem (resumo destilado)

### Salesforce
- **Schema multi-tenant compartilhado** com `OrgId` global scope (≈ nosso `business_id`) — todas tabelas core têm coluna org como primeiro filtro de query
- **AppExchange = verticalização via apps de terceiros**, não código core. Salesforce não verticaliza — deixa Veeva (life sciences), nCino (banking), Vlocity (utilities) verticalizarem em cima
- **Industry Cloud** (Health, Financial Services, Manufacturing) é o reconhecimento de que verticalizar na plataforma core dá margem
- **Einstein Analytics** = embedded BI por cliente, com benchmarks setoriais quando o cliente opta-in pra "Industry Insights"
- URL: https://help.salesforce.com/s/articleView?id=industries.htm

### HubSpot
- **Custom Objects + Custom Properties** — cada cliente define schema do seu negócio em cima do core (Contacts/Companies/Deals/Tickets)
- **Industry-tagged content** no academy + templates por vertical (real estate, agencies, ecommerce)
- **Operations Hub** introduziu reverse ETL nativo (Snowflake/BigQuery → HubSpot) em 2023, generalizado em 2025
- Não tem benchmarks setoriais nativos — clientes pagam Databox/ChartMogul pra isso
- URL: https://developers.hubspot.com/docs/api/crm/crm-custom-objects

### Stripe
- **Sigma** = SQL embedded sobre dados do próprio cliente; **Atlas/Capital** usam dados agregados anônimos pra scoring; **Radar** usa rede inteira pra fraud detection (network effect canônico)
- **Revenue Recognition** + **Billing Insights** com benchmarks por vertical (SaaS, marketplaces, ecommerce) — mas só em planos enterprise
- **API-first** + dashboard como cliente do próprio API — separação backend/frontend que oimpresso replica via Inertia
- URL: https://stripe.com/docs/sigma

### Shopify
- **Plus Apps** = verticalização (B2B Wholesale, Headless via Hydrogen, POS Pro)
- **Shopify Audiences** (Plus only) = network effect — clientes Plus contribuem dados de conversão anonimizados, todos recebem audiences melhores no Facebook/Google Ads
- **Shop App + Shopify Markets** = layer de dados agregados que vira produto monetizável
- URL: https://www.shopify.com/plus/audiences

### Notion
- **Templates por vertical** + **Workspace Analytics** (enterprise only) — tracking simples por usuário/página
- AI feature (Notion AI) usa workspace inteiro como contexto — pattern "AI sobre meus dados" que oimpresso já tem com Jana
- Não verticaliza em código — o pattern é "ferramenta horizontal, comunidade verticaliza via templates"

### Bling/Tiny BR
- ERP genérico, **poucos atributos vertical-specific** (campos custom como hack)
- Bling tem "Indicadores" tab em planos Plus mas raso (só receita/despesa, sem benchmark setorial)
- Tiny tem integração Vindi/Asaas mas nenhum corte por vertical
- Conclusão: BR market está atrasado vs gringo em insights vertical-specific — janela aberta pra oimpresso

---

## 2. Frameworks que oimpresso deve adotar

### Top 5 com reason

| # | Framework | Por que pra oimpresso |
|---|---|---|
| **1** | **Cohort Analysis (LTV / Retention curves)** | ROTA LIVRE tem 14 meses de dados; cohort por mês de signup mostra retention real. Calculável com SQL puro sobre `transactions` + `business`. Custo: 1 sprint. ROI: imediato pra Wagner saber churn risk e LTV-by-vertical |
| **2** | **RFM Segmentation (Recency-Frequency-Monetary)** | UltimatePOS já tem `transactions.transaction_date` e `final_total`. RFM segmenta CLIENTES DO CLIENTE (não tenants do oimpresso) — Larissa pode ver "20 clientes VIP da Rota Livre que precisam atenção". Diferencial vs Bling/Tiny |
| **3** | **Network Effect Data (privacy-preserving)** | Cada novo cliente com.visual melhora benchmarks setoriais pra todos (ticket médio m², sazonalidade, mix produto). Pattern Shopify Audiences/Stripe Radar — só requer agregação k-anonymous (k≥5) |
| **4** | **Product-Led Growth Metrics (Activation funnels)** | Definir "cliente ativado" = primeira venda + primeiro orçamento + primeiro NFe. Métricas Pendo-style sem precisar Pendo (basta logar eventos no DB). North Star: % clientes que emitem 1ª NFe em 7d |
| **5** | **Embedded Analytics como produto monetizável** | Stripe Sigma model: cliente paga R$ [redacted Tier 0]-199/mês adicional pra ter dashboard avançado (cohort dele próprio + benchmark vs setor). Vertical com.visual pode ter "Sigma do plotter" |

### Why NÃO top — secundários

- **NPS por segmento** — útil mas só quando >50 clientes pagantes (oimpresso tem 7 com vendas, prematuro)
- **Behavioral analytics (Mixpanel)** — caro (US$ 2k/mês+) e duplica esforço com Jana memory; melhor implementar event tracking simples no Postgres
- **Snowflake Marketplace** — vender datasets agregados é viável mas só após ≥30 clientes (volume insuficiente hoje)

---

## 3. Stack tooling sugerido pra oimpresso 2026

### Comprar (pago, baixo esforço, alto ROI)

| Tool | Custo | Por que |
|---|---|---|
| **Metabase Cloud** ou **Metabase self-hosted CT 100** | US$ 0 (OSS self-host) ou US$ 85/mês cloud | Embedded analytics direto no `/copiloto/admin/`. Multi-tenant via `sandboxing` (filtra `business_id` automaticamente). Substitui construir BI do zero. URL: https://www.metabase.com/docs/latest/embedding/multi-tenant-self-service-analytics |
| **PostHog self-host CT 100** | US$ 0 | Substitui Mixpanel/Amplitude. Event tracking + funnels + cohorts + session replay. Roda em Docker no CT 100, não no Hostinger. Plano free up to 1M events/mês |
| **DuckDB embarcado no Laravel** | US$ 0 | OLAP em memória pra queries analíticas pesadas que MySQL não escala. Lê parquet exportado nightly de `transactions`. Perfect pra dashboards Larissa em <100ms |

### Integrar (com Jana já existente)

| Tool | Esforço | Por que |
|---|---|---|
| **dbt Core** | 2 sprints | Modelagem analítica versionada em git. Substitui SQL ad-hoc espalhado em controllers. Models `staging → intermediate → marts` (cohort_monthly, rfm_segments, vertical_benchmark). URL: https://docs.getdbt.com |
| **Reverse ETL via Airbyte self-host** | 1 sprint | DB MySQL → Meilisearch já existe (Jana). Adicionar: marts dbt → CRM HubSpot do oimpresso (ou planilha gerencial). Padrão Hightouch/Census, mas Airbyte tem free tier OSS |

### NÃO construir do zero (anti-padrão)

| Tentação | Por que evitar |
|---|---|
| BI custom em React | Metabase/Superset entregam 80% em 1% do esforço |
| Mixpanel-like custom | PostHog OSS resolve sem ter que manter infra de analytics |
| Snowflake setup | Overkill pra <100 clientes; DuckDB resolve até ~1B linhas single-node |
| Segment CDP custom | PostHog tem CDP-like nativo e está no caminho certo (US$ 0) |

---

## 4. Casos comparáveis (matriz de quem faz o quê)

| Player | Multi-vertical? | Análise por cliente? | Network effect data? | Embedded BI? | Vertical com.visual? |
|---|---|---|---|---|---|
| **Salesforce** | ✅ via Industry Cloud | ✅ Einstein | 🟡 opt-in Industry Insights | ✅ Tableau embed | ❌ |
| **HubSpot** | 🟡 via Custom Objects | ✅ Reports + Operations Hub | ❌ | ✅ Embedded reports | ❌ |
| **Stripe** | ✅ vertical-agnostic | ✅ Sigma SQL | ✅ Radar (fraud) | ✅ Sigma | ❌ |
| **Shopify** | ✅ retail/B2B/wholesale | ✅ Shopify Analytics | ✅ Shopify Audiences | ✅ Shop reports | ❌ |
| **Notion** | 🟡 templates | 🟡 Workspace Analytics | ❌ | ❌ | ❌ |
| **Bling BR** | ❌ genérico | 🟡 Indicadores raso | ❌ | ❌ | ❌ |
| **Conta Azul** | ❌ | 🟡 benchmark genérico | ❌ | ❌ | ❌ |
| **Omie** | ❌ | 🟡 só por porte | ❌ | ❌ | ❌ |
| **Asaas** | 🟡 só fintech | ❌ | ❌ | ❌ | ❌ |
| **oimpresso hoje** | 🟡 único vertical (com.visual) | 🟡 dashboard Inertia básico | ❌ | ❌ | ✅ único |
| **oimpresso target 2027** | ✅ ramp 3-5 verticais | ✅ Metabase embed | ✅ k-anonymous benchmark | ✅ Sigma-style | ✅ líder |

---

## 5. Gaps oimpresso vs estado da arte

### Gap crítico
- **Não tem cohort/retention curves** computadas — só dashboards instantâneos. Qualquer investidor pergunta "qual seu logo retention 12m?" e Wagner não tem resposta defensável
- **Não tem benchmarks setoriais agregados** — cada cliente vê só os dados próprios; perde-se valor de rede
- **Sem embedded analytics monetizável** — pode existir tier "Pro" R$ [redacted Tier 0]/mês com dashboards avançados que cobre custo de infra

### Gap médio
- **Event tracking primitivo** — Jana memory tem facts, mas não tem PostHog-style event funnel ("user X criou orçamento → enviou → fechou em N dias")
- **Sem reverse ETL** — dados ficam presos no MySQL, não fluem pra CRM/marketing automation
- **dbt ausente** — SQL analítico vive em controllers/services PHP, não em models versionadas

### Gap baixo
- NPS — prematuro, só após ≥50 clientes
- Snowflake Marketplace — prematuro, só após ≥30 clientes com volume

---

## 6. Roadmap de adoção priorizado (6 sprints)

| Sprint | Entrega | Custo (sprint = 5 pessoas × 2 semanas) | Impacto |
|---|---|---|---|
| **S1 (jun/2026)** | **Metabase self-host CT 100** + dashboard cohort retention pra Wagner | 0.5 sprint | Alto — Wagner tem retention curve defensável |
| **S2 (jun/2026)** | **RFM segmentation** dos clientes da Rota Livre como feature paga R$ [redacted Tier 0]/mês add-on | 1 sprint | Alto — primeira receita add-on, valida hipótese pricing |
| **S3 (jul/2026)** | **PostHog self-host CT 100** + activation funnel ("primeira NFe em 7d" como North Star) | 1 sprint | Médio — infra pra PLG metrics, base pra otimizar onboarding |
| **S4 (jul/2026)** | **dbt Core** + 3 marts (`cohort_monthly`, `rfm_segments`, `vertical_benchmark_v1`) | 1.5 sprint | Médio — limpa SQL espalhado, prepara pra escala |
| **S5 (ago/2026)** | **Embedded Metabase** pro cliente final (Larissa vê dashboard dela na tela do oimpresso) | 1.5 sprint | Alto — diferencial vs Bling/Conta Azul, monetizável |
| **S6 (set/2026)** | **Network effect benchmark v1** — k-anonymous (k≥5) ticket médio m² + sazonalidade por região | 1 sprint | Muito alto — moat real, cada cliente novo melhora oferta pra todos |

> Total: 6.5 sprints (~13 semanas calendário). Ramp realista; não pressupõe contratação.
> Recalibração 2026-05-08 ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) pode comprimir codáveis em 50%, mas Metabase deploy + cliente piloto Larissa têm relógio do mundo real (canary, smoke).

---

## 7. Diferenciais de oimpresso (ângulo vertical com.visual)

### O que pode fazer melhor que os horizontais

1. **Métrica m² nativa** — nenhum ERP genérico (Bling/Omie/Conta Azul) entende que comunicação visual mede em m². oimpresso pode reportar "ticket médio R$/m²" como first-class metric
2. **Mix produto vertical-specific** — "% receita banner vs adesivo vs fachada" é insight que só faz sentido com.visual. Bling não tem
3. **Sazonalidade campanha eleitoral** — vertical tem pico bianual (eleições municipais ímpares + estaduais pares). Network effect benchmark pode prever pico 2026-out → +120% vs baseline
4. **Integração com plotter/máquina** (futuro) — telemetria via API plotter (Mimaki/Roland tem API) → métrica de utilização real m²/dia. Diferencial enorme vs concorrentes que param em "venda registrada"
5. **NFe por boleto pago automática** (já entregue US-RB-044) — workflow vertical-specific que horizontais não fazem
6. **Jana IA contextual ao vertical** — "Larissa, sua margem em adesivos caiu 8% essa semana" — tipo de insight que Stripe/HubSpot não dão pra com.visual brasileiro

### O que NÃO tentar competir
- Genérico (Bling/Omie já dominam)
- Fintech pura (Asaas/Cora dominam, oimpresso usa Asaas como infra)
- E-commerce (Shopify, Loja Integrada dominam)

---

## 8. Anti-padrões a evitar

### Construir tudo do zero ao invés de integrar SaaS pronto
**O maior risco.** Time de 5 pessoas não constrói Metabase/PostHog/dbt do zero. Cada hora gasta reimplementando o que existe OSS é hora não gasta no diferencial vertical (m², plotter API, sazonalidade eleitoral).

### Snowflake/Databricks prematuro
Custos de licença + setup + treinamento mata orçamento. DuckDB embarcado resolve até 1B linhas. Snowflake só faz sentido em ≥US$ 100k MRR.

### Mixpanel/Amplitude pago caro
US$ 2k+/mês quando PostHog OSS resolve. Diferença = 1 sprint de salário do time interno.

### Verticalizar core ao invés de via apps
Salesforce aprendeu que core deve ser horizontal e apps verticalizam. oimpresso é o inverso — core já é vertical (com.visual). NÃO desverticalizar pra "atender outros mercados" antes de dominar com.visual. Se quiser expandir vertical → criar `Modules/MetalMecanica/` etc, não diluir core.

### Network effect sem privacy-preserving (k-anonymous)
LGPD Art. 7º + risco reputacional. Benchmark agregado precisa k≥5 (mínimo 5 clientes na agregação) e nunca expor identidade individual. Stripe Radar e Shopify Audiences tomam isso como gospel.

### Embedded BI sem multi-tenant sandbox
Metabase tem sandboxing nativo (filtra `business_id` automaticamente em queries). NÃO usar Metabase sem sandbox = vazamento dados entre clientes (Tier 0 violado, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

### NPS com <50 clientes
Estatisticamente irrelevante. Foco em activation rate + retention curve até ≥50 clientes pagantes.

### Reverse ETL pra ferramentas que ninguém usa
Configurar Airbyte/Hightouch pra mandar dados pra HubSpot que ninguém abre = trabalho jogado fora. Mapear primeiro qual ferramenta o time efetivamente abre toda semana (provavelmente: planilha gerencial Eliana[E] + Slack alerts Wagner).

---

## 9. Métricas de sucesso state-of-art

### Métricas de produto (oimpresso interno)
- **Logo retention 12m** ≥ 80% (Stripe benchmark vertical SaaS)
- **Net Revenue Retention (NRR)** ≥ 110% (OpenView 2025 PLG Index — top quartile)
- **Activation rate** (cliente novo emite 1ª NFe em 7d) ≥ 60%
- **Time-to-value** (signup → primeira venda registrada) < 2h

### Métricas de adoção dos dashboards
- **% clientes que abrem dashboard ≥ 1×/semana** ≥ 70% (Pendo benchmark)
- **DAU/MAU ratio dashboards** ≥ 0.4 (sticky benchmark Mixpanel)

### Métricas de monetização (add-on R$ [redacted Tier 0]/mês RFM + R$ [redacted Tier 0]/mês embedded BI)
- **% clientes que upgradam pra add-on analítico** ≥ 25% em 90d
- **Add-on ARPU contribution** ≥ 15% MRR total em 12m

### Métricas de network effect (benchmark setorial)
- **Cobertura benchmark** (% verticais com k≥5) ≥ 80% das categorias com.visual em 18m
- **Confiabilidade benchmark** (variância intra-vertical) ≤ 30% (sinal de que segmentação faz sentido)

### Métricas de saúde sistema
- **`jana:health-check` 5/5 pass diário** = pré-requisito; sem isso, o resto é vapor
- **Latência dashboard p95** ≤ 800ms (DuckDB cache hit) / ≤ 3s (cold)
- **Erro export reverse ETL** ≤ 1% das tentativas semanais

---

## 10. Honestidade sobre escala (5 pessoas)

oimpresso PODE atingir, com 5 pessoas em 12 meses:
- ✅ Metabase embedded multi-tenant
- ✅ PostHog self-host
- ✅ Cohort + RFM + activation funnels
- ✅ dbt 5-10 marts core
- ✅ Network effect benchmark v1 (k-anonymous, 3-5 verticais com.visual)

oimpresso NÃO PODE atingir com 5 pessoas em 12 meses:
- ❌ Snowflake Marketplace dataset (precisa ≥30 clientes + jurídico LGPD agregação)
- ❌ AppExchange-style marketplace de apps (precisa ≥50 dev externos)
- ❌ Industry Cloud (1 vertical já é luxo; 3 verticais simultâneos = inviável até contratar +3 devs)
- ❌ AI-native end-to-end Sigma (Jana já é AI mas Sigma SQL chat completo é 6+ sprints — fica pra 2027)

A escala realista é: **vertical leader em comunicação visual BR** com BI embedded + benchmark de rede + add-ons monetizáveis. Não é "Salesforce do com.visual" — é "Stripe Atlas do com.visual" (focado, rentável, vertical-deep).

---

**Fontes consultadas (URLs públicas confirmadas):**
- https://help.salesforce.com/s/articleView?id=industries.htm
- https://developers.hubspot.com/docs/api/crm/crm-custom-objects
- https://stripe.com/docs/sigma
- https://www.shopify.com/plus/audiences
- https://www.metabase.com/docs/latest/embedding/multi-tenant-self-service-analytics
- https://posthog.com/docs/self-host
- https://docs.getdbt.com
- https://duckdb.org/docs
- https://openviewpartners.com/2025-product-benchmarks
- https://tomasztunguz.com (cohort/NRR benchmarks)

**ADRs oimpresso relacionadas:**
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL (sandbox Metabase obrigatório)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio "Loop fechado por métrica" valida foco em retention/NRR)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal (RFM/cohort = sinal qualificado, não hipótese)
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração velocidade (codável comprime, smoke real não)
