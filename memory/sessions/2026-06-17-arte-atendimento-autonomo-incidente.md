---
date: "2026-06-17"
topic: "Estado da arte — atendimento autônomo + processamento de incidente (2 frentes) ancorado no caso Guilherme"
authors: [C, W]
tipo: estado-da-arte
relacionado:
  - memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md
  - memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md
  - memory/sessions/2026-05-29-arte-reconcile-loop-kb-self-healing.md
  - memory/sessions/2026-05-15-arte-atendimento-omnichannel-memoria-cliente.md
adrs_tocadas: []
implementado_nesta_sessao:
  - "Frente 1 (sensor): check sells_value_sanity em jana:health-check + invariante pura valueExceedsCeiling + Pest contrato"
---

# Estado da arte — atendimento autônomo + processamento de incidente

## Origem (sinal qualificado)

Caso **Guilherme** (incidente `num_uf` 2026-06-05, ROTA LIVRE biz=4): cliente reportou
no WhatsApp *"vendas com valor errado"* — **8 dias depois** do bug entrar, apesar de a
lógica de detecção já existir no `SellsFinalTotalAuditCommand` (Heurística A) sem ninguém
rodá-la. Wagner pediu duas frentes + *"qual modelo de negócio estou procurando, quem se
destaca e como?"*.

## As duas frentes são duas CATEGORIAS de mercado distintas

| | **Frente 1 — o sensor** | **Frente 2 — a audaciosa** |
|---|---|---|
| Categoria | Agentic SRE / AIOps self-healing | Autonomous AI Customer Support Agent |
| O que faz | Detecta anomalia → hipótese causa-raiz → remedia → verifica | Cliente reporta → agente enriquece ticket → executa resolução (ou escala) |
| Se destacam | **Resolve AI** (80% resolução autônoma, criadores do OpenTelemetry), **Cleric** (parallel hypothesis testing + confidence + aprendizado contínuo) | **Sierra** (Bret Taylor; per-resolution pricing), **Decagon** (70% deflection; executa reembolso/alteração sozinho), **Intercom Fin** (helpdesk+IA), **Lindy** (SMB) |
| No caso Guilherme | Pega o `final_total` violando a invariante nas 16 vendas | Lê o WhatsApp, descobre QUAIS vendas, anexa causa-raiz, abre chamado/MCP task |

## Como os melhores fazem (padrão que se repete em todos)

1. **Não respondem — agem** via tools/APIs (chatbot → agente é tomar ação no sistema).
2. **Confidence + guardrails + HITL** — motor de confiança decide auto-resolver vs escalar.
3. **Aprendizado contínuo por incidente** — captura conhecimento institucional.
4. **Enriquecimento contextual antes de agir** — chama APIs, correlaciona, e **empurra bug
   repetitivo pro backlog (Jira/MCP) automaticamente**.
5. **Preço por resultado (outcome-based)** — resolução de IA ~US$0,62 vs ~US$7,40 humano.

## Modelo de negócio procurado: **Vertical AI Agent embutido no produto**

Tese 2026 literal: *"vertical AI agents are eating SaaS"*. Vencedores = **domínio profundo
+ integração profunda no sistema + preço alinhado a resultado**, internalizando o loop
*"preencher → analisar → decidir → agir"* (humano só define metas e revê exceções).

**Vantagem estrutural do oimpresso que Sierra/Decagon NÃO têm:** eles são camada
**horizontal** parafusada no produto de terceiros — não enxergam código nem invariantes de
dado. O oimpresso é dono do **ERP + canal (WhatsApp) + código + domínio determinístico
(valor/estoque/NFe)**.

> Sierra sabe que o cliente disse "valor errado". Não tem como saber que `final_total`
> violou uma invariante em 16 vendas a partir do deploy de 27/05. O oimpresso pode saber as
> duas coisas — e é isso que fecha o loop que o mercado horizontal não monta.

As duas frentes são **uma só**: Frente 2 é a porta da frente (lê o WhatsApp), Frente 1 é a
sala de máquinas (sabe qual invariante quebrou e onde).

## A espinha já existe no oimpresso

- **ADS Dual-Brain** (`ads-decision-flow`): `Risk → Confidence → Policy → Router → Brain A/B
  → HITL` = exatamente a arquitetura confidence+HITL+ação dos líderes.
- **Jana** (IA + memória) — camada conversacional.
- **`clients_feedbacks` + `CaptureFeedbackSheet`** — estrutura de ticket já existe;
  enriquecimento hoje é manual. O "audacioso" é a Jana preencher + **correlacionar com o
  sensor da Frente 1** pra anexar causa-raiz + vendas afetadas.

## Sequência recomendada

1. **Frente 1 primeiro** (sensor determinístico) — barato, alto ROI, e é o *grounding* da
   Frente 2 (sem sensor, a Jana enriquece com palpite; com sensor, com fato).
2. **Frente 2 sobre o ADS** — WhatsApp chega → ADS roteia: confidence alto + risco baixo
   (divergência de valor já diagnosticada) → auto-enriquece ticket + propõe fix; risco alto
   (mexe em valor/estoque) → **HITL obrigatório** (Regra Mestre Tier 0 já exige).

## Frente 1 — implementado nesta sessão (detecção-only)

- `jana:health-check` ganhou o check **`sells_value_sanity`**: invariante dura
  `final_total ≤ (total_before_tax + tax + shipping)` (margem 1.5). Roda 06:00 BRT; venda
  corrompida se auto-identifica em ≤24h, ANTES do cliente reportar.
- Invariante extraída em predicado puro `HealthCheckCommand::valueExceedsCeiling()` (const
  `VALUE_SANITY_MARGIN` compartilhada com o SQL — não divergem). Pest ancorado no contrato
  com os números reais do incidente.
- **Correção permanece human-gated** (Regra Mestre Tier 0) via `sells:final-total-audit` —
  o sensor só alerta, não muta valor.

## Gaps rankeados (impacto × esforço) — backlog Frente 2

| # | Gap | Impacto | Esforço | Nota |
|---|---|---|---|---|
| 1 | Sensor → notificação ativa (não só log ALERT) | alto | baixo | plugar `--notify` num canal Wagner vê (WhatsApp interno/email) |
| 2 | Captura de feedback em mídia sem texto (botão cego p/ vídeo) | alto | baixo | `ConversationThread.tsx:1374` gate `body>0` |
| 3 | Jana auto-enriquece ticket (persona+módulo+causa-raiz via sensor) | alto | médio | sobre ADS confidence+HITL |
| 4 | Correlação ticket↔incidente (linkar feedback à venda/deploy afetado) | alto | médio | o "loop" que ninguém horizontal monta |
| 5 | Preview de mídia na ficha de feedback + tratar cap 16MB | médio | baixo | — |
| 6 | Outcome metric (deflection / bugs pegos antes do cliente) | médio | médio | métrica de negócio do vertical agent |

## Sources

- Decagon vs Sierra (eesel) — https://www.eesel.ai/blog/decagon-vs-sierra
- Sierra vs Decagon vs Lindy — https://tooldirectory.ai/blog/sierra-vs-decagon-vs-lindy-best-ai-customer-support-agents-2026
- AI SRE tools 2026 (Metoro) — https://metoro.io/blog/top-ai-sre-tools
- Agentic SRE 2026 (Unite.AI) — https://www.unite.ai/agentic-sre-how-self-healing-infrastructure-is-redefining-enterprise-aiops-in-2026/
- AI ticket triage → Jira (Fini) — https://www.usefini.com/guides/ai-ticket-triage-jira-backlog-automation
- Vertical AI Agents 2026 (ACTGSYS) — https://actgsys.com/en/blog/vertical-ai-agents-industry-specific-2026
- Pricing 2026 (Monetizely) — https://www.getmonetizely.com/blogs/the-2026-guide-to-saas-ai-and-agentic-pricing-models
