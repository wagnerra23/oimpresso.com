# oimpresso — Deck Empresa Multipropósito

> **Versão:** draft 2026-05-09 — material multipropósito (prospect enterprise / canal-parceiro / investidor early-stage futuro).
> **Tom:** confiante mas honesto. Sem hype. Wagner-style.
> **Restrição:** todo número não-checado vem marcado `[validar — placeholder]`. Não inventar.
>
> **Como usar este deck:** cada slide foi escrito pra ser lido em 30-60s. Bullets enxutos, frase forte por slide. As ordens recomendadas pra cada audiência estão no rodapé.

---

## Slide 1 — Capa

**[LOGO oimpresso — placeholder]**

**oimpresso**
ERP vertical pra comunicação visual no Brasil.

São Paulo, SP — 2026-05-09
Wagner Rocha · wagnerra@gmail.com · oimpresso.com

> *NOTA pra Wagner: definir cidade/data conforme apresentação real. Logo placeholder até identidade visual fechada.*

---

## Slide 2 — O problema

Três dores que aparecem em **todas** as gráficas brasileiras de 1-30 funcionários:

1. **Update em lote é manual e tedioso.** Mudou o preço da lona 440g? Abre 80 produtos, um por um. (PrintPlanet, fórum profissional)
2. **Sem margem por OS.** O dono fecha o mês sem saber qual pedido lucrou e qual furou. ("trabalhando 14 horas por dia e ainda se perguntando onde foi parar o lucro" — FespaBrasil)
3. **WhatsApp como ERP de fato.** Orçamento vai por WhatsApp, foto da arte vai por WhatsApp, cobrança vai por WhatsApp — e nada fica rastreável.

> **95% das gráficas brasileiras operam sem OS rastreável.** `[validar — placeholder, atribuir fonte ou substituir por "a maioria das"]`

---

## Slide 3 — Por que agora

Três janelas se abriram em 2026:

- **NFC-e obrigatória pra varejo em SC** e expansão pra outros estados — gráficas que vendem balcão precisam emitir, não dá mais pra "deixar pra depois".
- **IA acessível.** O custo de um agente conversacional caiu ~10x em 18 meses (gpt-4o-mini classe). Ficou viável colocar IA dentro de um ERP de R$ 600/mês.
- **Mobile-first virou default.** Dono de gráfica não senta em frente ao PC pra olhar faturamento — abre celular às 22h. Concorrentes ainda têm app limitado ou inexistente.

A janela é agora porque o ERP vertical legado (40+ anos) não consegue migrar essa stack na velocidade certa.

---

## Slide 4 — Quem somos

- **Wagner Rocha** — líder, dono, decisão final de produto e arquitetura. ~20 anos em sistemas pra gráfica (origem WR2 / PontoWr2).
- **Time de 5 pessoas** — Maiara (suporte+dev), Felipe (dev+suporte), Luiz (dev IA-pair), Eliana (financeiro+dev IA).
- **Base SP** `[validar — placeholder, oimpresso opera distribuído; HQ comercial a definir]`.
- **Cliente piloto:** [Cliente piloto SP] — ~99% do volume de vendas atual `[validar números — placeholder]`. Validado em produção há ~2 anos `[validar — placeholder]`.

> *NOTA pra Wagner: confirmar HQ comercial (SP? SC?) e tempo exato de validação produção do cliente piloto. "~2 anos" baseia-se no histórico do projeto mas precisa fonte fixa.*

---

## Slide 5 — O produto

**Em uma frase:** ERP cloud nativo pra gráfica/comunicação visual, com IA conversacional que sabe os clientes, os preços e o fluxo da casa.

**[SCREENSHOT placeholder — cena: tela "Produção Oficina" do módulo Repair, drag-and-drop entre 4 colunas — Recebido, Em produção, Pronto pra entrega, Entregue. Card com nome do cliente, tipo de serviço (banner / adesivo / placa) e SLA visível. Chat flutuante da Jana no canto inferior direito.]**

Stack canônica: Laravel 13.6 + Inertia v3 + React 19 + Tailwind 4. Mobile-first. Multi-tenant desde o dia 1.

---

## Slide 6 — Diferenciais únicos (4)

1. **NFe automática a partir de boleto pago.** Cliente paga no Asaas/Inter/C6 → nota fiscal emitida sem clique humano. Entregue em produção (US-RB-044). Nenhum dos concorrentes verticais publica esse fluxo.
2. **Jana — IA com memória persistente.** Pergunta no chat "quanto faturei essa semana?" e a resposta vem em 3 segundos, com a query SQL auditável. Recall híbrido (Meilisearch + reranker).
3. **Stack moderna mobile-first.** React 19 + Inertia v3 nativo cloud. Concorrentes ainda migram desktop→web ou não têm app mobile maduro.
4. **Governança formal pública.** Constituição v2 (ADR 0094), 95+ ADRs canônicas append-only `[validar números — placeholder, hoje ~95]`. 36% das ERP-enterprises não têm processo formal de decisão equivalente `[validar — placeholder ou remover número]`.

---

## Slide 7 — Mapa competitivo

```
                          SaaS / IA / mobile-first
                                  │
                             [oimpresso ★]
                                  │ ◀── (sozinho aqui)
                                  │
                          [Calcme]   [Mubisys]
                  [Bling]            │
   [Tiny] ──────────────────────────────── ESPECIALIZAÇÃO VERTICAL
   [Conta Azul]                      │
                          [Alfa Networks]
                                     │
                          [Calcgraf]    [Visua]
                                     │
                          Desktop / legacy / on-premise
```

**Quadrantes:**
- **Genérico SaaS:** Bling, Tiny, Conta Azul — não calculam m², não têm OS, não têm vertical.
- **Vertical legacy:** Calcgraf (40 anos), Visua (Win7+) — vertical legítimo, stack envelhecida.
- **Vertical SaaS:** Alfa Networks, Calcme, Mubisys — concorrência direta. Calcme e Mubisys têm reclamações públicas documentadas (Reclame Aqui) sobre integração e atendimento pós-trial.
- **oimpresso:** sozinho no quadrante "vertical + IA com memória + mobile-first + governança formal".

---

## Slide 8 — Tração

- **[Cliente piloto SP]:** ~99% do volume de vendas validado em produção `[validar números — placeholder]`. Mais de 2 anos rodando `[validar — placeholder]`.
- **NFC-e SEFAZ ponta-a-ponta entregue:** pipeline Listener → Job → NfeService → SEFAZ → evento → e-mail DANFE. 11 templates fiscais (10 UF + 1 MEI) `[validar números — placeholder]`.
- **364+ PRs governados** `[validar — placeholder, baseado em commit recente]`. Cada mudança passa por ADR ou skill formal.
- **95+ ADRs canônicas append-only** `[validar números — placeholder]`. Conhecimento auditável, não opinativo.

> *NOTA pra Wagner: este slide é o ponto mais sensível pra prospect enterprise — eles vão validar os números. Confirmar antes de mostrar pra fora. Se algum número não estiver pronto, melhor remover do que arriscar.*

---

## Slide 9 — Modelo de negócio

Três tiers + add-on Jana + setup fee anti-tire-kicker.

| | **Starter** | **Pro** | **Enterprise** |
|---|---|---|---|
| Mensal `[draft]` | R$ 299 | R$ 599 | R$ 1.499 |
| Setup fee `[draft]` | R$ 0 | R$ 2.500 | R$ 5.000 |
| Compromisso | mensal | 12 meses | 24 meses |
| Usuários | até 3 | até 10 | ilimitado |
| Jana IA | add-on R$ 199/mês | inclusa (500 perguntas) | ilimitada |
| NFe auto-emissão | ❌ | ✅ | ✅ + contingência SVC |

Add-ons: +business adicional (R$ 199), customização de tela (R$ 1.500 one-shot), migração assistida.

> *NOTA pra Wagner: pricing está em `[draft]` no `06-pricing-tiers.md`. Validar antes de uso comercial. Setup fee é guard-rail (cliente que não paga setup raramente vinga).*

---

## Slide 10 — GTM

**Wedge primário:** dono de gráfica/comunicação visual de 1-10 funcionários, faturamento R$ 30-200k/mês `[validar — placeholder]`, hoje rodando **Bling+planilha** OU **Calcme/Mubisys frustrado pós-trial**.

**Por quê:**
- Bling/Tiny não calculam m² nem têm OS. Dor diária de 30min/pedido.
- Calcme/Mubisys têm a vertical, mas reviews públicas mostram fragilidade pós-contrato (4 reclamações Calcme com padrão "trial promete mais que entrega").

**Geografia:** SP → RS → PR (gráfica rápida densa nessas regiões).

**Canais:**
- Cold email + LinkedIn (donos de gráfica nominais).
- Canal/parceiro: **Singrafs** (associação setorial), **AFACOM+** (programa de implantação regional usado por concorrente), **ABTG**.
- Indicação cliente atual (-1 mês grátis pro indicador e indicado).

---

## Slide 11 — Tamanho de mercado (TAM/SAM/SOM)

> *NOTA pra Wagner: este slide depende inteiramente de números externos. NÃO usar pra investidor sem validar com Singrafs ou Sebrae primeiro.*

- **TAM (Total Addressable Market) — Brasil:** ~`[validar — placeholder, número de gráficas/comunicação visual ativas no Brasil. Fonte sugerida: Singrafs ou IBGE/RAIS]` empresas.
- **SAM (Serviceable Addressable Market):** gráficas com 1-30 funcionários (ICP), faturamento R$ 30-500k/mês — `[validar — placeholder]` empresas.
- **SOM (Serviceable Obtainable Market) 24m:** ICP refinado para 1-10 funcionários, em SP/RS/PR, com sistema atual frustrante — `[validar — placeholder]` empresas.
- **Ticket médio Pro:** R$ 599/mês × 12 = R$ 7.188/ano `[draft pricing]`. Multiplicar pelo SOM realista 24m pra chegar no run-rate alvo.
- **Meta R$ 5M/ano** (ADR 0022) — exige `[validar — placeholder, ~700 clientes Pro pagantes]` em 24-36m.

---

## Slide 12 — Roadmap 12 meses

**Q3/2026** — 2º e 3º cliente pagante fora do piloto. Validação de unit economics fora de uma única conta.

**Q4/2026** — App mobile MVP (PWA via Inertia, escopo: dashboard + notificação produção + chat Jana).

**Q1/2027** — DAM nativo (gestão de arquivos do cliente — print-ready, plotagens). Hoje é o gap concreto vs Mubisys (MubiDrive 150+ TB).

**Q2/2027** — Endorsement setorial (Singrafs/AFACOM+/ABTG) — co-marketing + canal regional.

> Nada acima é commit irrevogável. Roadmap segue regra dura: **não prometer feature sem ADR aceita.** Roadmap aqui é direção, não contrato.

---

## Slide 13 — Time + cultura

**5 pessoas, todas mão na massa:**
- **Wagner** — líder, dono, aprovação final.
- **Maiara** — suporte+dev.
- **Felipe** — dev+suporte.
- **Luiz** — dev IA-pair (iniciante pareado com IA).
- **Eliana** — financeiro+dev IA.

**Princípios duros (Constituição v2):**
1. **Cliente como sinal qualificado** — backlog só recebe item se cliente paga + reporta OU métrica detecta drift (ADR 0105). Não construímos hipótese sem sinal.
2. **IA-pair velocity ~10x** em tarefas codáveis (ADR 0106). Time pequeno entrega como time grande.
3. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — `business_id` global scope obrigatório. Vazar dado entre clientes é o pior bug possível e não acontece.
4. **Charter > Spec** — contrato vivo ao lado do código, não num Confluence morto.

---

## Slide 14 — Riscos + mitigações

| Risco | Mitigação |
|---|---|
| **Concentração [Cliente piloto SP] ~99% do volume** | Plano comercial deste deck (cold email + canal + LinkedIn) é exatamente a saída. Meta Q3/2026: 2º+3º cliente pagante. |
| **Gap mobile / DAM** | Roadmap Q4/2026 (mobile PWA) e Q1/2027 (DAM) priorizado. Concorrentes têm parcial — não temos paridade ainda. |
| **Hostinger ≠ CT 100 (separação runtime)** | Decisão arquitetural formal (ADR 0062). Hostinger só hospeda app web; daemons (Centrifugo, Meilisearch, MCP) vivem no CT 100 Proxmox. Sem drift. |
| **Endorsement setorial ainda fraco** | Singrafs/AFACOM+/ABTG no roadmap Q2/2027. Hoje vendemos por mérito do produto, não por selo de associação. |
| **Pricing não validado em mercado** | Tiers em `[draft]`. Primeiros 2 contratos fora do piloto vão calibrar. |

---

## Slide 15 — Próximos passos

**Pra prospect enterprise:**
- Trial guiado de 30 dias, sem cartão de crédito, com ROTA LIVRE (cliente piloto) como referência clicável `[se Wagner autorizar mention]`.
- Setup migração de dados incluso no Pro/Enterprise.
- Demo honesta: liga pro cliente piloto, pergunta direto. Sem trial-gating.

**Pra canal/parceiro (Singrafs, AFACOM+, ABTG):**
- Revenue-share por cliente fechado (modelo a calibrar — `[validar — placeholder, sugestão: 20% recorrente primeiros 12 meses]`).
- Co-marketing: case study conjunto, presença em feira (ExpoPrint, Fespa Brasil).
- Treinamento de canal incluso pra credenciar consultor regional.

**Pra investidor early-stage (futuro):**
- Conversa de 30 min sobre PMF.
- Métricas reais (não pitch): churn, NRR, payback CAC, gross margin.
- Não estamos levantando agora — quando levantarmos, é pra acelerar GTM, não pra construir produto. O produto está em produção.

**Contato:** Wagner Rocha · wagnerra@gmail.com · oimpresso.com

---

## Como ordenar os slides por audiência

> *NOTA pra Wagner: rodapé pra ti, não pra apresentar. Mantém a ordem dos 15 acima como default e remixe conforme o caso.*

- **Prospect enterprise** (gráfica 30+ func): 1 → 2 → 5 → 6 → 7 → 8 → 9 → 14 → 15. Pular 10/11/12/13 (não interessa pra eles).
- **Canal/parceiro** (Singrafs/AFACOM+/ABTG): 1 → 4 → 6 → 7 → 10 → 13 → 15. Foco em time + GTM + revenue share.
- **Investidor early-stage** (futuro): 1 → 2 → 3 → 6 → 7 → 8 → 11 → 12 → 13 → 14 → 15. Ordem completa, ênfase em mercado/roadmap/risco.

---

**Refs internas:**
- `memory/research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md`
- `memory/research/2026-05-prospeccao/03-concorrentes-alfa-visua-calcgraf-reviews.md`
- `memory/sales/2026-05/06-pricing-tiers.md`
- ADR 0022 (meta R$ 5M/ano), ADR 0093 (multi-tenant Tier 0), ADR 0094 (Constituição v2), ADR 0105 (cliente como sinal), ADR 0106 (recalibração 10x IA-pair).
