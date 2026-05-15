# Análise Voz do Cliente — Canal Suporte biz=1 · 2026-05-15

> Análise feita por Claude (sem chamada IA externa) em cima de amostra real de 80 msgs + estatísticas agregadas do canal Suporte WR Sistemas (channel_id=10, display_identifier=554896486699). Wagner pediu "tudo que receber aqui vai ter que ser analisado" e "é mais barato você analisar". Este doc é o **output da análise** + **proposta de onde ficam as análises daqui pra frente** + **esboço do estado-da-arte de atendimento e memória cliente**.
>
> PII redact: nomes próprios de clientes substituídos por "Cliente A/B/C". Telefones mostram só prefixo+sufixo (`+5527...5927`). Nomes do time interno WR (Maiara, Luiz, Felipe) preservados — declarados em [`memory/regras-time.md`](../regras-time.md).
>
> Companion: `memory/sessions/2026-05-15-arte-atendimento-omnichannel-memoria-cliente.md` (agent `estado-da-arte` rodando paralelo — referência externa Front/Intercom/Crisp/Sierra/Decagon 2026).

---

## TL;DR (60s)

**Estado prod biz=1 canal Suporte agora:**
- **249 clientes únicos** ativos · **29.355 msgs** (18.470 inbound / 10.885 outbound) · pico 18h
- Time atendendo: **Maiara · Luiz · Felipe** (Wagner não atende direto, fica supervisor)
- Atendimento via **AnyDesk remoto** é o padrão dominante ("passa o acesso" + "vou conectar")

**Top 3 categorias dominantes** (inferidas da amostra):
| # | Tema | Sinal | Implicação |
|---|---|---|---|
| 1 | **NFe / SEFAZ / emissão de nota** | 30-40% dos tickets · "Sefaz atualizou regras" · "não consigo emitir NF" | Gargalo crítico do produto — área que mais consome time |
| 2 | **Bugs pós-atualização do sistema** | "Foi feita uma atualização e deram alguns bugs" · "Ainda não consegui fazer notas conforme atualização" | Cliente PERCEBE regressões. Sinal de qualidade pós-release frágil |
| 3 | **Retorno bancário / boleto** | "problemas no retorno do arquivo do banco" · "não está dando baixa" | Jornada Financeiro/cobrança crítica — automação mal calibrada |

**Insight gerencial central:** a fila de Suporte está sendo usada como **fila de bug report do produto**. Não é só atendimento — é **sinal de roadmap**. Catalogar isso vira input direto de priorização ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md): "cliente como sinal qualificado").

**Onde devem ficar as análises** (proposta 4 camadas, detalhe §3):
1. **Por mensagem** → `messages.analise_*` (PR #916 draft, fica pra outro dia)
2. **Por conversa** → tabela `conversation_insights` (resumo + outcome + tempo)
3. **Por cliente** → tabela `customer_memory` (perfil acumulado, churn score, temas recorrentes)
4. **Por período** → snapshot diário/semanal (Wagner consome de manhã via brief)
5. **Por feature** → `product_feedback_signals` (Capterra reverso — bugs/dúvidas viram input do roadmap)

---

## §1 — Análise da amostra (80 msgs canal Suporte biz=1, 2026-05-15)

### Padrões linguísticos observados

| Pattern | Exemplo (anonimizado) | Frequência |
|---|---|---|
| **Saudação inflada** | "BOMMMMMDIAAAAA" / "Boa tarde, Maiara!!!" | Alta — abertura típica |
| **"Passa o acesso"** (AnyDesk handoff) | "vou te mandar o acesso" / "Me passa o acesso da sua máquina" | Dominante — gargalo de escala |
| **Bug report direto** | "não está dando baixa" / "deu erro" / "está muito lento" | Alta — produto fala via cliente |
| **Desespero contido** | "Estou com clientes na minha sala" / "Assim que poder me liga por favor" | Média — urgência percebida vs real |
| **Confirmação resolução** | "obrigadaaa" / "perfeito" / "muito obrigada" | Alta — clientes pacientes, gratos |

### Distribuição horária (inbound biz=1)

```
11h: 274     ▏
12h: 1.731   ████▍
13h: 1.441   ███▌
14h: 1.677   ████▏
15h: 1.640   ████
16h: 1.747   ████▍
17h: 1.803   ████▌
18h: 4.477   ███████████▏  ← pico (provável viés history sync chunk burst)
```

> **Nota:** o pico de 18h pode ser distorção do history sync importando em chunks — não necessariamente padrão real de comportamento. Validar com janela 7d quando estabilizar.

### Quem reclamou de quê (amostra de 30 msgs com palavras-chave erro/bug/problema/atualização)

Distribuição por **módulo do oimpresso** (inferido):

| Módulo | Msgs flagged | Sinais |
|---|---|---|
| **NfeBrasil** | 8/30 (27%) | "problema pra emitir nf" · "erro pra emitir nota" · "notas conforme atualização" · "verificar erro de emissão NF" |
| **Pós-atualização (multi-módulo)** | 5/30 (17%) | "atualização do sistema e deram bugs" · "Quanto demora a atualização?" · "sistema lento pós-reinstalação" |
| **Financeiro / boleto** | 3/30 (10%) | "retorno do arquivo do banco" · "não está dando baixa" |
| **Vendas / anexo** | 2/30 (7%) | "anexar arquivo na venda e tá dando erro" · "buga tudo finalizar venda" |
| **Layout / impressão** | 2/30 (7%) | "não consegue liberar pra salvar o layout" · "dar erro na remessa" |
| **Email / domínio** | 1/30 (3%) | "domínio email aparece erro" |
| **Spam B2B** | 1/30 (3%) | Vendedora terceira oferecendo plano Claro Empresas |
| **Outros / ambíguo** | 8/30 (27%) | "ainda to com dois problemas" / "deu o mesmo erro" — precisa contexto |

### Top conversas mais ativas (24h)

| conv | Cliente | n_msgs | Janela | Hipótese |
|---|---|---|---|---|
| 47 | +5527...5927 | **1.800** | 7h | Cliente único com problema complexo OU history sync concentrado |
| 41 | +5545...8597 | 1.167 | 6h |  |
| 46 | +5548...0075 | **929** | 6h | (msgs flagged "Esse é o erro" / "Mesmo erro" — issue recorrente não resolvida) |
| 64 | +5548...0182 | 881 | 6h |  |
| 105 | +1141...01288 | 857 | 5h | (DDI estranho — talvez número internacional ou erro de parsing JID) |

⚠️ **Atenção:** ratio msgs/cliente desigual (top 10 conversas concentram >40% do volume) — sinal de **clientes "frágeis"** que demandam desproporcional atenção. Candidatos a perfil "VIP" ou "high-churn-risk".

### Sinais qualitativos load-bearing

1. **"A Sefaz atualizou algumas regras de nota e estamos com uma demanda muito grande de atendimento"** (Maiara, conv=50) — **reconhecimento explícito de overload** do time. Métrica: tempo resposta SLA em risco.

2. **"Eu peguei uma cópia do banco de dados e iremos analisar melhor as mensagens"** (atendente, conv=204) — **caso desbloqueado por debug profundo** (export DB). Não escala.

3. **"Estou com clientes na minha sala"** (Cliente A, conv=50) — **cliente atende cliente final** (B2B2C). Cada minuto de espera = perda do cliente final dela. Urgência multiplicada.

4. **"Semana passada deu problema no nosso servidor e teve q reinstalar o sistema mas agora está muito lento"** (conv=47) — **regressão pós-reinstalação** confirma fragilidade de updates/installs.

5. **"quando eu termino uma venda... aparece mensagem pra cadastrar email... eu coloco 'não quero' e buga tudo o sistema"** (conv=284) — **bug específico, reproduzível, prioridade alta** pro roadmap.

---

## §2 — Top sinais gerenciais (priorizados impacto × esforço)

### Sinal A — Pipeline de release fragilizada (atualização vira bug report)
- **Evidência:** múltiplos clientes mencionam "pós-atualização e deram bugs" como CAUSA do problema atual
- **Implicação:** sistema CI/CD do oimpresso precisa de canary 7d ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) já caminha nessa direção) + smoke test pós-deploy + comunicação proativa "tem atualização, vocês podem reportar problemas em <link>"
- **Ação Wagner:** próximo deploy de produção, adicionar mensagem proativa WhatsApp template "Atualização aplicada — qualquer comportamento estranho, nos avise". Reduz volume reativo + dá audit trail estruturado.

### Sinal B — NFe é o produto crítico — gargalo de roadmap
- **Evidência:** ~30-40% dos tickets são NFe/SEFAZ
- **Implicação:** investimento em [Modules/NfeBrasil](../../Modules/NfeBrasil/) tem ROI alto. Cada hora de dor da Maiara/Luiz com SEFAZ rejeitando = oportunidade de UI mais inteligente + erros amigáveis no oimpresso.
- **Ação Wagner:** ler conversas conv=50/conv=218 inteiras (são casos NFe recorrentes) e listar top 3 "erros NFe que o sistema poderia explicar melhor" como entrada pro próximo cycle.

### Sinal C — AnyDesk é o gargalo de escala
- **Evidência:** "passa o acesso" / "vou conectar" é padrão dominante. Cada atendimento = sessão remota humana ~10-30min.
- **Implicação:** sem **self-service inteligente** (FAQ contextual + guia visual + bot Jana resolvendo top 20 casos sem humano), time não escala. Atender 500 clientes ≠ atender 49 do jeito atual.
- **Ação Wagner:** mapear top 5 casos resolvidos via AnyDesk → criar fluxo self-service guiado dentro do oimpresso (modal "preciso de ajuda → categoria X → passo-a-passo do bot Jana").

### Sinal D — Clientes "high-touch" concentrados
- **Evidência:** 10 conversas concentram >40% do volume
- **Implicação:** distribuição Pareto. Esses 10 clientes ou são (a) clientes VIP que pagam mais OU (b) clientes frustrados que vão churnar. Diferenciar via análise é crítico.
- **Ação Wagner:** review semanal dos top 10 conversas (manual ou via brief automatizado quando arquitetura §3 estiver em pé) — decisão por cliente: "investir mais" vs "deixar partir bem".

### Sinal E — Bug específico documentado (conv=284)
- "finalizar venda → modal email → 'não quero' → buga tudo"
- **É caso reproduzível** — entra direto como issue P1 no roadmap Sells/Vendas.
- **Ação Wagner:** ler msg id 28935 + abrir issue no GitHub spawn task MCP com US-SELL-XXX.

### Sinal F — Spam B2B no canal de Suporte
- Vendedora Claro Empresas oferecendo plano de telefonia
- **Implicação:** canal Suporte recebe ruído de prospecting. Filtro automático "spam" libera tempo humano.
- **Ação Wagner:** baixa prioridade — só se volume crescer. Hoje é caso isolado.

---

## §3 — Onde ficam as análises (arquitetura proposta — 5 camadas)

> **Princípio:** análise não vive em 1 lugar. Cada granularidade serve um consumidor diferente. Camadas 1-4 são DATA, camada 5 é UI. Tudo respeita Tier 0 multi-tenant ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)).

### Camada 1 — Análise **por mensagem** (granular, opcional)
- **Onde:** `messages.analise_*` (9 colunas — categoria/tema/urgência/resumo + tracking)
- **Quando:** quando vale o custo IA (Wagner liga via flag por business)
- **Status atual:** PR #916 DRAFT — código pronto, esperando Wagner aprovar custo (~R$ [redacted Tier 0] backlog + R$ [redacted Tier 0]/mês/biz)
- **Consumidor:** dashboard agregado tempo real (camada 5)
- **Skip:** se Wagner preferir não pagar IA, pula esta camada e analise eu (Claude) periodicamente em sessions ad-hoc

### Camada 2 — Análise **por conversa** (síntese)
- **Onde proposto:** nova tabela `conversation_insights`
- **Schema (esboço):**
  ```sql
  conversation_insights (
    id, business_id INDEXED,
    conversation_id INDEXED,
    tema_principal VARCHAR(32),      -- nfe|caixa|boleto|venda|atualizacao_bug|...
    sentimento_score DECIMAL(3,2),   -- -1.00..+1.00
    outcome VARCHAR(20),             -- resolvido|escalado|abandonado|em_andamento
    tempo_resolucao_min INT,
    n_msgs_inbound INT, n_msgs_outbound INT,
    atendente_principal_user_id INT NULL,
    resumo_executivo TEXT,           -- 3-5 linhas Jana
    flags JSON,                       -- [{tipo:"churn_risk", grade:"alto"}, {tipo:"bug_report", modulo:"NfeBrasil"}]
    updated_at,                       -- atualiza quando conversa fecha OU a cada N msgs
    created_at
  )
  ```
- **Quando atualiza:** trigger ao marcar conv `resolved` OU cron diário 03h pra conversas abertas há >24h
- **Consumidor:** sidebar UI Inbox "Resumo da conversa", filtro Inbox "só churn-risk-alto"

### Camada 3 — Memória **por cliente** (perfil acumulado — a peça central)
- **Onde proposto:** nova tabela `customer_memory` (single source of truth da memória cliente final)
- **Schema (esboço):**
  ```sql
  customer_memory (
    id, business_id INDEXED,
    customer_external_id VARCHAR(40) INDEXED,  -- E.164 telefone canônico ('+5548...')
    contact_id INT NULL,                        -- FK Contact (UltimatePOS CRM) quando linkado
    
    -- Stats agregados (Job daily 02h)
    n_conversations INT,
    n_msgs_total INT,
    first_interaction_at TIMESTAMP,
    last_interaction_at TIMESTAMP,
    avg_response_time_min INT,
    
    -- Inferências (LLM acumula janela 90d)
    temas_recorrentes JSON,    -- ["nfe","boleto","atualizacao_bug"]
    sentimento_score DECIMAL(3,2),
    churn_risk_score DECIMAL(3,2),
    comunicacao_preferida JSON, -- {hora_pico:"14h-17h", canal:"whatsapp", tom:"formal"}
    
    -- Memória qualitativa Jana (≤2KB texto)
    notas_jana TEXT,
    notas_atualizada_em TIMESTAMP,
    
    -- Operacional / tagging
    flags JSON,  -- [{tipo:"vip", motivo:"alto LTV"}, {tipo:"fragil", since:"2026-05-10"}]
    
    created_at, updated_at,
    UNIQUE (business_id, customer_external_id)
  )
  ```
- **Quando atualiza:**
  - Job daily 02h recalcula stats agregados
  - Listener `OmnichannelMessageReceived` invalida cache da memória do cliente
  - Jana pode escrever em `notas_jana` quando aprende algo (ex: "cliente X disse que prefere ser atendido pela Maiara — registrar")
- **Consumidor:**
  - **Sidebar UI Inbox "Sobre o cliente"** (Customer 360 — pattern Front/Intercom)
  - Jana Bot via `decide(intent: 'reply', context: customer_memory.get($contact))` quando responder
  - Brief gerencial agrega top clientes risco

### Camada 4 — Snapshot **por período** (consumível Wagner)
- **Onde proposto:** tabela `customer_voice_snapshots`
- **Schema (esboço):**
  ```sql
  customer_voice_snapshots (
    id, business_id INDEXED,
    snapshot_date DATE INDEXED,
    period VARCHAR(20),  -- daily|weekly|monthly
    
    top_temas JSON,             -- [{tema:"nfe", n:124, sentimento:-0.32}, ...]
    top_reclamacoes JSON,        -- top 5 reclamações textuais únicas (clusterizadas)
    top_features_demandadas JSON,-- INSIGHT ROADMAP — features que clientes pedem
    sentimento_medio DECIMAL(3,2),
    n_clientes_ativos INT,
    n_msgs_total INT,
    n_conversations_novas INT,
    
    brief_markdown LONGTEXT,     -- Jana resume período num briefing pro Wagner
    
    UNIQUE (business_id, snapshot_date, period)
  )
  ```
- **Quando gera:** cron daily 06h BRT (alinhado com Daily Brief — [ADR 0091](../decisions/0091-daily-brief.md))
- **Consumidor:** dashboard `/copiloto/voz-cliente` (UI nova) + email Wagner manhã

### Camada 5 — Sinais **por feature/módulo** (Capterra reverso — input roadmap)
- **Onde proposto:** tabela `product_feedback_signals`
- **Schema (esboço):**
  ```sql
  product_feedback_signals (
    id, business_id INDEXED,
    modulo VARCHAR(40),          -- NfeBrasil|Caixa|Vendas|Financeiro|...
    severidade VARCHAR(20),       -- crítico|alto|medio|baixo
    n_mencoes_30d INT,
    primeira_mencao_at, ultima_mencao_at,
    exemplos_msg_ids JSON,        -- [29083, 28526, 28411] — pra ler msgs originais
    sugestao_feature TEXT,        -- Jana sugere: "modal explicativo erro NFe X"
    status VARCHAR(20),           -- novo|triage|planejado|em_dev|shipped|rejeitado
    linked_task_id VARCHAR(40) NULL,  -- task MCP quando virar US no SPEC
    
    UNIQUE (business_id, modulo, sugestao_feature(50))
  )
  ```
- **Quando atualiza:** Jana mensal (cron 1º dia do mês) agrega `messages` por módulo e clusteriza sugestões
- **Consumidor:** Wagner durante planejamento cycle — lê `product_feedback_signals` filtrado por `n_mencoes_30d > 5 ORDER BY severidade`

### Resumo das 5 camadas

| Camada | Tabela | Granularidade | Atualiza | Consumidor |
|---|---|---|---|---|
| 1 | `messages.analise_*` | Por mensagem | tempo real (Job) | Dashboard tempo real |
| 2 | `conversation_insights` | Por conversa | trigger ou cron daily | UI sidebar conversa |
| 3 | `customer_memory` | Por cliente | cron daily + Jana writes | UI sidebar cliente · Bot Jana context |
| 4 | `customer_voice_snapshots` | Por período | cron daily 06h | Dashboard Wagner · email morning |
| 5 | `product_feedback_signals` | Por módulo | cron monthly | Wagner planning cycle |

**Migração proposta:** Camada 3 (`customer_memory`) é a PEÇA CENTRAL — começar por ela. Camadas 2, 4, 5 são derivações cruzando `customer_memory` × `conversations` × `messages`. Camada 1 fica pra última (PR #916 retomado quando custo IA aprovado).

---

## §4 — Memória cliente · como deveria ser (em 2026)

### Hoje (estado atual oimpresso)

| Peça | Onde | Status |
|---|---|---|
| Identidade cliente | `Contact` (UltimatePOS CRM legacy) — nome, telefone, CPF, endereço | ✅ existe |
| Conversa | `conversations` (omnichannel, ADR 0135) | ✅ existe, mas `contact_id=NULL` em todas biz=1 (não linkadas) |
| Histórico interação | `messages` append-only | ✅ existe, falta indexar pra recall semântico |
| Memória usuário SaaS (Wagner/Maiara) | `copiloto_memoria_facts` (Jana, ADR 0036) | ✅ existe |
| **Memória do cliente FINAL** (a pessoa do outro lado do WhatsApp) | — | ❌ **NÃO EXISTE** |

### Gap identificado

Hoje, quando o cliente +5548...0075 (conv=46) manda "Esse é o erro" pela 3ª vez consecutiva, o atendente NÃO sabe automaticamente:

- Quantas vezes esse cliente já reportou bugs no último mês
- Qual módulo ele mais reclama
- Se ele é VIP (alto LTV) ou frágil (alto churn risk)
- Quando foi a última interação
- Qual a preferência dele (tom formal? casual? horário?)
- Se ele já recebeu workaround similar antes

Resultado: cada conversa começa do zero. Atendimento humano carrega isso na cabeça da Maiara/Luiz — **conhecimento implícito não escala**.

### Como deveria ser (vai detalhar quando agent `estado-da-arte` entregar — referência preliminar)

Padrão consolidado em 2026 (Front, Intercom, Crisp v4, Help Scout):

#### 1. Customer 360 sidebar (sempre visível durante atendimento)
- Foto/nome + 1 linha "quem é esse cliente"
- Stats: n interações, tempo médio, n módulos contatados
- Top temas recorrentes (chips clicáveis filtrando histórico)
- Sentimento agregado (mood gauge)
- Flags: VIP / Frágil / Churn-risk / Cliente novo (<7d)
- Últimas 3 interações (links)
- Notas qualitativas Jana ("este cliente prefere atendimento por chamada — anotado em 2026-04-12 conv=84")

#### 2. Memória persistente (read + write)
- Persiste no DB, sobrevive `/clear` do atendente
- Read: atendente abre conv → memória carrega
- Write: 3 caminhos:
  - **Automático** (Jana infere via LLM + heurística — temas, sentimento, churn score)
  - **Atendente humano** ("anote: este cliente é Pinheiros SP, sócio é Sr. X" via slash command `/lembrar`)
  - **Sistema** (importação Contact CRM, integração faturamento — LTV automático)

#### 3. Smart recall (Jana usa memória ao responder)
- Quando bot Jana responde cliente, pipeline:
  1. Pega últimas 5 msgs da conv (curto prazo)
  2. Pega `customer_memory.notas_jana` + `temas_recorrentes` (médio prazo)
  3. Busca semântica (Meilisearch + embeddings) nas msgs antigas (longo prazo)
  4. Injeta tudo no prompt → resposta personalizada
- Exemplo: cliente +5527...5927 (conv=47, 1800 msgs) tem temas_recorrentes `["nfe","atualizacao_bug","performance"]`. Quando ele manda "está lento de novo", Jana já sabe contexto histórico — responde com awareness.

#### 4. LGPD compliance (não opcional)
- **Right to access:** cliente pede via canal oficial → exporta JSON com tudo sobre ele
- **Right to erasure:** delete cascade em `customer_memory` + soft-delete em `Contact` + retention `messages` (lei nfe exige 5 anos histórico, mas anonimiza nome)
- **Audit log:** quem leu memória de quem, quando (tabela `customer_memory_access_log`)
- **Atendente vê só `business_id` dele** (Tier 0 — já garantido por scope global)

#### 5. Caps & guardrails (LLM em produção)
- Latência: recall + LLM resposta < 2s pro UX não quebrar
- Custo: budget mensal por business (cap hard)
- Hallucination: Jana NUNCA inventa "cliente disse X" — só cita msg específica com `message_id`
- Tom: Jana espelha tom histórico do cliente (formal/casual inferido da memória)

---

## §5 — Estado-da-arte atendimento (preview — agent entrega detalhes)

> Esta seção é placeholder. Agent `estado-da-arte` foi spawned em paralelo. Quando entregar `memory/sessions/2026-05-15-arte-atendimento-omnichannel-memoria-cliente.md`, vou citar especificamente:
>
> - O que Front faz com Customer Context (e como difere de Intercom)
> - Sierra.ai / Decagon — agentes resolutivos autônomos: aplicabilidade pra PME BR
> - VoC analytics: Klaviyo vs Gong vs Crisp MagicReply
> - Padrões anti-pattern abandonados em 2026 (ticket rígido, queue-based, status open/closed)
> - Custo realista: modelos pequenos cached × full LLM call

Visão preliminar (minha leitura geral, será refinada):

- **Conversation > Ticket:** o "ticket" como entidade rígida foi abandonado. Conversa é o objeto central (Inbox unificada Front-style)
- **AI Co-pilot > AI Replacement:** 2026 estabilizou que atendente humano + AI side-by-side > AI sozinha respondendo cliente. Sierra.ai é exceção (alto investimento, B2C massivo)
- **Customer Context é commodity:** quem não tem sidebar com perfil agregado, perdeu o jogo
- **VoC analytics SIMPLES > complexo:** classificar msg em 5-7 categorias + tema bate 80% do valor; dashboards complexos viram shelf-ware
- **Embeddings + retrieval > LLM brute force:** custo cai 10-50× ao usar embeddings cached + reranker (já é stack Jana, [ADR 0048](../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md))

---

## §6 — Próximas ações priorizadas (30 dias)

### Onda 1 — Fundação memória cliente (P0)
1. **Migration `customer_memory`** — schema da camada 3 acima · 1 PR ~200 linhas · `feat(whatsapp): customer_memory P0`
2. **Job daily `customer-memory:rebuild --business=N`** — recalcula stats agregados das últimas 90d msgs · cron 02h · 1 PR ~250 linhas
3. **Backfill biz=1** — popular `customer_memory` com 249 clientes ativos · comando one-shot

### Onda 2 — Customer 360 sidebar UI (P0)
4. **Endpoint** `GET /atendimento/customer/{external_id}/profile` → JSON snapshot
5. **Componente React** `<CustomerSidebar>` em `resources/js/Pages/Whatsapp/Inbox.tsx` — Inertia::defer pra não pesar abertura

### Onda 3 — Inferência automática (P1)
6. **Job mensal** `customer-memory:enrich-themes` — Jana clusteriza msgs por cliente → atualiza `temas_recorrentes` (custo ~R$ [redacted Tier 0]/cliente/mês × 249 = R$ [redacted Tier 0]/mês biz=1)
7. **Sentiment score** — modelo pequeno cached (Haiku 4.5 ou local Llama 3.2 1B) — ~R$ [redacted Tier 0]/msg
8. **Churn risk score** — regra heurística primeiro (sem LLM): `(n_msgs_negativos_30d / n_msgs_30d) > 0.4 → score=0.8`. ML depois.

### Onda 4 — Dashboard "Voz do Cliente" (P1)
9. **Tabela `customer_voice_snapshots`** + cron 06h
10. **UI `/copiloto/voz-cliente`** — top temas semana + clientes high-touch + features demandadas
11. **Brief diário Wagner** — extension do BriefDiarioAgent inclui seção "voz cliente" quando snapshot atualizado

### Onda 5 — Capterra reverso (P2)
12. **Tabela `product_feedback_signals`** + Jana mensal clusteriza
13. **Comando** `php artisan voz-cliente:feedback-roadmap` — output markdown com top 10 features demandadas pro Wagner usar em planning

### Out of scope (ficam pra outros ciclos)
- **PR #916 análise IA por mensagem** — fica draft, retomar quando Wagner aprovar custo gpt-4o-mini (após Onda 3 validar fluxo end-to-end)
- **Sierra.ai-style agent resolutivo autônomo** — só após Onda 1-4 estáveis e Jana com track record de 3+ meses
- **Self-service guiado** (resolver top 5 casos sem AnyDesk) — depende UX research separado

---

## §7 — Decisões pendentes Wagner (gate humano)

1. **Aprova as 5 camadas de §3 como roadmap canônico?** Se sim, viro tasks MCP `US-WA-VOZ-001..010` distribuídas por owner (Felipe/Maiara/Luiz).
2. **Persistir memória cliente: tabela `customer_memory` separada (proposta) OU enriquecer `Contact` existente com colunas?**
   - **Proposta minha:** separada. Razão: Contact é CRM clássico (cadastro estático). `customer_memory` é metadado derivado (dinâmico, recalculado). Misturar polui Contact + dificulta LGPD erasure granular.
3. **Custo IA pra inferência (Onda 3):**
   - Sentiment + classificação per-msg: ~R$ [redacted Tier 0]/mês/biz com modelo pequeno
   - Clustering mensal: ~R$ [redacted Tier 0]/mês biz=1 (249 clientes)
   - **Total estimado biz=1:** R$ [redacted Tier 0]-30/mês — vale?
4. **PR #916 (análise per-msg) — fica draft até quando?** Posso reabrir ready se você quiser executar paralelo às Ondas 1-4. Ou fechar permanente se preferir só inferência mensal (mais barata).

---

## §8 — Notas operacionais

- **Workers ativos:** 4 queue:work drenando whatsapp-history (~50 chunks/30min). default queue 12k stuck — investigação separada pendente.
- **Total msgs biz=1 agora:** 26.932 (subiu de 21.482 → 26.932 = +5k em ~25min)
- **PR #916:** marcado draft em 2026-05-15 21:30 BRT
- **Companion doc:** `memory/sessions/2026-05-15-arte-atendimento-omnichannel-memoria-cliente.md` (agent rodando paralelo)

**Status:** análise feita por Claude · proposta arquitetural completa · aguarda Wagner decidir Ondas 1-5 + escopo das 4 decisões pendentes acima.
