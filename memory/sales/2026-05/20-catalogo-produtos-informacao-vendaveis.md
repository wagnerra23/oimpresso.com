---
title: Catálogo — 12+ produtos de informação vendáveis a partir do data asset WR Sistemas
status: draft (Wagner valida + ADR pré-lançamento de cada um)
date: 2026-05-09
author: Claude Opus 4.7 (sub-agent product-management + data-monetization)
purpose: catalogar produtos vendáveis a partir do acesso autorizado a 37 bancos OfficeImpresso (132k clientes finais, GMV R$ 45M agregado, 12 UFs diretas + 27 indiretas)
relates:
  - memory/decisions/proposals/feature-financial-snapshot-multi-cliente.md
  - memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md
  - .claude/skills/officeimpresso-financial-snapshot/SKILL.md
  - memory/sales/2026-05/06-pricing-tiers.md
lgpd_master_principle: tudo derivado de bancos cliente → opt-in formal por banco; agregados anônimos só com k-anonymity ≥ 5; PII (CPF/CNPJ/nome cliente final) NUNCA sai do tenant origem
---

# Catálogo — 12+ produtos de informação vendáveis — 2026-05-09

> **Status**: draft estratégico. Cada produto que avançar pra MVP precisa ADR canon + DPA (Data Processing Agreement) com cliente origem antes de qualquer cobrança. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) é IRREVOGÁVEL — agregação cross-tenant é o único uso lícito de dados de outros clientes pra produto, e SÓ com k-anonymity + opt-in.

---

## Vantagem competitiva única WR Sistemas

A WR Sistemas é a **única empresa do segmento de comunicação visual brasileiro** com acesso histórico autorizado a 37 bancos Firebird de gráficas operacionais (4-15 anos de histórico cada), totalizando ~132k clientes finais, ~R$ 45M de GMV agregado, e cobertura de 12 UFs diretas + 27 indiretas (clientes-de-clientes). Esse data asset existe porque os clientes contrataram o sistema OfficeImpresso quando ele era Delphi-only e o servidor Firebird ficou com a WR como provedora — uma posição de incumbente que **não pode ser replicada por concorrente novo** (Mubisys, Zênite, Calcgraf não têm acesso aos bancos legacy nem ao schema canônico mapeado em `OFFICEIMPRESSO-FIREBIRD-SCHEMA.md`).

O segundo pilar é **temporal**: o histórico de 5+ anos por cliente (com sazonalidade real, ciclo de inadimplência observado, churn rates por região) cria séries que valem mais que qualquer pesquisa de mercado encomendada. Concorrente que tentar copiar leva 5 anos pra acumular dado equivalente, e nesse tempo a WR já moveu pra produtos preditivos (forecast, churn risk, score de crédito setorial). Em short: **dado histórico longitudinal é moat regulatório-temporal**, não algorítmico — fácil de defender, difícil de atacar. A janela pra lançar é AGORA, antes que (a) clientes migrem pra concorrente e levem o banco junto, ou (b) regulação LGPD endureça opt-out implícito em legacy systems.

---

## Tabela master (15 produtos)

| # | Produto | Customer | Pricing | Delivery | Data source | LGPD risk | Esforço MVP | ROI 12m |
|---|---|---|---|---|---|---|---|---|
| 1 | **Financial Snapshot Tier 1-3** | Cliente OfficeImpresso (38 bases) | R$ 99/299/599 mês | Dashboard web + PDF email + push alerts | `FINANCEIRO`, `MENSALIDADE_FINANCEIRO`, `BOLETOS` por tenant | 🟢 Verde (dado próprio do cliente) | M (110h IA-pair, já especificado) | R$ 65k–145k |
| 2 | **Benchmark Setorial Anônimo** | Cliente OfficeImpresso (38 + futuros oimpresso.com) | R$ 49/m add-on | Dashboard "você vs média gráficas SP/região/porte" | Agregado k-anon ≥5 de FINANCEIRO + VENDA todos tenants | 🟡 Amarelo (precisa opt-in DPA) | M (60h — engine k-anon + UI) | R$ 28k–60k |
| 3 | **Score de Crédito Gráfica B2B** | Fintechs (Asaas, Iugu, Cora, BV+) e seguradoras | R$ 5–15 por consulta API + R$ 2k/m fee fixo | API REST `POST /credit-score {cnpj}` | Histórico pagamento agregado de PESSOAS×FINANCEIRO×BOLETOS cross-tenant | 🔴 Vermelho (revenda dado pra terceiro — exige DPA forte + LGPD Art. 7º base legal "legítimo interesse" + opt-out cliente origem) | G (200h — modelo + API + compliance) | R$ 80k–240k |
| 4 | **Lead Intelligence pra Fornecedores** | HP, Avery, 3M, Mimaki, Roland, distribuidores tinta/vinil | R$ 8k–20k mês por fornecedor (whitelabel) | Dashboard + Excel mensal: "gráficas SP que faturam R$ 30-100k/mês comprando vinil" | VENDA + FINANCEIRO agregado anônimo + segmentação porte/UF | 🟡 Amarelo (perfil agregado, sem PII) | M (80h — query engine + UI white-label) | R$ 192k–480k |
| 5 | **Relatório de Mercado Anual Com. Visual BR** | ABRIGRAF, ABRACOM, SEBRAE, FGV, investidores PE/VC | R$ 4.900/cópia (PDF) ou R$ 14k/ano licença ilimitada | PDF 60 pgs + 1 webinar por release | Agregado nacional + 5 anos histórico | 🟢 Verde (k-anon ≥10, agregado UF) | P (40h primeira edição, depois 20h/ano) | R$ 35k–90k |
| 6 | **Newsletter Setorial "ImpressaInsights"** | Lead magnet B2B — atrai prospects oimpresso + monetiza ads em 12m | R$ 0 (free) → ads R$ 800–2.500 por edição em 12m | Email semanal Mailchimp + RSS | Indicadores macro do banco (volume, ticket médio anonimizado) | 🟢 Verde (só dado agregado) | P (16h setup + 2h/sem operação) | R$ 12k–40k em ads (recompensa real é pipeline indireto) |
| 7 | **API White-Label "Insights Gráfica"** | Software houses concorrentes que queiram embed (Bling, Conta Azul) | R$ 0,50–2 por chamada API + R$ 5k/m mínimo | API REST + SDK PHP/JS | Camada de agregados k-anon + score | 🔴 Vermelho (concorrente direto pode usar contra) | G (160h) | R$ 60k–180k (alto risco competitivo) |
| 8 | **Forecast Receita 90d (preditivo)** | Cliente OfficeImpresso e oimpresso.com | R$ 149/m add-on Tier 2+ | Card no dashboard + alerta WhatsApp | ML simples (Prophet/ARIMA) sobre 24m FINANCEIRO próprio | 🟢 Verde (dado próprio) | M (60h — modelo + integração) | R$ 36k–90k |
| 9 | **Churn Risk Score Cliente Final** | Cliente OfficeImpresso (saber qual cliente DELE vai parar de comprar) | R$ 99/m add-on | Lista priorizada "top 20 clientes em risco" + razão | ML sobre VENDA + FINANCEIRO próprio (sazonalidade + pagamento + engagement) | 🟢 Verde (dado próprio) | M (80h — modelo + UI) | R$ 24k–72k |
| 10 | **Mapa de Expansão Geográfica** | Cliente quer abrir filial nova / fornecedor quer entrar UF | R$ 1.499 por consulta (Cliente) / R$ 8k/m subscription (Fornecedor) | PDF 12 pgs + mapa interativo: densidade gráficas, ticket médio UF, gap competitivo | Agregado VENDA × CIDADE/UF (PESSOAS) + IBGE | 🟡 Amarelo (precisa k-anon ≥5 por cidade) | M (50h) | R$ 45k–120k |
| 11 | **Compliance Fiscal Snapshot** | Cliente OfficeImpresso (alerta NFe vs receita) | R$ 79/m add-on | Dashboard: "receita declarada × NFe emitida — gap" + alerta | NOTA_FISCAL × FINANCEIRO próprio | 🟢 Verde (dado próprio, ajuda compliance) | P (30h) | R$ 28k–70k |
| 12 | **Webinar Trimestral "Saúde do Setor"** | Lead magnet → conversão oimpresso + sponsor fornecedor | R$ 0 + sponsor R$ 5k–15k por evento | Webinar Zoom 60min + replay + slide deck | Mesmo dado do Relatório Anual mas vivo | 🟢 Verde | P (20h por edição) | R$ 40k–120k em sponsor |
| 13 | **Score de Saúde Operacional** | Cliente próprio (autoavaliação) + investidor/banco (due diligence) | R$ 49/m cliente / R$ 2.500 por relatório due diligence | Score 0-100 + 5 dimensões (caixa, AR, churn, growth, fiscal) | Multi-tabela próprio cliente | 🟢 Verde (próprio) → 🟡 Amarelo (DD: cliente autoriza release pro investidor) | M (60h) | R$ 30k–80k |
| 14 | **Histórico de Adimplência B2B (consulta CNPJ→CNPJ)** | Cliente OfficeImpresso quer saber se PROSPECT dele paga em dia | R$ 19 por consulta ou R$ 199/m (50 consultas) | Web app + API: "CNPJ X paga em dia em N gráficas (anônimas) — score Y" | PESSOAS.CNPJCPF × FINANCEIRO histórico cross-tenant | 🔴 Vermelho (revenda dado de comportamento de pagamento de PJ — exige DPA + base legal forte; PJ tem proteção menor que PF na LGPD mas ainda exige cuidado) | G (140h — engine + LGPD + UI) | R$ 60k–180k |
| 15 | **Dataset Anônimo Pesquisa Acadêmica** | USP, FGV, FIA, PUC — research grants | R$ 8k–25k licença por dataset × pesquisa | CSV + dicionário de dados via Google Drive licenciado | Dump anonimizado k-anon ≥10 + DP noise | 🟢 Verde (totalmente desidentificado) | P (24h por release) | R$ 16k–50k (low volume, high margin, branding acadêmico) |

---

## Detalhe por produto

### Produto 1: Financial Snapshot (Tier 1-3)

Já especificado em [`feature-financial-snapshot-multi-cliente.md`](../../decisions/proposals/feature-financial-snapshot-multi-cliente.md). Cliente conecta o banco Firebird local via VPN/tunnel e recebe dashboard semanal/mensal com caixa, AR/AP vencidos, alertas de déficit. Wedge brutal porque conversa começa com valor entregue (não com pitch).

**Caso de uso real**: piloto ServidorWR2 (2026-05-09) revelou déficit -R$ 68k 12m + R$ 868k em atrasos que Wagner não tinha visibilidade clara. Cliente não sabe estado real → paga pra saber.

**Mockup do output (PDF email)**:
```
═══ FINANCIAL SNAPSHOT — [Gráfica X] — Maio/2026 ═══

📊 12 meses (jun/25 → mai/26)
   Receita recebida    R$ 1.247.890  (+8,2% vs ano anterior)
   Despesa paga        R$ 1.318.450
   ⚠️ Déficit operacional   -R$ 70.560

🚨 Atenção imediata
   • R$ 142k em atraso > 90d (5 clientes — ver pág 4)
   • Top atraso: CLIENTE-007 R$ 38k (vencido 127d)
   • MRR contratos vivos: R$ 89.450 — 12 contratos sem valor
     definido precisam reconciliar

📈 Tendência 6m: receita estável (-2%), despesa subindo (+11%)
                 → projeção 90d: déficit acumula ~R$ 35k
```

---

### Produto 2: Benchmark Setorial Anônimo

Cliente vê na própria tela "sua gráfica vs média de gráficas SP, porte similar (faturamento R$ 50-150k/m)". Compara: ticket médio, % atrasos, MRR contratos, top categoria de gasto. Tudo agregado com k-anonymity ≥5 (mínimo 5 gráficas no segmento, senão "amostra insuficiente"). Add-on barato (R$ 49/m) — vira retention killer porque cliente fica viciado em comparar.

**Caso de uso**: gráfica em Campinas porte médio descobre que tem ticket médio 23% abaixo da média SP — usa pra justificar reajuste de preço sem perder noites de sono.

**Mockup do output (card no dashboard)**:
```
🏆 BENCHMARK — Você vs Gráficas similares (SP, R$ 50-150k/m, n=12)

Ticket médio venda    Você R$ 487    Média R$ 632    🔴 -23%
% atrasos > 30d       Você 18%       Média 11%       🔴 +7pp
MRR contratos         Você R$ 14k    Média R$ 22k    🟡 -36%
% receita recorrente  Você 28%       Média 41%       🟡 -13pp

💡 Sugestão Jana: revisar tabela de preços + ativar lembretes
   automáticos cobrança 5d antes vencimento (Top 3 ações)
```

---

### Produto 3: Score de Crédito Gráfica B2B (vendido pra fintechs)

API que recebe `{cnpj_grafica}` e retorna score 0-1000 baseado em histórico de pagamento agregado nos 37+ bancos. Asaas/Iugu/Cora pagam **R$ 5-15 por consulta** porque hoje gastam mais com Serasa/Boa Vista que cobrem mal o nicho gráfico (Serasa não tem visibilidade em fornecedor de tinta/vinil que vende parcelado pra gráfica).

**Caso de uso**: Asaas vai aprovar Antecipação de Recebíveis pra Gráfica X (CNPJ Y). Liga API WR → score 720 (paga em dia há 4 anos em 6 fornecedores rastreados). Asaas aprova mais rápido + taxa menor → repassa parte do ganho como fee.

**LGPD vermelho — mitigação obrigatória**:
- DPA com cada cliente origem permitindo "uso agregado anônimo do histórico de pagamento dos clientes finais pra score B2B (sem expor identidade do cliente final)"
- Base legal: legítimo interesse (LGPD Art. 7º IX) + transparência ativa
- Opt-out fácil pro cliente origem
- Score só pra PJ (CNPJ), nunca PF
- Auditoria de uso da API + log imutável

**Mockup**:
```json
POST /v1/credit-score
{ "cnpj": "12.345.678/0001-90" }

200 OK
{
  "score": 720,
  "tier": "B+",
  "history_months": 48,
  "data_points": 156,
  "indicators": {
    "on_time_payment_rate": 0.94,
    "avg_delay_days": 4.2,
    "max_delay_observed": 23,
    "concentration_risk": "low",
    "trend_12m": "stable"
  },
  "data_freshness": "2026-05-08",
  "tos_version": "v2.1"
}
```

---

### Produto 4: Lead Intelligence pra Fornecedores (HP, Avery, 3M, Mimaki…)

Fornecedor de tinta/vinil/máquinas paga R$ 8-20k/mês por dashboard que mostra: "232 gráficas em SP+RJ faturando R$ 30-100k/mês, comprando categoria X (proxy via VENDA + descrição produto), com tendência crescimento Y". Permite o fornecedor priorizar onde gastar SDR/visita comercial.

**Caso de uso**: HP quer lançar plotter novo R$ 80k. Compra dashboard WR. Filtra: SP+RJ+MG, gráficas R$ 50-200k/m faturamento (porte que cabe no plotter), atualmente sem plotter dessa faixa (proxy: ticket médio venda < threshold). Saída: lista de 87 gráficas qualificadas → SDR HP liga já sabendo perfil.

**Importante**: NUNCA expor PII (nome/CNPJ específico). Apenas perfis agregados + "região-porte-tendência". Quem quiser identificação contrata serviço de SDR-as-a-Service da WR (aí WR liga, não vende a lista).

**Mockup**:
```
🎯 SEGMENTO: Gráficas SP+RJ — Médio porte (R$ 50-150k/m)
                Categoria: alta conversão sublimação têxtil

   Total identificadas:        232
   Crescimento 12m:            +14%
   Ticket médio compra:        R$ 4.230
   Frequência média:           2.1 compras/mês de fornec. tinta

   Por sub-região:
   ├─ Grande SP             87
   ├─ Interior SP           54
   ├─ Grande RJ             41
   └─ Interior RJ           50

   📞 Quer abordar? Contrate SDR-as-a-Service (R$ 80/lead qualif.)
```

---

### Produto 5: Relatório de Mercado Anual Com. Visual BR

PDF 60 páginas estilo "Estado da Comunicação Visual no Brasil 2026" com macroindicadores derivados do agregado: faturamento médio por porte, % de NFe vs informalidade observada, sazonalidade, top 10 categorias, churn rate. Vendido pra ABRIGRAF, ABRACOM, SEBRAE (interesse setorial), FGV/USP (academia), e investidores PE/VC olhando consolidação do setor.

**Caso de uso**: PE fund avaliando comprar grupo de gráficas. Quer entender setor sem custar R$ 250k em consultoria McKinsey. Compra relatório WR R$ 14k licença ilimitada → distribui pro investment committee.

**Mockup capítulos**:
```
1. Sumário executivo (3 takeaways)
2. Tamanho de mercado estimado (top-down × bottom-up via WR data)
3. Distribuição UF + porte
4. Sazonalidade (calendário 12m)
5. Indicadores financeiros médios (margem, AR aging, MRR)
6. Tendências 5y (digitalização, m² adesivo crescendo, têxtil sub.)
7. Riscos (inadimplência, regulação fiscal, dólar/insumos)
8. Apêndice metodológico + k-anon disclosure
```

---

### Produto 6: Newsletter "ImpressaInsights"

Email semanal grátis com 3 indicadores macro (volume agregado, ticket médio, alerta sazonalidade) + 1 dica operacional + 1 case study anonimizado. Lead magnet B2B — coleta email → top of funnel pra oimpresso. Em 12m, monetiza com 1-2 ads de fornecedor (HP, Mimaki, Avery) por edição.

**Mockup**:
```
ImpressaInsights #023 — 9/maio/2026

📊 Esta semana, 232 gráficas em SP movimentaram R$ 4.7M
   (-3% vs semana anterior — Dia das Mães caindo num domingo)

💡 DICA Operacional
   Faturou R$ 30k de NFC-e mas só emitiu R$ 18k? Risco fiscal.
   [Ver checklist 5min]

🏆 CASE — Gráfica em Campinas reduziu inadimplência 18% → 9%
   em 4 meses sem trocar de sistema. Como? [Ler post]

🎯 PATROCINADOR
   HP Latex 700: rendimento R$/m² 22% melhor que rivais.
   [Demo agendada]

[Descadastrar]
```

---

### Produto 7: API White-Label "Insights Gráfica"

Bling/Conta Azul/concorrente embeda widget "score de saúde do seu cliente gráfica" no dashboard deles. WR cobra R$ 0,50-2 por chamada + mínimo R$ 5k/m. **RISCO ALTO**: concorrente direto pode usar pra benchmarking competitivo. Recomendado SÓ pra integradores que NÃO competem (Conta Azul OK, Bling OK; Calcgraf NÃO).

---

### Produto 8: Forecast Receita 90d

ML simples (Prophet/ARIMA) sobre histórico próprio do cliente → previsão receita próximos 90d com intervalo de confiança. Card no dashboard. R$ 149/m add-on Tier 2+.

**Mockup**:
```
📈 FORECAST 90d — Junho a Agosto 2026

   Junho       R$ 142k ± 18k    🟢 Confiança alta (sazonalidade clara)
   Julho       R$ 156k ± 24k    🟡 Confiança média
   Agosto      R$ 148k ± 31k    🟡 Confiança média

   ⚠️ Alerta: julho costuma ter pico de devolução +12% — provisione
              R$ 18k margem extra pra pagar fornecedor cedo
```

---

### Produto 9: Churn Risk Score Cliente Final

Cliente OfficeImpresso descobre quais dos PRÓPRIOS clientes vão parar de comprar (modelo treinado com sazonalidade + recência + valor + tendência pagamento). Lista priorizada Top 20.

**Mockup**:
```
🚨 Top 5 clientes em risco de churn — atue ESTA SEMANA

1. CLIENTE-A14 (R$ 8.2k/m histórico)
   Última compra: 78d atrás (era a cada 22d)
   Pagamento: piorou nos últimos 3m
   → Sugestão: ligar oferecendo desconto 5% próximo pedido

2. CLIENTE-A07 (R$ 5.8k/m)
   ...
```

---

### Produto 10: Mapa de Expansão Geográfica

Dashboard interativo + PDF: densidade de gráficas por município, ticket médio, gap competitivo (cidades com poucas gráficas vs poder de compra IBGE). Vendido como consulta pontual (R$ 1.499) pra cliente decidindo abrir filial, ou subscription (R$ 8k/m) pra fornecedor entrar em região.

---

### Produto 11: Compliance Fiscal Snapshot

Compara `FINANCEIRO.RECEBIDA` vs `NOTA_FISCAL` emitida → calcula gap. Alerta vermelho quando gap > X%. R$ 79/m add-on. Argumento de venda: "evita autuação Receita Federal". Dado próprio do cliente — LGPD verde.

---

### Produto 12: Webinar Trimestral

Mesmo dado do Relatório Anual, mas vivo, 1h Zoom, com Q&A. Sponsor (HP, Mimaki) paga R$ 5-15k por evento pra apresentar 5min + ter logo. Replay vira lead magnet.

---

### Produto 13: Score de Saúde Operacional

Score 0-100 + 5 dimensões (caixa, AR, churn, growth, fiscal). Cliente vê na própria. Versão "due diligence" entrega PDF assinado digitalmente que o cliente leva pro banco/investidor — R$ 2.500 por relatório.

---

### Produto 14: Histórico de Adimplência B2B (CNPJ→CNPJ)

Gráfica X quer saber se PROSPECT Gráfica Y (CNPJ Z) paga em dia. Consulta API WR: "Gráfica Z paga em dia em 4 fornecedores rastreados, score 680 — atrasou 1 vez 12d". R$ 19/consulta. **LGPD vermelho** — exige DPA forte + base legal + opt-out. PJ tem proteção menor que PF mas ainda demanda cuidado.

---

### Produto 15: Dataset Anônimo Pesquisa Acadêmica

USP/FGV/PUC pagam R$ 8-25k por dataset anonimizado pra pesquisa (artigo, dissertação, mestrado). Branding acadêmico vale mais que receita direta — paper publicado mencionando "WR Sistemas dataset" vira moat reputacional.

---

## Análise consolidada

### Top 3 maior ROI absoluto (12m)
1. **Lead Intelligence Fornecedores (#4)** — R$ 192k–480k. Tickets altos (R$ 8-20k/m) com poucos clientes (5-20 fornecedores). Esforço médio.
2. **Score de Crédito Gráfica B2B (#3)** — R$ 80k–240k. Volume alto de consultas paga bem; mas LGPD exige investimento upfront sério.
3. **Histórico de Adimplência B2B (#14)** — R$ 60k–180k. Volume médio + ticket médio + retenção alta.

### Top 3 mais fáceis de lançar (low effort, demanda real)
1. **Financial Snapshot Tier 1-3 (#1)** — já especificado, dado próprio (verde), demanda validada (Wagner já viu valor no piloto). 110h IA-pair.
2. **Compliance Fiscal Snapshot (#11)** — 30h, dado próprio, dor real (medo de Receita Federal vende sozinho).
3. **Newsletter ImpressaInsights (#6)** — 16h setup, lead magnet barato, abre porta pros outros produtos.

### Top 3 maior diferenciação (concorrente não tem como copiar)
1. **Relatório de Mercado Anual (#5)** — Mubisys/Zênite/Calcgraf não têm 5 anos de histórico cross-cliente. Wagner é incumbente única.
2. **Score de Crédito Gráfica (#3)** — Serasa não cobre nicho; concorrente novo precisa 5 anos pra acumular dado.
3. **Benchmark Setorial Anônimo (#2)** — só faz sentido com volume crítico (ele tem 38 bases; concorrente novo tem 0-3).

### Sequenciamento sugerido (qual lançar primeiro)
**Fase 1 (próximos 30 dias) — VERDES + BAIXO ESFORÇO**:
- Newsletter (#6) — semana 1, lead magnet
- Financial Snapshot Tier 1 MVP (#1) — semanas 1-3
- Compliance Fiscal (#11) — semana 4

**Fase 2 (30-90 dias) — AMARELOS COM DPA**:
- Benchmark Setorial (#2) — depois de DPA assinado pelos primeiros 5 clientes
- Mapa Expansão (#10)
- Forecast (#8) + Churn Risk (#9) — add-ons

**Fase 3 (90-180 dias) — VERMELHOS COM COMPLIANCE PESADA**:
- Score Crédito B2B (#3) — exige DPO + ADR + base legal sólida
- Histórico Adimplência (#14)

**Fase 4 (180+ dias) — VENDA INSTITUCIONAL**:
- Relatório Anual (#5)
- Webinar (#12)
- Dataset Acadêmico (#15)
- Lead Intelligence Fornecedores (#4) — exige sales B2B com SDR dedicado

---

## Riscos transversais

### LGPD compliance
- **Mitigação obrigatória**: DPO formalizado, ADR mãe sobre uso secundário do dado, DPA por cliente origem, opt-out simples, k-anonymity ≥ 5 default (≥ 10 em release público), audit log imutável de cada query, base legal documentada (legítimo interesse com LIA — Legitimate Interest Assessment).
- **Cenário pior**: ANPD recebe reclamação de cliente final dizendo "minha gráfica forneceu meu dado de pagamento pra terceiro sem consentimento" → multa até 2% do faturamento (R$ 50M cap). Mitigação: cláusula no contrato OfficeImpresso autorizando uso agregado anônimo + comunicação ativa pré-lançamento.

### Cliente reagir mal (sentir invadido)
- **Cenário**: Larissa (ROTA LIVRE) descobre que Wagner usou o dado dela pra montar Score de Crédito vendido pra Asaas → relação de confiança quebra → churn + word-of-mouth ruim no setor (gráficas se conhecem em SP).
- **Mitigação**: comunicação proativa em todo lançamento de produto novo ("estamos lançando X, seu dado é usado de forma Y, você pode optar fora aqui"), revenue share com cliente origem (10% da receita do produto que usa o dado dele volta como crédito), case study anonimizado SEMPRE com aprovação por escrito.
- **Princípio**: cliente é parceiro, não fonte de matéria-prima. Se Wagner não consegue olhar Larissa nos olhos e contar do produto, o produto não pode existir.

### Concorrente copiar (após 1º cliente público)
- **Mitigação**: moat é o dado histórico (5+ anos, não-replicável em <5 anos). Concorrente pode anunciar produto similar mas não terá dado. Marketing: "WR tem 5 anos de histórico em 37 gráficas — concorrente Y tem 6 meses em 2 gráficas".

### Custo de manutenção (cada produto vira passivo)
- **Cenário**: lançou 12 produtos, 3 dão receita boa, 9 viram dívida técnica + suporte sugando time.
- **Mitigação**: kill switch trimestral — produto que não atingiu 30% do ROI projetado em 6m é descontinuado. Comunicação clara de sunset 60d antes. Foco em 3-5 produtos vencedores em vez de portfolio espalhado.

---

## ROI agregado se Wagner lançar Top 5 priorizados

Top 5 sugeridos (mix de quick-win + diferenciação + receita):
1. Financial Snapshot Tier 1-3 (#1) — R$ 65k–145k
2. Compliance Fiscal (#11) — R$ 28k–70k
3. Benchmark Setorial Anônimo (#2) — R$ 28k–60k
4. Forecast Receita 90d (#8) — R$ 36k–90k
5. Lead Intelligence Fornecedores (#4) — R$ 192k–480k

**Cenário conservador agregado 12m**: R$ 349k
**Cenário realista agregado 12m**: R$ 845k
**% da meta R$ 5M/ano**: 7% conservador / 17% realista

Combinado com receita de assinatura oimpresso.com tradicional (Starter/Pro/Enterprise pricing tiers já definidos), atinge meta R$ 5M ano com **diversificação de risco** — não dependendo só de SaaS subscription puro. Data products têm margem 80%+ (custo marginal de servir cliente extra é desprezível) — são o caminho natural pra escalar sem virar consultoria humana intensiva.

---

## Próximos passos sugeridos

1. **Wagner valida** este catálogo (descarta produtos que considera fora de visão estratégica)
2. **DPO formalizado** + ADR mãe "uso secundário de dado de cliente OfficeImpresso" antes de qualquer produto Amarelo/Vermelho
3. **Financial Snapshot MVP** (#1) lança como já especificado em [`feature-financial-snapshot-multi-cliente.md`](../../decisions/proposals/feature-financial-snapshot-multi-cliente.md)
4. **Newsletter (#6)** sobe semana 1 — lead magnet barato que valida apetite de mercado
5. **Compliance Fiscal (#11)** semana 4 — quick win com dor real
6. **Benchmark (#2)** depois de 5 clientes Snapshot ativos com DPA assinado
7. ADR per produto antes de cobrar primeira fatura
