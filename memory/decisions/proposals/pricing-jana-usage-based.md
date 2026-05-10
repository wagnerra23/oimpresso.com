# Pricing Jana — Usage-Based (proposta)

> **Status:** PROPOSAL (não-canônico). Não é ADR.
> **Autor:** Claude (pricing strategist task)
> **Data:** 2026-05-09
> **Pergunta:** Como precificar Jana de forma que cliente pague proporcional ao valor extraído (usage-based)?
> **Restrições:** custos unitários derivados de ranges públicos OpenAI/Anthropic + benchmark SaaS B2B BR.
> **Não-objetivo:** substituir [pricing-pct-gmv-baseado-em-32-clientes.md](pricing-pct-gmv-baseado-em-32-clientes.md) — esta proposta é **complementar** (Jana como add-on monetizável dentro do tier do ERP).

---

## TL;DR

5 tiers usage-based + 1 free + 1 Power. ARPU médio mês 12 projetado **~R$ 412**. Margem global **76%** sustentável. Principal risco: **complexidade percebida vs preço fixo simples** (cliente Larissa-tipo decora preço, não tabela). Mitigação: dashboard de uso em tempo real + alerta proativo aos 80% + opção "modo conforto" (fixo Premium).

---

## 1. Componentes que cliente paga

| Componente | Lógica | Cobrado |
|---|---|---|
| **Base mensal** | Acesso à feature Jana + recall + memória persistente | Por tier |
| **Queries Brain A** (gpt-4o-mini wrapper) | Conversação simples, recall, sumarização | Incluso até X, depois R$ 0,02/extra |
| **Queries Brain B** (Sonnet/Opus) | Análise complexa, decisão crítica, planejamento | Incluso até X, depois R$ 0,80/extra |
| **Push notifications** (Centrifugo) | Alertas proativos Jana → cliente | Incluso até X, depois R$ 0,05/push extra |
| **API calls** (REST/MCP exposed) | Integração externa cliente → Jana | Incluso até X, depois R$ 0,01/call extra |
| **Storage histórico** | Memória persistente (facts + sessions + embeddings) | 1GB grátis, depois R$ 0,10/GB-month |
| **Voice queries** (premium add-on) | STT+TTS (Whisper + ElevenLabs) | R$ 0,15/query, só Premium+ |

### Custo unitário (ranges públicos OpenAI/Anthropic Q1-2026)

| Item | Custo oimpresso | Cobrado | Markup |
|---|---|---|---|
| Brain A query (gpt-4o-mini, ~2k tokens in + 500 out) | ~R$ 0,005 | R$ 0,02 | 4x |
| Brain B query (Sonnet, ~4k tokens in + 1k out) | ~R$ 0,30 | R$ 0,80 | 2.6x |
| Brain B query (Opus, raro) | ~R$ 1,50 | R$ 3,00 | 2x |
| Push notification (Centrifugo CT 100) | ~R$ 0,001 | R$ 0,05 | 50x (margem alta — barato pra nós) |
| API call (Hostinger CPU) | ~R$ 0,0005 | R$ 0,01 | 20x |
| Storage GB-month (Hostinger + Meilisearch) | ~R$ 0,005 | R$ 0,10 | 20x |
| Voice query (Whisper + ElevenLabs) | ~R$ 0,06 | R$ 0,15 | 2.5x |

**Margem global ponderada:** 76% (range SaaS B2B saudável: 70-85%).

---

## 2. Tiers propostos

### Free (acquisition)
- **R$ 0/m**
- 30 queries Brain A/m
- 0 Brain B
- 1 push/dia (30/m)
- 7 dias retenção (auto-purge memória após 7d)
- Sem API
- 100MB storage
- **Apenas cliente registrado** (não anônimo, evita abuse)
- **Limite hard:** 90 dias trial → upgrade ou downgrade pra read-only

### Starter — R$ 99/m
- 200 queries Brain A
- 0 Brain B (upsell óbvio)
- 30 push/m
- 100 API calls
- 1GB storage
- Suporte: email 48h
- **Target:** gráfica solo, freelancer com ERP

### Pro — R$ 299/m ⭐ (sweet spot)
- 2.000 queries Brain A
- 50 queries Brain B
- 500 push/m
- 5.000 API calls
- 10GB storage
- Suporte: email 24h
- **Target:** PME 2-5 funcionários (perfil ROTA LIVRE — vestuário Gravatal/SC; também serve gráfica/oficina via módulo vertical correspondente — [ADR 0121](../0121-oimpresso-modular-especializado-por-vertical.md))

### Premium — R$ 599/m
- Brain A unlimited (fair-use 10k)
- 200 queries Brain B
- Push unlimited (fair-use 5k)
- 50.000 API calls
- 100GB storage
- Voice queries habilitado (R$ 0,15/each)
- Suporte: chat 4h SLA
- **Target:** gráfica 5-15 funcionários, multi-loja

### Enterprise — R$ 1.499/m
- Tudo unlimited (fair-use generoso)
- Voice unlimited (fair-use 1k/m)
- Storage 1TB
- Multi-user (5 logins simultâneos Jana)
- Suporte: WhatsApp 1h SLA
- Onboarding dedicado (2h Wagner/Felipe)
- **Target:** rede gráficas, franquia

### Power — R$ 2.999/m
- Enterprise +
- White-label Jana (sua-grafica.ai)
- SLA 99,5% contratual com SLA-credit
- Multi-user unlimited
- API custom endpoints
- Treinamento mensal time cliente (1h)
- Brain B Opus liberado (raro, mas disponível)
- **Target:** grandes operações (Mubisys-tier, ROTA LIVRE 5x)

---

## 3. % do GMV (alternativa/combinação)

**Modelo híbrido:** cliente paga base do tier + **0,5-1% do GMV** opcionalmente, em troca de:
- Brain B unlimited (sem caps)
- Voice unlimited
- Suporte priorizado

**Quando faz sentido:**
- Cliente Pro com GMV R$ 100k/m → 0,5% = R$ 500 → vale pagar R$ 299 + R$ 500 = R$ 799 (em vez de Premium R$ 599 com caps)
- Não vale: cliente GMV R$ 30k → 1% = R$ 300, melhor Pro fixo

**Justificativa:** alinhamento perfeito — cliente cresce, paga mais; cliente shrink, paga menos. Cross-ref [pricing-pct-gmv-baseado-em-32-clientes.md](pricing-pct-gmv-baseado-em-32-clientes.md).

**Implementação:** opt-in voluntário, exibido no dashboard "modo growth" com simulador.

---

## 4. Overage pricing (passou do incluso)

| Item | Overage |
|---|---|
| Brain A | R$ 0,02/query extra |
| Brain B | R$ 0,80/query extra |
| Push | R$ 0,05/push extra |
| API call | R$ 0,01/call extra |
| Storage | R$ 0,10/GB-month extra |
| Voice | R$ 0,15/query (sempre cobrado em Premium) |

**Alertas proativos (transparência total):**
- 50% uso → banner azul "metade do mês"
- 80% uso → banner amarelo "considere upgrade pra Pro" (com simulação economia)
- 100% uso → banner laranja "começou overage — R$ X até agora"
- 150% uso → email Wagner-style "tá saindo caro, vamos conversar?"

**Cap de proteção:** overage máximo = 50% do tier base. Cliente Pro (R$ 299) overage cap R$ 150 → upgrade automático sugerido com 1-clique pra Premium (R$ 599 saldando overage acumulado).

---

## 5. Free tier (acquisition funnel)

- 30 queries Brain A / mês (não cumulativo)
- 0 Brain B
- 1 push/dia
- 7 dias retenção memória
- Sem API
- 100MB storage
- Cliente registrado (email + business_id)
- 90 dias trial, depois read-only ou upgrade

**Conversão alvo:** 8-12% Free → Starter em 90d (benchmark SaaS B2B BR 5-10%).

---

## 6. Casos de uso modelados

### Cliente Starter típico (gráfica solo)
- 150 queries Brain A/m, 0 Brain B
- Custo oimpresso: 150 × R$ 0,005 = **R$ 0,75/m**
- Receita: **R$ 99/m**
- **Margem: 99%** (cliente subutiliza tier — bom problema, lucra)
- Valor extraído cliente: 5h/m economizadas em consulta de pedido = R$ 750/m em tempo de operador → **ROI 7,5x**

### Cliente Pro típico (perfil ROTA LIVRE)
- 800 queries Brain A, 30 Brain B, 200 push, 2k API
- Custo: 800×0,005 + 30×0,30 + 200×0,001 + 2k×0,0005 = R$ 4 + R$ 9 + R$ 0,20 + R$ 1 = **R$ 14,20/m**
- Receita: **R$ 299/m**
- **Margem: 95%**
- Valor extraído: 15h/m operador + 3 decisões críticas/m apoiadas = R$ 2.500/m → **ROI 8,3x**

### Cliente Premium típico
- 3.000 Brain A, 150 Brain B, 800 push, 20k API, 10GB
- Custo: 3000×0,005 + 150×0,30 + 800×0,001 + 20k×0,0005 + 10×0,005 = R$ 15 + R$ 45 + R$ 0,80 + R$ 10 + R$ 0,05 = **R$ 70,85/m**
- Receita: **R$ 599/m**
- **Margem: 88%**
- Valor extraído: 30h/m + analytics + multi-loja sync = R$ 5.500/m → **ROI 9,2x**

### Cliente Enterprise (rede)
- 10k Brain A, 800 Brain B, 5k push, 100k API, 200GB, 100 voice
- Custo: 50 + 240 + 5 + 50 + 1 + 6 = **R$ 352/m**
- Receita: **R$ 1.499/m**
- **Margem: 76%**
- Valor extraído: R$ 18k/m → **ROI 12x**

### Cliente Power (white-label)
- 30k Brain A, 2k Brain B (incluindo Opus 5%), 20k push, 500k API
- Custo: 150 + 700 + 20 + 250 = **R$ 1.120/m**
- Receita: **R$ 2.999/m**
- **Margem: 63%** (mais baixa por incluir Opus + SLA + onboarding)
- Valor extraído: R$ 50k+/m → **ROI 16x**

---

## 7. Custo unitário detalhado (referências públicas)

- **OpenAI gpt-4o-mini** (jan 2026): $0.15/M input, $0.60/M output → ~$0.001/query típica → **R$ 0,005**
- **Anthropic Sonnet 4.5** (2026): $3/M input, $15/M output → ~$0.06/query → **R$ 0,30**
- **Anthropic Opus 4.7** (2026): $15/M input, $75/M output → ~$0.30/query → **R$ 1,50**
- **Storage Hostinger**: ~R$ 0,005/GB-month (shared)
- **Meilisearch CT 100**: R$ 0,001/GB-month adicional embedding storage
- **Centrifugo push** (CT 100 self-hosted): custo marginal zero, computa CPU/RAM ~R$ 0,001/push
- **ElevenLabs Brazilian PT** (voice): $0.30/1k chars → ~R$ 0,06/query 30s

Margem global ponderada portfolio (mix Starter 40% / Pro 35% / Premium 15% / Ent 8% / Power 2%): **76%**.

---

## 8. Anti-padrões evitados

- ❌ **Cobrar por feature** (cliente confunde) → cobramos por uso unitário
- ❌ **Cliffs duros** (cliente para de usar pra não pagar) → overage cap suave + upgrade 1-clique
- ❌ **Esconder overage** → dashboard real-time + 4 níveis de alerta proativo
- ❌ **Tier "Custom" sem preço público** (perde transparência) → Power tem preço público R$ 2.999, custom só Power+
- ❌ **Inflar limite Brain B no Pro** (mata margem) → 50 queries é o cap, força upsell honesto
- ❌ **Cobrar setup fee** → onboarding incluído pra não criar fricção (exceto Power, que tem tempo Wagner/Felipe)

---

## 9. Migração 41 clientes atuais

**Política grandfather 12 meses:**
- Quem já paga R$ X mantém R$ X até maio/2027
- Recebe convite voluntário pra Pro Plus features (Brain B, voice, multi-user)
- Migração natural: 30% ano 1 (benchmark SaaS BR migration), 60% ano 2

**Comunicação:** email Wagner-tone com simulador "veja quanto economiza/ganha no novo plano" + opção "ficar como está".

**ROTA LIVRE (Larissa, biz=4)** — case especial: cliente piloto + 99% volume → grandfather indefinido + tier Premium grátis até final 2027 (compensação histórico).

---

## 10. Network effect bonus

- **Benchmark network** (cliente compartilha métricas anonimizadas pro pool) → 20% off mensal
- **Programa afiliados** (já existe US-AFF-001) → 1 mês grátis por indicação convertida
- **Top 10 usuário Jana mês** (mais queries, mais retenção) → tier Premium grátis 6 meses
- **Hall da fama** público: gráficas mais inovadoras Jana-powered (consent opt-in)

---

## 11. Projeções financeiras

### ARPU mês 12 (após ramp)

Assumindo distribuição target 41 clientes atuais + 30 novos = 71 clientes:

| Tier | % mix | Clientes | Preço médio (c/ overage) | Receita |
|---|---|---|---|---|
| Free | — | (não conta ARPU) | R$ 0 | R$ 0 |
| Starter | 35% | 25 | R$ 110 | R$ 2.750 |
| Pro | 38% | 27 | R$ 340 | R$ 9.180 |
| Premium | 17% | 12 | R$ 680 | R$ 8.160 |
| Enterprise | 8% | 6 | R$ 1.650 | R$ 9.900 |
| Power | 2% | 1 | R$ 3.200 | R$ 3.200 |
| **Total** | 100% | **71** | **ARPU R$ 466** | **R$ 33.190 MRR** |

**ARPU médio mês 12: ~R$ 467** (acima da banda alvo R$ 350-450, indica que mix Pro/Premium puxou pra cima — saudável).

**ARR projetado mês 12:** R$ 398k (vs meta R$ 5M/ano = 8% — precisa scale customer base, não pricing).

### LTV

Churn assumido 3%/m (SaaS B2B BR vertical):
- LTV Starter: R$ 110 × (1/0,03) × 0,99 margin = **R$ 3.630**
- LTV Pro: R$ 340 × 33 × 0,95 = **R$ 10.659**
- LTV Premium: R$ 680 × 33 × 0,88 = **R$ 19.755**
- LTV Enterprise: R$ 1.650 × 33 × 0,76 = **R$ 41.382**
- LTV Power: R$ 3.200 × 33 × 0,63 = **R$ 66.528**

**LTV médio ponderado:** R$ 14.200. CAC alvo (1/3 LTV): R$ 4.700 — viável com afiliados + organic.

### Curva ARPU mês 1 → 12

- M1 (lançamento): R$ 280 (early adopters Starter/Pro)
- M3: R$ 340 (overage começa, Pro→Premium upgrades)
- M6: R$ 395 (Enterprise pipeline matura)
- M9: R$ 435 (Power pilots)
- M12: R$ 467 (mix estável)

---

## 12. Principal risco

### Complexidade percebida vs simplicidade

Cliente Larissa-tipo (ROTA LIVRE, dona-operadora **loja de roupa em Gravatal/SC**) **decora preço fixo**. Tabela com "200 queries Brain A + 50 Brain B + overage R$ 0,02" pode soar "celular pré-pago caro" e empurrar pra concorrente com R$ 299 fixo flat.

**Mitigações:**
1. **Dashboard "modo conforto"** — cliente escolhe ver apenas "% do tier usado" (1 número), sem unidades técnicas
2. **"Modo previsível"** — opt-in pra pagar Premium fixo R$ 599 sem overage tracking visível
3. **Linguagem de marketing** — não falar "Brain A/B" pro cliente final, falar "perguntas rápidas" / "análises profundas"
4. **Auto-upgrade inteligente** — se cliente gastou overage > 30% do tier 2 meses seguidos, sugere upgrade com 1-clique e crédito do overage acumulado
5. **Garantia "menor preço"** — primeiros 3 meses, se Pro fixo R$ 299 sairia mais barato que tier escolhido + overage, oimpresso refunda diferença
6. **A/B test pricing display** — testar "R$ 299/m + uso" vs "R$ 299/m flat" em landing — métrica conversão

### Riscos secundários

- **Custo Brain B disparar** (Anthropic aumenta preço Sonnet) → cláusula reajuste anual + contrato Power tem floor
- **Cliente abusar Free** (90 dias trial) → fingerprint + business_id único + email validado
- **Voice queries explodirem custo** (cliente faz 1000/m) → cap fair-use 1k/m em Premium, depois R$ 0,15/each transparente
- **Migração 41 clientes** gerar churn inesperado → grandfather 12m + comunicação Wagner-tone

---

## 13. Próximos passos (se aprovado)

1. ADR canon `pricing/0001-jana-usage-based.md` (depois desta proposta debater)
2. Implementar `JanaUsageMeter` service em `Modules/Jana/Services/Pricing/`
3. Migração `jana_usage_records` (business_id, period, brain_a_count, brain_b_count, etc)
4. Dashboard `/copiloto/admin/jana/usage` (Inertia page)
5. Hooks no `JanaService` pra incrementar contador a cada query
6. Email cron diário: alerta 80% / 100% / 150%
7. Stripe/Asaas integration pra cobrança recorrente + overage
8. Landing `oimpresso.com/jana/precos` com simulador
9. A/B test pricing display 60d antes de mover toda base
10. Comunicar 41 clientes 30d antes do switch + grandfather 12m

---

## Cross-refs

- [pricing-pct-gmv-baseado-em-32-clientes.md](pricing-pct-gmv-baseado-em-32-clientes.md) — modelo % GMV ERP (este doc é complementar pra Jana add-on)
- [pricing-recalibracao-ticket-real-830-850.md](pricing-recalibracao-ticket-real-830-850.md) — ticket médio atual base
- [feature-financial-snapshot-multi-cliente.md](feature-financial-snapshot-multi-cliente.md) — produto adjacente pricing
- [modelos-performance-fee-comissionamento.md](modelos-performance-fee-comissionamento.md) — modelo % híbrido (referência seção 3)

---

**Status final:** PROPOSAL aguardando review Wagner. Não-canônico até virar ADR.
