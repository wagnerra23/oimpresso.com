---
name: Plano de Crescimento oimpresso — vender mais / lucrar mais (gerência 2026-05-31)
description: Estratégia de receita consolidada de 4 pesquisas de mercado paralelas (pricing, monetização carteira legacy, IA-como-receita, economia WhatsApp/Agrosys). Diagnóstico + 4 alavancas priorizadas + pricing recomendado + playbook 90 dias + de-risk Agrosys. Wagner pediu "seja o gerente e reestruture pra vender mais/lucrar mais".
type: project
---

# 📈 Plano de Crescimento oimpresso — vender mais / lucrar mais

> **Pedido do Wagner (2026-05-31):** *"Seja o gerente e reestruture como posso vender mais ou ter mais lucro. Pesquise na internet e direcione o projeto."*
> **Método:** diagnóstico interno (brief + docs canon de receita/cliente) + 4 pesquisas de mercado paralelas 2025-2026 (53 buscas/fetches, ~320k tokens). Fontes ao final.

---

## 🎯 TL;DR — a tese de gerente em 3 frases

1. **Você não tem problema de produto. Tem problema de monetização.** Tem produto bom + uma carteira morna de 50 negócios reais (26 anos de WR Sistemas) — e está convertendo ~1.
2. **A alavanca mais rápida, barata e certa é a carteira legacy:** você é o único no mundo que pode demonstrar o oimpresso já populado com os 26 anos de histórico real do prospect (importador Firebird→MySQL já validado no Martinho). Isso colapsa os 2 maiores medos de PME — perder dados e dar trabalho migrar.
3. **A Jana (IA conversacional com memória) é seu fosso:** nenhum concorrente BR tem. É a arma de venda (killer demo), a justificativa de preço premium, e o motor de retenção. **Agrosys é a baleia — mas é outra corrida; de-risca em paralelo SEM gastar cycle de dev até o contrato assinar.**

**A mudança de comportamento nº1:** o brief mostrou **104/104 commits dos últimos 7 dias NÃO tocam receita**. Você está construindo em vez de vender. O ROI da próxima hora de código é menor que o ROI de 90 min/dia ligando pra carteira morna.

---

## 🔍 Diagnóstico (a verdade crua dos dados)

| Sinal | Número | Implicação |
|---|---|---|
| Businesses cadastrados | 56 | — |
| Com qualquer venda | 7 | — |
| Cliente real (volume) | **1** (ROTA LIVRE/Larissa = 99%) | Disposição-a-pagar **não validada** além de 1 |
| Carteira legacy WR (Firebird) | **50 negócios reais** | Funil morno PARADO — Vargas 3.981 vendas, Extreme 85k, Gold 55k, Zoom 52k, Mhundo 18k, Martinho 46k (migrado) |
| Importador Firebird→MySQL | ✅ validado (Martinho: 44k vendas, 83k títulos) | **Arma de migração pronta e não-replicável** |
| Diferencial Jana (IA+memória) | Único no nicho BR | Fosso defensável — Zênite/Mubisys/Calcgraf/Bling/Omie **não têm** |
| Deal Agrosys | R$2,65M ano-1 potencial | Baleia, mas risco de execução/legal/adoção |
| Foco de esforço (7d) | 104/104 commits fora de receita | **Energia no lugar errado pro estágio** |

**Conclusão:** o gargalo não é engenharia — é **tempo de venda** + **um preço pra vender** + **um motivo pra trocar**. Os três têm solução barata e rápida.

---

## 🪜 As 4 alavancas, priorizadas (impacto × esforço × certeza)

| # | Alavanca | Impacto | Esforço | Certeza | Quando |
|---|---|---|---|---|---|
| **1** | **Monetizar a carteira legacy (50 → pagantes)** | 🟢 Alto | 🟢 Baixo (importador pronto) | 🟢 Alta (warm 5× cold) | **Agora — 90 dias** |
| **2** | **Fixar pricing + posicionamento público** | 🟢 Alto (destrava tudo) | 🟢 Baixíssimo | 🟢 Alta | **Esta semana** |
| **3** | **Jana como receita + retenção (killer demo + cobrança)** | 🟢 Alto | 🟡 Médio | 🟡 Média | **30-60 dias** |
| **4** | **Agrosys + linha "WhatsApp+NFe pra ISVs"** | 🔵 Altíssimo (baleia) | 🔴 Alto + risco legal | 🔴 Baixa (depende de 3os) | **De-risk já, dev só pós-contrato** |

> Regra de gerente: **1 e 2 financiam a sobrevivência e provam o modelo AGORA. 4 é o que te leva a R$5M — mas não troque receita certa-e-pequena por receita incerta-e-grande antes de ter caixa.**

---

## 💰 Alavanca 2 — PRICING (faça esta semana, destrava todo o resto)

### O erro atual
Pricing hipotético de hoje = **12 SKUs** (Financeiro + NFe + Cobrança + IA, cada um ×3 tiers). Isso te faz competir **feature-a-feature contra Granatum** (R$269 flat, "ilimitado barato") — uma briga que você perde. **Pare de vender módulo. Venda a suíte vertical inteira por UM preço.**

### O mercado (referência)
- **Horizontais BR:** Bling R$55–650, Omie R$200–1.500, ContaAzul R$160–720, Granatum R$269 flat. **ContaAzul já dá IA-utilitária de graça** → IA-de-tarefa virou table-stakes.
- **Verticais gráfica BR:** **Mubisys R$1.800/mês + R$1.800 adesão**, Zênite/Calcgraf 100% quote-based (preço escondido), **suporte ruim** (Reclame Aqui), **NENHUM tem IA**.
- **Print MIS global:** base + por usuário, **nenhum monetiza IA**.

### Packaging recomendado (good-better-best — mire 50% no tier do meio)

| Tier | Preço/mês | Pra quem | Inclui | Jana |
|---|---|---|---|---|
| **Essencial** | **R$ 249** | Saindo de planilha/Bling | 1 vertical, Financeiro, NFe ilimitada, 100 cobranças/mês, WhatsApp 1 número, 3 users | Co-piloto leve (200 interações/mês) |
| **Profissional** ⭐ | **R$ 599** | Operação rodando, 5-10 pessoas | Tudo + 12 users, 500 cobranças, omnichannel 3 números, multi-loja, BI básico | **Jana com memória persistente + proativa** (2.000/mês) |
| **Escala** | **R$ 1.290** (+R$59/user >25) | Multi-unidade, volume | Users até 25, 2.000 cobranças, API, white-glove | **Jana + agentes** (10k/mês) |

**Frase de venda do tier-âncora:** *"R$599/mês, a suíte completa da sua gráfica + IA que conhece seu negócio — **3× mais barato que o Mubisys, e eles não têm IA.**"*

**Preço público é arma:** num nicho 100% quote-based e opaco, publicar **um número simples e defensável** acelera o ciclo de venda e sinaliza confiança. Faça uma landing com o preço e o comparativo "vs Mubisys".

### Oferta de migração (clientes legacy / vindos de Zênite/Mubisys)
A barreira é **inércia, não preço** (B2B paga 20% de prêmio pra NÃO trocar). Então o herói **não é desconto** — é **atrito-zero**:
- ✅ **"Migração Branca": R$0 de implantação, eu trago seus 26 anos de histórico** (importador pronto).
- ✅ **2 meses grátis no anual** (enquadrar como "2 meses grátis", nunca "17% off").
- ✅ **Roda os dois em paralelo por 60 dias** + **dinheiro de volta** — risco zero.
- ✅ **Preço de fundador travado 24 meses** (sem reajuste — "saia do reajuste deles", concorrentes subiram +11% em 2025).
- ⚠️ **Condição anti-dois-sistemas:** migração grátis SÓ com contrato anual + compromisso de desligar o desktop em 60-90 dias (modelo Autodesk trade-in).

### Anti-canibalização (regra de ouro)
Ancore a **mensalidade anual à vista ≥ o que o cliente já gastava/ano** em licença+suporte+atualização do WR. Você troca "vendia upgrade a cada 2-3 anos" por "recebo todo ano, pra sempre, e faço upsell de Jana". **Isso é aumento de LTV, não canibalização** (Adobe cobrou MENOS/ano e multiplicou o LTV). Anual também resolve seu **fluxo de caixa**.

---

## 🤖 Alavanca 3 — JANA como receita + retenção

### O modelo: HÍBRIDO 3 camadas (não add-on por usuário — per-seat está morrendo)
- **Camada 1 — Jana no core (lock-in, não receita):** conversacional básica embutida em todo plano. Quanto mais usa, mais sabe → **memória = switching cost**. Protege churn.
- **Camada 2 — Jana Pro (recorrente):** alertas proativos + fechamento mensal narrado + metas. Escalona por tier (o premium que retém — planos <R$250 têm NRR 32% vs 85% acima). Posicione como *"o analista que você não contrata"* (comparação é com humano de R$3k, não com software).
- **Camada 3 — Agentes que EXECUTAM (por resultado, margem 90%):** a Jana **cobra inadimplente**, responde WhatsApp, emite NFe.
  - Cobrança: **R$0,80–1,50 por cobrança resolvida** OU **1-2% do recuperado** (cliente recupera "dezenas de milhares" — queda de até 60% em atraso; 1-2% é trivial pra ele, 90% de margem pra você).
  - Atendimento: **R$1-2 por conversa resolvida** (benchmark Intercom $0,99 / Zendesk $1,50).

### Proteção de margem (você JÁ tem a infra)
O **roteamento Brain A/Brain B não é detalhe técnico — é a vantagem de margem.** 80% das interações no Brain A barato; só o complexo no Brain B. É literalmente a defesa nº1 da literatura (AI-first sem isso roda 25% de margem; com isso, 70-80%). + cache agressivo (Anthropic prompt caching) + fair-use cap na Camada 2.

### A killer demo (o roteiro que fecha venda)
> Na call, no **celular do dono**, você digita pra Jana no WhatsApp: **"Jana, como foi meu mês?"**
> Em 3s, com os dados REAIS dele: *"Maio fechou R$87.400 — 12% acima de maio do ano passado. Mas 3 clientes têm R$14.200 vencidos há +15 dias. E a NFe da Papelaria W vence amanhã. Quer que eu cobre os 3 agora?"*
> Você: **"Manda."** → Jana dispara as 3 cobranças ao vivo.

**Por que mata:** em 30s o dono vê que (a) a IA conhece o negócio DELE (não é ChatGPT genérico), (b) ela **age**, (c) o ROI é óbvio (R$14.200 que ele ia esquecer). **Nenhum concorrente BR reproduz isso.**

**A regra-mãe de IA-como-receita:** *não venda "IA", venda o RESULTADO, e cobre na moeda do problema (a mão-de-obra que ele não contrata), não na moeda do token.*

---

## 🚀 Alavanca 1 — PLAYBOOK 90 DIAS: monetizar a carteira legacy

**Capacidade realista (1 pessoa + IA):** bloco fixo de **90 min/dia × 5 = ~7,5h/semana** de venda. Eliana cobre proposta/contrato/cobrança. IA faz o trabalho pesado (snapshot Firebird, rascunho de mensagem, rodar importador). Dá pra tocar **5-8 clientes/semana com profundidade**.

### Fase 0 — Munição (dias 1-7)
- Segmentar os 50 em 3 ondas (score: uso atual do desktop + saúde financeira + dor aguda + proximidade + valor de prova social). → Rastreador em [`_pipeline-migracao-legacy.md`](../clientes/_pipeline-migracao-legacy.md).
- Rodar **snapshot financeiro Firebird** dos 15 da Onda A (receita 12m, inadimplência) → vira o gancho personalizado.
- Congelar a OFERTA + os 3 scripts + 1 contrato anual padrão.
- Preparar o "kit demo": login do cliente **já com os dados dele migrados**.

### Fase 1 — Onda A (15 mais quentes/saudáveis · semanas 1-4) → meta 5-7 fechados
Cadência por cliente (3-5 toques / 10-14 dias): WhatsApp pessoal + "rodei seus números" → call diagnóstico 30 min (Mom Test, a dor, não pitch) → **migração-demo com a conta dele populada** → proposta anual + garantia paralelo → fechar.

### Fase 2 — Onda B (20 do meio + dormentes · semanas 5-8) → meta acumulada 12-15
Sequência win-back 3 mensagens (WhatsApp primário + email backup). **Prova social fresca do MESMO nicho** ("a [gráfica da Onda A] já migrou, a Jana cobra sozinha"). Pedir 1 indicação a cada migrado.

### Fase 3 — Onda C (15 frios · semanas 9-12) + consolidação
Janela final ("vou descontinuar suporte ao desktop antigo até [data]"). Converter mensais→anual. Documentar o runbook de migração (vira processo, não heroísmo).

### Metas realistas
| Onda | Tocados | Fechamento | Fechados |
|---|---|---|---|
| A | 15 | 45% | ~7 |
| B | 20 | 30% | ~6 |
| C | 15 | 20% | ~3 |
| **90 dias** | **50** | **~32%** | **12-18** |

- **MRR 90 dias:** 12-18 × ticket R$300-450 = **~R$5-7k MRR** (maioria anual à vista → bom pro caixa).
- **12 meses:** 30-40 dos 50 migrados → **R$13-18k MRR ≈ R$160-216k ARR** da carteira morna, **CAC quase zero**.
- **Upside:** cada migrado vira candidato a upsell Jana (Camada 3) no mês 2-3 → ARPU sobe, NRR 106%→120%+.

---

## 🐋 Alavanca 4 — Agrosys + linha "WhatsApp+NFe pra ISVs" (de-risk JÁ, dev só pós-contrato)

### O que a pesquisa confirmou
- **Pricing WhatsApp do plano está CORRETO** (utility $0,0068, marketing $0,0625, service grátis, Tech Provider zero-fee). Margem **sobre custo Meta ~98% é real.**
- **MAS a margem operacional realista é 60-80%** (não 98%) depois de infra multi-tenant + suporte a 4000 produtores + comissão + churn. E **R$200k MRR assume 100% de adoção** de uma base que **não é sua, é da Agrosys** → modele com **30-50%**.

### As 3 correções críticas (antes de codar 1 linha)
1. **DECISÃO #1 — billing Cenário A vs B:**
   - **Cenário A (cliente paga Meta direto):** seu custo Meta ≈ R$0, **elimina ~todo o fiscal de importação**, LGPD vira Operador puro. Você cobra R$50 pelo **software/orquestração**, não revende mensagem. ✅ **Recomendado.**
   - Cenário B (você paga e repassa): vira Merchant of Record, fiscal de importação **40-53%** (não 20% — entidade BR é **Meta Inc. EUA**, não Irlanda), NF de saída, mais dor.
2. **Comissão do vendedor (Artur):** **NUNCA % recorrente vitalícia sobre MRR** (vira passivo perpétuo que sobrevive ao churn). Padrão sadio: **one-time sobre 12 meses de ACV (10-20%) com clawback** se churn precoce.
3. **Arquitetura anti-ban:** **1 produtor = 1 Business Portfolio = 1 WABA = 1 número** (isolamento total — ban é por número, não derruba a rede). A armadilha real (nova, out/2025): **concentrar números no MESMO portfólio** = limite vira pool compartilhado e 1 spammer derruba todos. Precisa de orquestração multi-tenant real (queue por tenant, throttle, observabilidade de quality).

### A oportunidade MAIOR (a tese de plataforma)
A mesma plataforma multi-tenant que serve a Agrosys serve **qualquer ERP/ISV com base instalada**. Custo marginal do próximo = um conector de webhook. **Agrosys é o 1º de N canais cativos.** ⚠️ Não é greenfield — **TecnoSpeed/PlugNotas já faz "NFe via WhatsApp" white-label**; seu diferencial é vender **direto pra base de UMA ERP**, não no varejo. **A linha de produto vale mais que o deal único.**

### Checklist pré-código (resumo — completo na pesquisa)
☐ Decidir Cenário A · ☐ Reestruturar comissão Artur (one-time+clawback) · ☐ DPA/contrato Operador LGPD com Agrosys · ☐ Validar adoção real (modelar 30-50%) · ☐ Atrelar R$500k upfront a marcos com SLA · ☐ Business Verification + App Review Meta · ☐ Template NFe cirúrgico (Utility, sem floreio) · ☐ Parecer de contador (Eliana).

---

## 🧭 Meta-recomendação: de construtor a vendedor

O insight que atravessa as 4 pesquisas: **seu maior risco não é técnico, é alocação do seu tempo.** Você tem o produto, o fosso (Jana) e a carteira morna. O que falta é **90 min/dia de venda founder-led** + **um preço pra vender** + **a oferta de migração branca**.

- **Esta semana:** fixar pricing (R$249/599/1290) + landing com preço público + comparativo vs Mubisys.
- **Segunda-feira:** rodar o importador nos **5 clientes mais quentes** e fazer a 1ª call **com a conta deles já na tela**. (É a UMA coisa de maior alavancagem que existe — só você no mundo consegue.)
- **Paralelo (Eliana):** de-risk Agrosys (Cenário A + comissão + DPA) — sem dev até contrato.
- **Não faça:** mais refactor/design-system até o pipeline de venda estar rodando.

> **A matemática do R$5M:** a carteira morna te dá ~R$200k ARR (sobrevivência + prova + caixa). Os deals ISV tipo Agrosys são o que te leva a R$5M. **Sequência:** caixa da carteira AGORA → prova o modelo → de-risca e fecha o 1º ISV → replica a linha de plataforma.

---

## 📚 Fontes (53 buscas/fetches)
**Pricing:** Bling/ContaAzul/Omie/Granatum planos · Mubisys proposta (Scribd) · Zênite/Calcgraf · Printavo/YoPrint/ShopVOX · Monetizely (IA pricing + switching costs) · SaaStr · Maxio.
**Carteira legacy:** Adobe (chartmogul) · Autodesk trade-in · Sage · userhelp (done-for-you) · saasmag/userpilot (onboarding) · gradient.works (benchmarks SMB) · campaignhq (win-back).
**Jana/IA:** Menlo Ventures 2025 · SoftwareSeni (margem) · Sebrae/docmanagement · Service Direct · MS Copilot · Intuit Assist · Salesforce Agentforce · HubSpot Breeze · Intercom Fin · Neofin (cobrança IA).
**WhatsApp/Agrosys:** developers.facebook (pricing updates) · gallabox (out/2025 portfolio limits) · twilio Tech Provider · Meta Terms (entidade BR=Inc.) · pwc (withholding) · PlugNotas/TecnoSpeed · Aliare/Agrosys.

*(URLs completas nos 4 briefs de pesquisa desta sessão.)*
