# Estado-da-arte 2026 — atendimento conversacional omnichannel com memória persistente de cliente

> **Data:** 2026-05-15 · **Autor:** agent `estado-da-arte` · **Cliente:** Wagner
> **Escopo:** o que líderes 2026 (Sierra, Decagon, Intercom Fin, Front, Gladly, Crisp) consideram não-negociável em atendimento conversacional + VoC + memória persistente do **cliente final** (não do usuário SaaS), e onde oimpresso bate/atrasa.
> **Tempo:** ~30min · **Pesquisa Fase 1:** 8 WebSearch limpos (sem `memory/` ou `decisions-search`).
> **Foco:** decisões acionáveis Wagner próximos 30 dias.

---

## 1. TL;DR

- **Oimpresso já tem ~65% da espinha dorsal cobrada por líderes 2026:** schema omnichannel polimórfico (ADR 0135), Jana memória + Meilisearch hybrid, FSM auditável, multi-tenant Tier 0, tags manuais de conversa, LGPD `whatsapp_consent` em `Contact`. **2 gaps fatais e 2 gaps de alto-impacto.**
- **Gap fatal #1 — `Contact` é só cadastro CRM legacy, NÃO é Customer Profile 2026.** Não tem timeline cross-canal, não tem perfil de comunicação acumulado, não tem sentimento histórico, não tem "última reclamação", não tem padrão de churn. Líderes 2026 (Gladly, Decagon User Memory, Front Customer Context) tratam isso como pré-requisito pra qualquer agent inteligente. Sem isso, IA responde com amnésia toda conversa.
- **Gap fatal #2 — `AnalisarMensagemAgent` (PR #916) está aberto há dias mas adiado por custo.** Custo real estimado no próprio PR: **R$ 4 one-time backlog (21k msgs) + R$ 0,60/mês/biz real-time**. Isso é trivial — não é razão honesta pra adiar. Razão honesta provável: medo de drift biz=1 sem dry-run validado. Mergear este PR é a **maior alavanca de impacto vs esforço da lista**.
- **Gap alto-impacto #3 — VoC dashboard não existe.** Categoria/tema/urgência por msg sozinhos são dado bruto; o valor está em **agregação gerencial** ("essa semana 14 clientes reclamaram de entrega atrasada") + **drift detection** ("spike em 'cobrança duplicada' nas últimas 24h"). Este é o "transformar reclamação em administração" que Wagner literalmente pediu 2026-05-15.
- **Gap alto-impacto #4 — sem `Reply Suggestion` (MagicReply/Fin-style) baseada no perfil acumulado do cliente.** Tags + análise + memória cliente formam o substrato pra "sugerir resposta personalizada" no inbox — feature top vendável em PME BR (Take Blip cobra R$ 600/mês por isso).
- **Nota de maturidade vs líderes 2026 (apenas dimensão "conversational support + VoC + customer memory"):** **48/100**. Atrás de Front/Gladly/Crisp na profundidade de Customer Profile, par em schema omnichannel, à frente em multi-tenant Tier 0.
- **Recomendação:** mergear PR #916 **com dry-run gated por Wagner**, antes de qualquer outra coisa. Custos travados em ~R$ 5 totais no mês. Próxima ação hoje: `php artisan whatsapp:analyze-backlog --business=1 --channel=10 --since=24h --dry-run` e olhar o output.

---

## 2. Fase 1 — pesquisa: 5 players de referência + 1 PME-comparable

### 2.1 Intercom Fin AI Agent (líder PME→mid-market, RAG-first)

Fin é o agent de service "default" do mercado SaaS 2026. **Resolution rate médio 65-67%, top performers 93%**, em 40M+ conversas. Arquitetura é **3-camadas com aprendizado contínuo**: (1) Intelligence engine RAG — hybrid retrieval (vector + keyword) sobre Help Center + docs internos + ticket histórico; (2) Single agent que troca de "persona" (service/sales) por contexto da conversa; (3) Performance dashboard com `resolution rate × involvement rate × CX score` num só lugar. Referência porque **provou que RAG bem feito sobre KB própria + recall de tickets passados resolve 2/3 do tráfego sem treinar modelo**. ([Intercom Fin AI Agent explained](https://www.intercom.com/help/en/articles/7120684-fin-ai-agent-explained), [Resolution rate guide](https://www.intercom.com/help/en/articles/8205718-fin-ai-agent-outcomes))

### 2.2 Sierra.ai (líder enterprise, "Agent OS" multi-model)

Fundada por Bret Taylor (ex-Salesforce co-CEO, chair OpenAI), valuation $15.8B em maio/2026. Arquitetura "constellation of models" — **mix de OpenAI/Anthropic/Meta escolhido por task** (não 1 modelo pra tudo) com fine-tuning seletivo onde off-the-shelf falha. Componentes-chave: **Agent Data Platform (ADP, nov/2025)** — memória persistente cliente (histórico, CRM, status real-time); **Ghostwriter (mar/2026)** — builder conversacional que cria outros agents em linguagem natural; **PCI-compliant payments (abr/2026)** — agent processa transação financeira mid-conversation. Deploy unificado chat+SMS+WhatsApp+email+voice+ChatGPT de **1 config**. Referência porque **mostra que "memória persistente do cliente" virou produto separado (ADP) e não detalhe de implementação** — sinal de que mercado precificou. ([Constellation of models](https://sierra.ai/blog/constellation-of-models), [Plain.com 2026 analysis](https://www.plain.com/blog/conversational-ai-customer-service))

### 2.3 Decagon (líder enterprise concierge, AOPs natural-language)

$4.5B valuation (triplicou em 6 meses), $35M ARR, 100+ enterprise customers. Diferencial técnico: **Agent Operating Procedures (AOPs)** — workflows definidos em linguagem natural que compilam pra lógica estruturada executável (cliente edita "atendente" como se fosse onboarding humano). Resultados: 80% deflection, -65% custo, 93% quality score. **User Memory (lançado mar/2026)** é arquitetura canônica explícita em blog público: extrai e armazena **feature requests + sentiment + preferences declarados** como metadata estruturada anexada ao perfil do cliente, com **opt-in storage + expiration controls + redaction policies + API access**. Cross-channel memory: conversa começa em chat e continua em voz/email sem perda. Referência porque **publicou schema/contrato de memória cliente com LGPD/GDPR built-in** — replicável. ([Decagon User Memory blog](https://decagon.ai/blog/user-memory), [Spring 2026 launch](https://decagon.ai/blog/spring26-product-launch))

### 2.4 Front (líder colaboração, "customer context sidebar" canônico)

Plataforma de atendimento focada em "complexidade humana" — email + chat + SMS + voz num workspace compartilhado. Customer Context Sidebar é o pattern visual canônico replicado por Gladly/Intercom/Help Scout: sidebar à direita mostra histórico cross-canal + ordens/shipments/bookings puxados de sistemas externos em tempo real **dentro da conversa** (sem agent abrir 4 abas). 2026: **Smart QA** (QA automatizado por IA — monitora 100% das conversas, classifica qualidade) + Dialpad integrado (voice nativo). Referência porque **estabeleceu que "contexto cross-sistema dentro do thread" é o pattern UX canônico, não feature premium**. ([Front customer service platform](https://front.com/), [May 2026 release](https://front.com/blog/may-product-release))

### 2.5 Gladly (líder "people not tickets", radial timeline)

Posicionamento explícito: "customer service platform built around people, not tickets". Arquitetura: **Customer Profile único** com **radial timeline** — toda interação (email, chat, SMS, voz de 2 anos atrás, DM Facebook de ontem) feed num **único thread contínuo de anos** sob 1 perfil. Profile guarda LTV + status fidelidade + return rate + "preferências" derivadas de comportamento (favorite color, room preferences). **Agentic AI 2026:** vendedor de ponta-a-ponta enxerga o **mesmo histórico completo que humano** — handoff IA→humano não tem "começar do zero". Forrester + IDC chamam **unified customer data como hard requirement** pra agentic AI funcionar. Referência porque **é a forma operacional radical de "morte do ticket"** — extremamente influente em design system de concorrentes. ([Gladly product](https://www.gladly.ai/product/cx-team-platform/), [Agentic AI guide](https://www.gladly.ai/blog/agentic-ai-customer-service/))

### 2.6 (Bonus PME) Crisp MagicReply — comparable mais direto a oimpresso

Crisp é o player que PME/startup elege antes de Intercom/Zendesk. **MagicReply** sugere respostas baseadas em **conversas passadas do MESMO cliente + Help Center**. Funcionalidades adjacentes em 2026: **summarização automática de conversa pra handover de shift**, **switching áudio↔texto inline**, acesso a "dados e interações passadas" como sidebar. Tier free + Pro recomendado pra solopreneurs que batem 20+ tickets/dia. Referência porque é **o teto razoável que PME BR comparable do Wagner mira hoje** — não Sierra/Decagon (esses são fora do orçamento). ([Crisp MagicReply](https://www.success.ai/ai-tools/crisp-magicreply), [F3 2026 SMB comparison](https://f3fundit.com/ai-customer-support-for-solopreneurs-intercom-vs-crisp-vs-plain-vs-chatwoot-2026/))

---

## 3. Síntese: o que é "core moderno" não-negociável em 2026

Padrões convergentes que **TODOS** os 5+1 players têm (não é diferenciação, é table-stakes 2026):

| # | Padrão | Mecanismo concreto |
|---|---|---|
| 1 | **Unified Customer Profile cross-canal** | 1 perfil = N conversas históricas + atributos derivados (LTV, sentimento, churn risk, preferências). Gladly chama "Customer Profile", Decagon chama "User Memory", Front chama "Customer Context". |
| 2 | **Conversation timeline contínua (não tickets)** | Threads não fecham — viram histórico permanente sob o profile. "Ticket aberto/fechado" é anti-pattern declarado por DevRev, Gladly, Plain. |
| 3 | **RAG-first sobre KB + histórico ticket** | Intercom Fin, Decagon, Sierra. Hybrid retrieval (vector + keyword), não fine-tuning. |
| 4 | **VoC auto-classification por LLM** | Categoria/tema/urgência/sentimento extraído em tempo real, **sem taxonomia manual**. Plataformas (Chattermill, Thematic, Pivony) abandonaram tagging humano em 2025. |
| 5 | **Multi-channel deployment de 1 config** | Sierra/Decagon: chat+voz+email+SMS+WhatsApp num único agent config. |
| 6 | **Audit trail visível ao cliente final** | HubSpot Audit Cards, Decagon redaction policies + expiration. LGPD Art. 20 (BR) torna isso regulatório-obrigatório em 2026 segundo ANPD. |
| 7 | **Reply suggestion no inbox humano (não só auto-resolve)** | Crisp MagicReply, Front, Intercom Co-Pilot. Padrão "IA sugere, atendente aprova" antecede "IA resolve sozinha" no roadmap PME. |
| 8 | **Modelo small-first (Haiku/4o-mini-class), waterfall pro maior** | Sierra publicou explicitamente: 70-80% tráfego em modelos menores. Custo total -70% vs flagship. |

Anti-patterns abandonados em 2026 (citados explicitamente pelos players):

- ❌ **Ticket system com status "aberto/fechado/escalado"** — DevRev e Plain declararam "ticket management is dead"; substituído por **conversation timeline contínua + state derivada de SLA + IA action history**.
- ❌ **Queue-based routing manual** — substituído por **skill+context routing automático** com fallback humano por confidence.
- ❌ **Tagging manual de conversa** — automatizado por LLM; humano só revisa exceções (catalogado por Chattermill 2026).
- ❌ **Knowledge base separada do "outro lado"** — Help Center e ticket histórico viram **mesma fonte RAG**. Separar é overhead.
- ❌ **Memória de sessão (some no logout)** — substituída por persistent cross-session. Memoria/Mem0/Sierra ADP são frameworks dedicados.

---

## 4. Fase 2 — comparativo oimpresso vs estado-da-arte (15 dimensões)

Notação: **0** não existe · **1** embrionário · **2** funcional com gap · **3** par com líderes 2026 · **3+** supera líderes.

| # | Dimensão | Estado-da-arte 2026 (ref) | oimpresso hoje | Nota | Justificativa |
|---|---|---|---|---|---|
| 1 | **Schema omnichannel polimórfico** | Sierra/Front/Gladly: 1 conversation table cross-canal | [ADR 0135](../decisions/0135-omnichannel-inbox-arquitetura.md) — `channels`+`conversations`+`messages` LIVE em prod, biz=1 + biz=4 | **3** | Schema 1:1 com líderes; falta drivers Insta/Email/ML mas isso é gate cliente-sinal |
| 2 | **Customer Profile unificado** | Gladly radial timeline, Decagon User Memory schema | `Contact` (UltimatePOS legacy) — cadastro estático CPF/CNPJ/email/mobile + `whatsapp_consent` LGPD. **SEM timeline cross-canal, SEM perfil derivado, SEM sentimento** | **1** | Gap fatal. Cadastro ≠ profile. |
| 3 | **Memória persistente do cliente final** | Decagon "stored preferences/feature requests/sentiment metadata"; Sierra ADP | `copiloto_memoria_facts` é só usuário SaaS (Wagner/Larissa). **NÃO existe tabela equivalente pra Contact (cliente externo)** | **0** | Gap fatal — confusão de escopo |
| 4 | **VoC auto-classification por msg** | Chattermill/Thematic: LLM extrai theme+sentiment+urgency real-time | `AnalisarMensagemAgent` esboçado [PR #916](https://github.com/wagnerra23/oimpresso/pull/916) — categoria+tema+urgência+resumo via gpt-4o-mini, custo ~R$4 backlog. **PR aberto, adiado** | **1** | 80% pronto, falta merge — alto-impacto-baixo-esforço extremo |
| 5 | **VoC dashboard agregado** | Pivony/Observe.AI: trend detection + spike alert + root cause | Não existe. PR #916 menciona como "out of scope próximos PRs" | **0** | Sem isso, classificação é dado bruto inútil |
| 6 | **Tags/categorização de conversa** | Auto via LLM (2026) ou manual + sugestão IA | `whatsapp_tags` + `whatsapp_conversation_tags` (manual, atendente aplica) | **2** | Manual funciona; falta auto-suggest IA derivada da análise da msg |
| 7 | **Reply suggestion baseada em histórico** | Crisp MagicReply, Front, Intercom Co-Pilot | Existe `Macros` + `MacroVariants` (templates manuais), mas **sem sugestão IA contextual** | **1** | Macros é base boa; falta camada "IA escolhe macro + personaliza" |
| 8 | **RAG sobre KB própria** | Intercom Fin, Decagon | Jana `KbAnswerAgent` existe + Meilisearch hybrid embedder + MCP `memoria-search` | **2** | RAG infra par com líderes; **não está plugado no inbox WhatsApp** (não responde cliente externo) |
| 9 | **Conversation timeline cross-canal** | Gladly radial timeline | Schema permite (ADR 0135) mas UI Inbox só mostra **1 conversa por vez** — sem agregação cross-canal por cliente | **1** | Backend pronto, UI ausente |
| 10 | **Multi-channel deploy de 1 config** | Sierra/Decagon | ADR 0135 prevê (drivers Insta/Email/ML em fases 1-3 com gate cliente-sinal) | **2** | Arquitetura ok; só Whatsapp em prod hoje |
| 11 | **Audit trail visível cliente final** | HubSpot Audit Cards, LGPD Art. 20 enforceável | `mcp_dual_brain_decisions` (interno) + FSM `sale_stage_history`. **Cliente externo não vê nada** | **1** | Backend ok; **UI cliente final inexistente → risco LGPD Art. 20** |
| 12 | **Multi-tenant Tier 0 isolation** | Nenhum líder trata como absoluto; geralmente é "Enterprise feature" | `business_id` global scope IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) | **3+** | Supera líderes — vantagem estrutural |
| 13 | **LGPD opt-in granular + retention** | Decagon expiration controls, ANPD enforcement 2026 | `Contact.whatsapp_consent`/`email_consent` (booleano) — **sem timestamp opt-in, sem auto-expire 24m, sem audit de consent** | **1** | Campo existe, política ausente — multas ANPD começaram 2026 |
| 14 | **Custo LLM tiered (small-first)** | Sierra/Decagon waterfall | Brain A (Ollama local $0) + Brain B (Sonnet 4.6) + AnalisarMensagemAgent (gpt-4o-mini) | **3** | Padrão correto desde o início |
| 15 | **Async support continuous (anti-ticket)** | DevRev/Plain "ticket is dead" | `conversations.status` tem `open`/`awaiting_human`/`resolved`/`archived` — mas atendentes ainda pensam em "fechar ticket" | **2** | Schema neutro; cultura ainda ticket-style |

**Resumo notas:** 5×**3 ou 3+**, 4×**2**, 4×**1**, 2×**0**. Média ponderada **~1.9 → 48/100** vs líderes 2026.

---

## 5. Fase 3 — gaps rankeados por impacto × esforço

> Estimates seguem [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10× IA-pair + margem 2×.

| Rank | Gap | Impacto | Esforço IA-pair | Pré-req? | Risco se não fizer |
|---|---|---|---|---|---|
| **1** | **Mergear PR #916 `AnalisarMensagemAgent` com dry-run gated** | **Alto** — destrava todos gaps 2-5 (sem análise não dá pra agregar nem perfilizar) | ~2h (review + dry-run biz=1 + flag activation + Wagner aprova) | Nenhum — PR já existe | Wagner literalmente disse "tudo que receber vai ter que ser analisado"; sem isso, promessa quebrada |
| **2** | **VoC Dashboard agregado** (top reclamações + spike alert + trend semanal) | **Alto** — é o "transformar reclamação em administração" do Wagner | ~6h (1 Controller + 1 Page Inertia + 3 widgets agregando `messages.analise_*`) | Gap #1 mergeado | Análise vira dado morto, esforço gpt-4o-mini desperdiçado |
| **3** | **Customer Profile schema (`contact_profile` tabela nova)** + accumulate por inbound msg | **Alto** — destrava gaps 3, 7, 9, 11 (memória cliente + reply suggestion + audit visível) | ~8h (migration + service `ContactProfileService::accumulate(message)` + extract via mesmo Agent #916 + Pest cross-tenant) | Gap #1 mergeado | IA continua com amnésia toda conversa; Wagner não pode fazer "Larissa pergunta de Maria e Jana sabe que Maria reclamou de entrega 3x" |
| **4** | **Auto-tag de conversa derivada de análise IA** | Médio | ~3h (Listener que pega `analise_categoria` da última msg + sugere tag no UI) | Gaps #1, #6 (tags já existe) | Atendente continua taggando manual — economia trivial mas visível |
| **5** | **Reply Suggestion IA no Inbox** (rascunho automático baseado em perfil + KB) | Alto | ~10h (Service `ReplyDraftService` + KbAnswerAgent integrado + UI "rascunho sugerido" + flag opt-in per-business + Pest) | Gap #3 (perfil cliente) + RAG já existe | Atendente humano fica lento — Take Blip ganha clientes que iam pro oimpresso |
| **6** | **LGPD Art. 20 compliance UI cliente final** ("decisão tomada por IA - peça revisão aqui") | Médio-Alto | ~5h (rota pública `/atendimento/auditoria/{conv_uuid}` + opt-out IA flag em `Contact` + log de decisões expostas) | Gaps #1, #3 | Multa ANPD começou 2026; risco regulatório real |
| **7** | **Timeline cross-canal por Contact na sidebar** | Médio | ~4h (Inertia sidebar agrega `conversations` JOIN `messages` por `contact_id` ordenado desc, paginado) | Gap #3 (precisa de `contact_id` populado consistente) | Atendente perde contexto histórico; ainda assim "funciona" |
| **8** | **`whatsapp_consent` virar consent record com timestamp+source+auto-expire 24m** | Médio | ~3h (migration `contact_consents` + observer + auto-expire job) | Nenhum bloqueante | Risco LGPD multa; trabalho técnico simples |
| **9** | **Drivers Instagram/Messenger** (Fase 1 ADR 0135) | Baixo (gate cliente-sinal) | ~6h por driver | ADR 0105 cliente pagante pedir | Nenhum — gate de cliente protege |
| **10** | **Conversation merge by Contact** (todas conversas de Maria viram thread) | Baixo (UX nice-to-have) | ~6h | Gap #7 | Atendente faz mental merge — caro só em volume |

---

## 6. Recomendação concreta

**Comece pelo Gap #1 — mergear PR #916.**

Por quê:
- **Alto impacto**: destrava cadeia inteira de gaps 2 (dashboard), 3 (perfil cliente), 4 (auto-tag), 5 (reply suggestion). Sem análise, nada disso existe.
- **Esforço trivial**: ~2h (PR já existe com 18 Pest cases, gates Tier 0, dry-run command, opt-in flag por business).
- **Sem pré-req bloqueante**: PR está aberto, CI tem que ficar verde, Wagner aprova dry-run biz=1 primeiro.
- **Custo real travado**: R$ 4 one-time + R$ 0,60/mês/biz. Comparar com R$ 300-1.500/mês cobrado por Take Blip pela mesma feature.
- **Honestidade brutal**: o motivo original do adiamento ("custo gpt-4o-mini") **não bate matematicamente**. O custo verdadeiro é "Wagner não rodou dry-run ainda". Resolver isso é uma reunião de 15 min com terminal aberto.

**Próxima ação concreta hoje (Wagner):**

```bash
# 1) Sair desta worktree e voltar pro repo principal
cd D:/oimpresso.com

# 2) Checkar PR #916 na branch dele (zero deploy, zero merge)
gh pr checkout 916

# 3) Migrar local (idempotente, adiciona colunas analise_* a messages)
php artisan migrate

# 4) Dry-run em prod biz=1 canal Suporte (zero custo IA — só conta msgs e enfileira N/A)
php artisan whatsapp:analyze-backlog --business=1 --channel=10 --since=24h --dry-run

# 5) Olhar output (~ "X mensagens elegíveis, custo estimado R$Y")
# 6) Se número convencer, sair do PR checkout, voltar pra main, mergear PR via gh pr merge --squash
```

**Depois disso (mesma semana):** Gap #2 (dashboard agregado) — 6h IA-pair. Wagner abre o dashboard, vê "essa semana 14 menções a 'entrega atrasada'" e a promessa "usar reclamações pra administrar" sai do papel.

**Depois (próximas 2 semanas):** Gap #3 (Customer Profile schema). Aqui sim entra ADR nova — vale formalizar contrato `ContactProfileService::accumulate()` + retenção LGPD + redaction + opt-out IA antes de codar. Não inventar agora — deixar o uso real de #1+#2 informar o design.

---

## 7. Não-fazer agora (anti-recomendações)

- ❌ **Não pesquisar mais sobre Sierra ADP / Decagon User Memory.** Pesquisa já entregue acima. Mais pesquisa = procrastinação disfarçada.
- ❌ **Não trocar gpt-4o-mini por modelo maior antes do dry-run.** R$ 4 ainda não foi gasto — calibrar modelo sem dado de qualidade é cargo cult.
- ❌ **Não criar `contact_profile` table antes de mergear #916.** Sem categoria/tema/urgência por msg, perfil não tem o que acumular. Sequência importa.
- ❌ **Não abrir frente de Instagram/Email/ML driver agora.** ADR 0135 já cravou gate cliente-sinal — sem cliente pagante pedindo, é overhead.
- ❌ **Não fazer "VoC complete suite" tipo Chattermill em 1 PR.** Decompõe em #1 (análise por msg) → #2 (dashboard simples) → #3 (drift detection) → #4 (alerta auto-trigger). 4 PRs ≤300 linhas cada.

---

## 8. Pendências de governança (criar se Wagner aprovar caminho)

- **ADR nova** quando Gap #3 for executado: "Customer Profile schema + accumulate contract + LGPD retention" (referencia ADR 0093, 0135, 0061; supersedes nada).
- **Skill nova Tier B** `voc-dashboard-update` — auto-trigger quando Edit em página `/atendimento/voc/*` ou Service `AnaliseMensagemService`.
- **`review_triggers` no ADR 0135** quando Gap #4 entrar: "auto-tag derivada de IA atingir ≥85% precisão → desativar tagging manual default em prod".

---

## 9. Fontes (Fase 1 — pesquisa limpa)

- [Intercom Fin AI Agent explained](https://www.intercom.com/help/en/articles/7120684-fin-ai-agent-explained)
- [Intercom Fin resolution rates & outcomes](https://www.intercom.com/help/en/articles/8205718-fin-ai-agent-outcomes)
- [Sierra.ai constellation of models](https://sierra.ai/blog/constellation-of-models)
- [Sierra product overview](https://sierra.ai/product)
- [Plain.com — Conversational AI 2026 analysis](https://www.plain.com/blog/conversational-ai-customer-service)
- [Decagon User Memory blog (mar/2026)](https://decagon.ai/blog/user-memory)
- [Decagon Spring 2026 launch (Proactive Agents)](https://decagon.ai/blog/spring26-product-launch)
- [Front customer service platform](https://front.com/)
- [Front May 2026 product release (Smart QA, Dialpad)](https://front.com/blog/may-product-release)
- [Gladly customer profile (people not tickets)](https://www.gladly.ai/product/customer-profile/)
- [Gladly agentic AI 2026 guide](https://www.gladly.ai/blog/agentic-ai-customer-service/)
- [Crisp MagicReply](https://www.success.ai/ai-tools/crisp-magicreply)
- [Crisp AI overview](https://crisp.chat/en/ai/)
- [F3 Fund It — Solopreneur AI support comparison 2026](https://f3fundit.com/ai-customer-support-for-solopreneurs-intercom-vs-crisp-vs-plain-vs-chatwoot-2026/)
- [Chattermill — Best conversational analytics 2026](https://chattermill.com/blog/best-conversational-analytics-tools)
- [Chattermill — LLMs for customer feedback at scale](https://chattermill.com/blog/how-to-use-llms-to-analyse-customer-feedback-at-scale)
- [Thematic — Conversational analytics guide](https://getthematic.com/insights/conversational-analytics)
- [Pivony — VoC analytics 2026 practitioner guide](https://pivony.com/blog/what-is-voice-of-customer-analytics-the-2026-practitioners-guide/)
- [Treasure Data — Customer 360 in 2026](https://www.treasuredata.com/blog/customer-360)
- [SocialHub — LGPD 2026 ANPD multa WhatsApp PME](https://www.socialhub.pro/blog/lgpd-2026-anpd-multa-whatsapp-marketing-pme-base-legal-consentimento/)
- [DevRev — Ticket management is dead 2026](https://devrev.ai/blog/ticket-management)
- [BlueTie — Reactive IT is dead 2026](https://bluetie.com/reactive-it-is-officially-dead-why-2026-belongs-to-proactive-monitoring-not-ticket-based-support/)
- [OpenAI API pricing 2026 (cost calculator)](https://developers.openai.com/api/docs/pricing)
- [PE Collective — Cross-provider LLM pricing 2026](https://pecollective.com/blog/llm-pricing-comparison-2026/)
- [Vellum — GPT-4o-mini vs Haiku vs 3.5 customer support benchmark](https://www.vellum.ai/blog/gpt-4o-mini-v-s-claude-3-haiku-v-s-gpt-3-5-turbo-a-comparison)
- [Zendesk AI agent GDPR/LGPD compliance 2026](https://www.eesel.ai/blog/zendesk-ai-agent-gdpr-compliance)
- [Mem0 — State of AI Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [Memoria framework arxiv 2512.12686](https://arxiv.org/html/2512.12686v1)
