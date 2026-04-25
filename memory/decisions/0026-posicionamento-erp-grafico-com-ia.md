# ADR 0026 — Posicionamento estratégico: ERP de Comunicação Visual com IA

**Status:** ✅ Aceita
**Data decisão:** 2026-04-25
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão `redesign-cms-meta-5mi`)
**Relacionado:** ADR 0022 (meta R$5mi/ano), ADR 0025 (redesign Cms Inertia/React)

---

## Contexto

ADR 0022 fixou meta de **R$5mi/ano de faturamento** mas deixou prazo e estratégia de execução em aberto. ADR 0025 redesignou o site público (Modules/Cms) em Inertia/React com copy alinhada ao vertical de comunicação visual. Faltava decidir: **com qual narrativa/posicionamento** vamos competir e **quais features** priorizar pra atingir a meta.

Foram conduzidos 2 researches em 2026-04-25:
1. **Research de marketing** — sites/copy dos concorrentes BR ([`memory/comparativos/site_marketing_concorrentes_comunicacao_visual_2026_04_25.md`](../comparativos/site_marketing_concorrentes_comunicacao_visual_2026_04_25.md))
2. **Matriz Capterra/G2** — feature-by-feature oimpresso vs 8 concorrentes ([`memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md`](../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md))

Findings consolidados:

| Dimensão | Hoje | Mercado |
|---|---|---|
| Stack técnica | Laravel 13.6 + Inertia v3 + React + Tailwind v4 (moderna) | Concorrentes verticais legacy (Delphi/PHP antigo, jQuery) |
| Vertical de gráfica | ❌ não tem cálculo m², FPV, OP gráfica | Mubisys/Zênite/Visua/Calcgraf têm 30+ anos no nicho |
| IA | 🟡 Copiloto + LaravelAI em construção (único no mercado vertical) | Nenhum vertical tem IA real; Omie tem só "IA fiscal" limitada |
| MemCofre | ✅ único no mercado | Ninguém tem cofre de memória corporativa |
| Base instalada | 7 clientes ativos (1 = 99% volume) | Mubisys 14k usuários, Bling 300k+, Omie 180k+ |
| Suporte/operação | ❌ sem chat 24/7, sem academy | Todos os concorrentes verticais oferecem |

3 caminhos de posicionamento avaliados:

| Caminho | Tese | Veredito |
|---|---|---|
| **A** — "Mubisys mais barato" | Copiar feature-set, cobrar 30% menos | ❌ Mubisys tem 14k usuários + 30 anos. Você é desafiante sem narrativa. |
| **B** — "ERP gráfico com IA + cofre de memória" | Único vertical de CV com IA contextual + MemCofre | ✅ Diferencial defensável; ticket premium |
| **C** — "ERP genérico moderno BR" | Competir com Bling/Omie | ❌ Não ganha de R$55/mês do Bling sem queimar caixa |

## Decisão

### Posicionamento adotado (Caminho B)

> **"O ERP de comunicação visual com IA que substitui seu Mubisys/Zênite — e nunca esquece um cliente."**

3 pilares de diferenciação:

1. **Vertical real de comunicação visual** (cálculo por m², OP gráfica end-to-end, fluxo orçamento→contrato→OP→entrega)
2. **IA contextual nativa** via módulo Copiloto (sabe a tela, dados do user, sugere meta, monitora)
3. **MemCofre** como knowledge base corporativa por business — único no mercado

### Math da meta (R$5mi/ano em 24 meses)

R$5mi/ano = **R$417k/mês de MRR**.

Cenário recomendado em [`memory/11-metas-negocio.md`](../11-metas-negocio.md): **Cenário D Misto** = 50 enterprise × R$5k + 120 médios × R$1,5k + 200 pequenos × R$440 = R$418k/mês.

Pra ticket médio R$497/mês: **838 clientes ativos**. Com churn 3%/mês e CAC payback 6m, funil de **~50 leads qualificados/mês**. Realidade hoje: 7 clientes ativos. Gap: **800+ clientes em 24 meses = 33 novos/mês líquidos**. Wagner sozinho não escala — pressupõe contratação a partir de R$50k MRR.

### 3 features prioritárias pra construir (próximos 6 meses, ordem)

1. **PricingFpv** (cálculo por m² + FPV gráfica) — desbloqueia prospect de qualquer gráfica/CV. Sem isso o resto não importa. **3-4 sprints.**
2. **Copiloto v1 production-ready** — sair de "em construção" pra vendável. Foco em 3 use-cases (orçamento histórico, margem média, lembretes fiscais). **2-3 sprints.**
3. **CT-e + MDF-e + conciliação OFX** — mata gap fiscal+financeiro. Sem isso, gráfica que entrega não compra. **3 sprints.**

### O que explicitamente NÃO faremos agora

- **App mobile nativo** — deal-breaker conhecido, mas adiável 12 meses se Copiloto compensar
- **Marketplace/E-commerce nativo** — nunca vamos ganhar do Bling (250+ integrações). Manter Woocommerce sync existente
- **SPED contábil completo** — deixa pra quem migrar de Mubisys, prioridade 2027

### Métrica de fé (90 dias)

Se em 90 dias **PricingFpv + Copiloto v1 estiverem em produção** e:
- ROTA LIVRE virar case com vídeo
- **5 prospects qualificados convertidos via indicação**

→ **confirma a tese.** Senão, **pivota pra Caminho A ou C**.

## Consequências

### Positivas
- Filtro de priorização claro pra próximas sprints (qualquer feature passa pelo "isso ajuda gráfica/CV ou alimenta a IA?")
- Diferencial defensável por 12-18 meses (concorrentes verticais não têm IA, genéricos não têm vertical de CV)
- Permite ticket premium (R$497/mês entre Mubisys e Bling premium) com narrativa que sustenta
- Aproveita stack moderna real (vantagem técnica injustificável de gastar)
- Compatível com revenue thesis dos módulos (ARQ-0004 Financeiro, Copiloto, RecurringBilling)

### Negativas / riscos
- **Concentração em ROTA LIVRE permanece** durante a janela de construção (12-18 meses). Se ROTA LIVRE churn antes, meta fica em risco
- Posicionamento "vertical CV" pode reduzir TAM aparente vs "ERP genérico" — mitigado por manter UltimatePOS base + outros segmentos como satélites
- **Wagner sozinho** não opera 838 clientes — premissa: contratar CS a partir de R$50k MRR
- Se Copiloto não entregar a fantasia de IA contextual, perdemos a narrativa principal
- Concorrentes verticais (Mubisys especialmente) podem reagir adicionando IA em 12-18 meses

### Riscos de execução
| Risco | Mitigação |
|---|---|
| PricingFpv ficar pronto sem cliente pra validar | Validar com ROTA LIVRE antes de prospectar |
| Copiloto v1 demorar mais que 3 sprints | Cortar use-cases secundários, manter os 3 principais |
| Sem suporte/academy/treinamento, churn dispara | Documentar via MemCofre como produto interno; contratar pessoa em R$30k MRR |
| Gráficas não confiam em "ERP novo" | ROTA LIVRE como case principal + 90 dias com 5 indicações = social proof |

## Plano de execução (próximas 4 semanas)

### Semana 1
- [ ] Wagner valida visualmente PR1+PR2 do redesign Cms (commits aabe142d, 3fd21e6b, 9ffa56c2 na branch `claude/cms-react-redesign`)
- [ ] Push pra GitHub + deploy SSH na Hostinger
- [ ] Atualizar copy do site com posicionamento "ERP de comunicação visual com IA" (PR3)
- [ ] Criar SPEC do `Modules/PricingFpv` em `memory/requisitos/PricingFpv/`

### Semana 2-3
- [ ] Implementar PricingFpv MVP (tabela de preço por substrato + cálculo m² no orçamento existente)
- [ ] Validar com ROTA LIVRE: pega 5 orçamentos antigos da Larissa e refaz no PricingFpv. Compara.

### Semana 4
- [ ] Copiloto v1: tirar do "em construção", entregar 1 use-case end-to-end ("qual foi o orçamento dessa cliente ano passado")
- [ ] Material de venda: vídeo de 60s do fluxo orçamento→OP→entrega da ROTA LIVRE

## Métricas a acompanhar (mensal)

| Métrica | Hoje | Meta 90d | Meta 24m |
|---|---|---|---|
| Clientes ativos | 7 | 12 | 838 |
| MRR | ⚠️ não medido | R$10k | R$417k |
| Churn mensal | ⚠️ não medido | <5% | <3% |
| Leads qualificados/mês | ⚠️ não medido | 5 (via indicação ROTA LIVRE) | 50 |
| % volume não-ROTA LIVRE | 1% | 5% | 50% |

## Relação com outras ADRs

- **ADR 0022** (Meta R$5mi/ano) — este ADR dá a estratégia de execução pra atingir a meta
- **ADR 0025** (Redesign Cms Inertia/React) — site novo já está alinhado com este posicionamento (verbo-de-ação, métrica m², features OP/m² no FeatureGrid)
- **ADR 0023** (Inertia v3 upgrade) — base técnica que viabiliza velocidade de iteração
- **Revenue thesis dos módulos** ([`memory/claude/reference_revenue_thesis_modulos.md`](../claude/reference_revenue_thesis_modulos.md)) — pricing tiers compatíveis

## Documentos-fonte

- [Matriz Capterra/G2](../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)
- [Research marketing/copy](../comparativos/site_marketing_concorrentes_comunicacao_visual_2026_04_25.md)
- [Cenários R$5mi (11-metas)](../11-metas-negocio.md)
- [Revenue thesis dos módulos](../claude/reference_revenue_thesis_modulos.md)

---

**Última atualização:** 2026-04-25
